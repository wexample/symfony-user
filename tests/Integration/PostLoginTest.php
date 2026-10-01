<?php

namespace Wexample\SymfonyUser\Tests\Integration;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Wexample\SymfonyUser\Event\SecurityEvent;
use Wexample\SymfonyUser\Service\MagicLinkService;
use Wexample\SymfonyUser\Tests\Fixtures\App\Entity\User;
use Wexample\SymfonyUser\Tests\Fixtures\App\PostLoginAppKernel;
use Wexample\SymfonyUser\Tests\Traits\DatabaseTestTrait;

/**
 * Managers land on /manager, members on /public, anyone else on /protected;
 * /manager is reserved to managers, /organizations/{id} to organization 1.
 */
class PostLoginTest extends WebTestCase
{
    use DatabaseTestTrait;

    private KernelBrowser $client;

    private EntityManagerInterface $entityManager;

    /**
     * @var list<SecurityEvent>
     */
    private array $denials = [];

    protected static function getKernelClass(): string
    {
        return PostLoginAppKernel::class;
    }

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->client->disableReboot();
        $this->entityManager = $this->createDatabaseSchema();
        self::getContainer()->get('cache.rate_limiter')->clear();
        self::getContainer()->get('cache.app')->clear();

        $this->createUser('manager', ['ROLE_MANAGER']);
        $this->createUser('member', ['ROLE_MEMBER']);
        $this->createUser('both', ['ROLE_MEMBER', 'ROLE_MANAGER']);
        $this->createUser('other', []);
        $this->entityManager->flush();

        self::getContainer()->get('event_dispatcher')->addListener(
            SecurityEvent::class,
            function (SecurityEvent $event): void {
                if ($event->type->value === 'access.denied') {
                    $this->denials[] = $event;
                }
            }
        );
    }

    public function testWithoutASavedPageTheFirstRoleHeldDecides(): void
    {
        $this->assertSame('/manager', $this->loginJson('manager'));
        $this->client->request('GET', '/logout');
        $this->assertSame('/public', $this->loginJson('member'));
        $this->client->request('GET', '/logout');
        // In the order of the configuration, not of the user's roles.
        $this->assertSame('/manager', $this->loginJson('both'));
        $this->client->request('GET', '/logout');
        $this->assertSame('/protected', $this->loginJson('other'));
        $this->client->request('GET', '/logout');

        // A page gets redirected the same way.
        $this->client->request('POST', '/_forms/submit/form-login_form', ['login_form' => [
            'identifier' => 'member', 'password' => 'secret', '_token' => 'csrf-token',
        ]], [], ['HTTP_ORIGIN' => 'http://localhost']);
        $this->assertResponseRedirects('/public');
    }

    public function testASavedPageWinsOnlyWhenTheUserMayOpenIt(): void
    {
        $this->client->request('GET', '/manager');
        $this->assertResponseRedirects();
        $this->assertSame('/public', $this->loginJson('member'));
        $this->client->request('GET', '/logout');

        $this->client->request('GET', '/manager');
        $this->assertSame('/manager', $this->loginJson('manager'));
    }

    public function testAMagicLinkWithoutTargetLandsOnTheRoleRoute(): void
    {
        $member = $this->entityManager->getRepository(User::class)->findOneBy(['username' => 'member']);
        $link = self::getContainer()->get(MagicLinkService::class)->createLink($member);

        $this->client->request('GET', $link->getUrl());
        $this->assertResponseRedirects('/public');
    }

    public function testEveryDenialOfASignedInUserIsJournaled(): void
    {
        // Not signed in: sent to the login page, not denied.
        $this->client->request('GET', '/manager');
        $this->assertSame([], $this->denials);

        $this->loginJson('member');

        $this->client->request('GET', '/manager');
        $this->assertResponseStatusCodeSame(403);
        $this->client->request('GET', '/organizations/2?token=secret');
        $this->assertResponseStatusCodeSame(403);
        $this->client->request('GET', '/organizations/1');
        $this->assertResponseIsSuccessful();

        $this->assertCount(2, $this->denials);
        [$byAccessControl, $byVoter] = $this->denials;

        $this->assertNotNull($byAccessControl->userId);
        $this->assertNotEmpty($byAccessControl->ip);
        $this->assertSame([
            'route' => 'manager_home',
            'path' => '/manager',
            'http_method' => 'GET',
            'roles' => 'ROLE_MEMBER,ROLE_USER',
            'attributes' => 'ROLE_MANAGER',
            'reasons' => "The user doesn't have ROLE_MANAGER.",
            'suppressed' => 0,
        ], $byAccessControl->extra);

        $this->assertSame('/organizations/2', $byVoter->extra['path']);
        $this->assertSame('ORGANIZATION_VIEW', $byVoter->extra['attributes']);
        $this->assertSame('organization=2', $byVoter->extra['reasons']);
    }

    public function testARepeatedDenialIsJournaledOnceAMinuteWithItsCount(): void
    {
        $this->loginJson('member');

        for ($i = 0; $i < 5; ++$i) {
            $this->client->request('GET', '/manager');
        }
        $this->assertCount(1, $this->denials);

        // A minute later.
        $cache = self::getContainer()->get('cache.app');
        $item = $cache->getItem('wexample_user_access_denied_' . hash('xxh128', 'member@example.com|manager_home|ROLE_MANAGER'));
        $cache->save($item->set(['until' => time() - 1] + $item->get()));

        $this->client->request('GET', '/manager');
        $this->assertCount(2, $this->denials);
        $this->assertSame(4, $this->denials[1]->extra['suppressed']);
    }

    /**
     * Signs in through the ajax login form, and returns where it sends.
     */
    private function loginJson(string $username): string
    {
        $this->client->request(
            'POST',
            '/_forms/submit/form-login_form',
            ['login_form' => ['identifier' => $username, 'password' => 'secret', '_token' => 'csrf-token']],
            [],
            ['HTTP_ACCEPT' => 'application/json', 'HTTP_ORIGIN' => 'http://localhost']
        );

        return (string) parse_url(json_decode($this->client->getResponse()->getContent(), true)['action']['url'], PHP_URL_PATH);
    }

    /**
     * @param list<string> $roles
     */
    private function createUser(string $username, array $roles): void
    {
        $this->entityManager->persist(
            (new User())
                ->setEmail($username . '@example.com')
                ->setUsername($username)
                ->setPassword('secret')
                ->setRoles($roles)
                ->setEnabled(true)
                ->setEmailTwoFactorEnabled(false)
        );
    }
}
