<?php

namespace Wexample\SymfonyUser\Tests\Integration;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Wexample\SymfonyUser\Tests\Fixtures\App\Entity\Membership;
use Wexample\SymfonyUser\Tests\Fixtures\App\Entity\Team;
use Wexample\SymfonyUser\Tests\Fixtures\App\Entity\User;
use Wexample\SymfonyUser\Tests\Fixtures\App\MembershipAppKernel;
use Wexample\SymfonyUser\Tests\Traits\DatabaseTestTrait;

/**
 * The members screens of symfony-user-ds: ada administers Alpha, bob watches
 * it, cid belongs to no team.
 */
class MembershipScreenTest extends WebTestCase
{
    use DatabaseTestTrait;

    private KernelBrowser $client;

    private EntityManagerInterface $entityManager;

    private string $alpha;

    private string $bobMembership;

    protected static function getKernelClass(): string
    {
        return MembershipAppKernel::class;
    }

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->client->disableReboot();
        $this->entityManager = $this->createDatabaseSchema();
        self::getContainer()->get('cache.rate_limiter')->clear();

        $team = new Team('Alpha');
        $this->entityManager->persist($team);
        $ada = $this->createUser('ada');
        $bob = $this->createUser('bob');
        $this->createUser('cid');
        $this->entityManager->persist(new Membership($team, $ada, ['administrator']));
        $this->entityManager->persist($membership = new Membership($team, $bob, ['watcher']));
        $this->entityManager->flush();

        $this->alpha = 'team:' . $team->getId();
        $this->bobMembership = $this->alpha . ':' . $membership->getId();
    }

    public function testAGroupIsUnknownToWhoeverIsNotInIt(): void
    {
        $this->login('cid');

        foreach (['/memberships/' . $this->alpha, '/memberships/' . $this->bobMembership . '/member', '/memberships/nothing:1', '/memberships/team:not-an-id'] as $url) {
            $this->client->request('GET', $url);
            $this->assertResponseStatusCodeSame(404, $url);
        }
    }

    public function testAMemberDoesNotOpenAnotherOne(): void
    {
        $this->login('bob');

        $this->client->request('GET', '/memberships/' . $this->bobMembership . '/member');
        $this->assertResponseStatusCodeSame(404);

        $this->post('membership_grants_form', ['grant_administrator' => '1'], $this->bobMembership);
        $this->assertResponseStatusCodeSame(403);
        $this->assertSame(['watcher'], $this->findMembership('bob')->getGrants());
    }

    public function testAnAdministratorChangesTheGrantsAndReloadsTheList(): void
    {
        $this->login('ada');

        $payload = $this->post('membership_grants_form', ['grant_contributor' => '1', 'grant_client' => '1'], $this->bobMembership);

        $this->assertTrue($payload['ok']);
        $this->assertSame('/memberships/' . $this->alpha, $payload['action']['url']);
        $this->assertSame(['contributor', 'client'], $this->findMembership('bob')->getGrants());
    }

    public function testARefusalComesBackOnTheForm(): void
    {
        $this->login('ada');

        $payload = $this->post('membership_grants_form', [], $this->bobMembership);

        $this->assertFalse($payload['ok']);
        $this->assertSame(['watcher'], $this->findMembership('bob')->getGrants());
    }

    public function testAnAdministratorRemovesAMember(): void
    {
        $this->login('ada');

        $this->assertTrue($this->post('membership_remove_form', [], $this->bobMembership)['ok']);
        $this->assertNull($this->findMembership('bob'));
    }

    private function createUser(string $username): User
    {
        $user = (new User())
            ->setEmail($username . '@example.com')
            ->setUsername($username)
            ->setPassword('secret')
            ->setEnabled(true)
            ->setEmailTwoFactorEnabled(false);
        $this->entityManager->persist($user);

        return $user;
    }

    private function findMembership(string $username): ?Membership
    {
        $this->entityManager->clear();
        $user = $this->entityManager->getRepository(User::class)->findOneBy(['username' => $username]);

        return $this->entityManager->getRepository(Membership::class)->findOneBy(['user' => $user]);
    }

    private function login(string $username): void
    {
        $this->assertTrue($this->post('login_form', [
            'identifier' => $username,
            'password' => 'secret',
        ])['ok']);
    }

    private function post(string $formName, array $data, ?string $id = null): ?array
    {
        $this->client->request(
            'POST',
            '/_forms/submit/form-' . $formName . ($id ? '/entity/' . $id : ''),
            [$formName => $data + ['_token' => 'csrf-token']],
            [],
            ['HTTP_ACCEPT' => 'application/json', 'HTTP_ORIGIN' => 'http://localhost']
        );

        return json_decode($this->client->getResponse()->getContent(), true);
    }
}
