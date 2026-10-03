<?php

namespace Wexample\SymfonyUser\Tests\Integration;

use Wexample\SymfonyUser\Service\ImpersonationService;

/**
 * The default rule: the accounts the actor administers.
 */
class ImpersonationTest extends AbstractImpersonationTestCase
{
    public function testOnlyTheAccountsTheActorAdministersAreListed(): void
    {
        $this->assertSame(
            ['support-a@example.com', 'support-b@example.com'],
            $this->identifiers($this->getService()->listTargets($this->find('manager')))
        );

        // Past the threshold, nothing is listed: the page searches.
        $this->createUser('support-c', ['ROLE_SUPPORT']);
        $this->createUser('support-d', ['ROLE_SUPPORT']);
        $this->entityManager->flush();
        $this->assertNull($this->getService()->listTargets($this->find('manager')));
    }

    public function testASearchFindsOnlyWhatTheRulesAllow(): void
    {
        $service = $this->getService();
        $manager = $this->find('manager');

        $this->assertSame(['support-a@example.com', 'support-b@example.com'], $this->identifiers($service->searchTargets($manager, 'SUPPORT')));
        $this->assertSame([], $service->searchTargets($manager, 'owner'));
        $this->assertSame([], $service->searchTargets($manager, 's'));

        for ($i = 0; $i < ImpersonationService::SEARCHES_PER_MINUTE; ++$i) {
            $service->searchTargets($manager, 'support');
        }
        $this->assertSame([], $service->searchTargets($manager, 'support'));
    }

    public function testTheFormLeadsToTheSwitch(): void
    {
        $this->login('manager');

        // The switch happens where the account lands once signed in.
        $payload = $this->chooseAccount('support-a@example.com');
        $this->assertTrue($payload['ok']);
        $this->assertSame('/protected?_switch_user=support-a%40example.com', $payload['action']['url']);

        $this->client->request('GET', $payload['action']['url']);
        $this->assertResponseRedirects('/protected');
        $this->assertSame('support-a@example.com', $this->getToken()->getUserIdentifier());
        $this->assertContains('account.impersonation_started', $this->types());
    }

    public function testFromOneImpersonationToTheNextWithTheOriginalRights(): void
    {
        $this->login('manager');
        $this->client->request('GET', $this->chooseAccount('support-a@example.com')['action']['url']);

        // Support cannot impersonate; the manager behind them chooses.
        $payload = $this->chooseAccount('support-b@example.com');
        $this->assertTrue($payload['ok']);
        $this->client->request('GET', $payload['action']['url']);

        $this->assertSame('support-b@example.com', $this->getToken()->getUserIdentifier());
        $this->assertSame('manager@example.com', $this->getToken()->getOriginalToken()->getUserIdentifier());

        // Still the manager's rule: the owner stays out of reach.
        $this->assertFalse($this->chooseAccount('owner@example.com')['ok']);

        $this->client->request('GET', '/protected?_switch_user=_exit');
        $this->assertSame('manager@example.com', $this->getToken()->getUserIdentifier());
    }

    public function testASwitchNotChosenThroughTheFormIsRefused(): void
    {
        $this->login('manager');

        // A bare link, sent to an administrator.
        $this->client->request('GET', '/protected?_switch_user=support-a@example.com');
        $this->assertResponseStatusCodeSame(403);
        $this->assertSame('impersonation=no_intent', $this->lastDenial()->extra['reasons']);

        // Chosen for one account, used for another.
        $this->chooseAccount('support-a@example.com');
        $this->client->request('GET', '/protected?_switch_user=support-b@example.com');
        $this->assertResponseStatusCodeSame(403);
        $this->assertNotContains('account.impersonation_started', $this->types());
    }

    public function testAnAccountTheActorDoesNotAdministerIsNeverReached(): void
    {
        $this->login('manager');

        foreach (['owner@example.com', 'support-off@example.com', 'outsider-support@example.com', 'manager@example.com', 'nobody@example.com'] as $identifier) {
            $payload = $this->chooseAccount($identifier);
            $this->assertFalse($payload['ok'], $identifier);
        }

        $this->client->request('GET', '/protected?_switch_user=owner@example.com');
        $this->assertResponseStatusCodeSame(403);
        $this->assertSame('impersonation=not_administered', $this->lastDenial()->extra['reasons']);
    }

    public function testWithoutTheSwitchRoleThePageAndTheSearchAreRefused(): void
    {
        $this->login('support-a');

        $this->client->request('GET', '/account/impersonate');
        $this->assertResponseStatusCodeSame(403);
        $this->client->request('GET', '/account/impersonate/search?q=support');
        $this->assertResponseStatusCodeSame(403);
        $this->assertSame('IMPERSONATE', $this->lastDenial()->extra['attributes']);

        $this->assertFalse($this->chooseAccount('support-b@example.com')['ok']);
    }


    public function testTheSearchEndpointAnswersWithTheAllowedAccounts(): void
    {
        $this->login('manager');

        $this->client->request('GET', '/account/impersonate/search?q=support');
        $this->assertResponseIsSuccessful();
        $targets = json_decode($this->client->getResponse()->getContent(), true)['targets'];

        $this->assertSame(['support-a@example.com', 'support-b@example.com'], array_column($targets, 'identifier'));
        $this->assertSame(['ROLE_SUPPORT'], $targets[0]['roles']);
    }
}
