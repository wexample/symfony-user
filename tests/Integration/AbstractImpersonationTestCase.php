<?php

namespace Wexample\SymfonyUser\Tests\Integration;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Wexample\SymfonyUser\Event\SecurityEvent;
use Wexample\SymfonyUser\Service\ImpersonationService;
use Wexample\SymfonyUser\Tests\Fixtures\App\Entity\User;
use Wexample\SymfonyUser\Tests\Fixtures\App\ImpersonationAppKernel;
use Wexample\SymfonyUser\Tests\Traits\DatabaseTestTrait;

/**
 * Managers hold the switch role and administer managers and support; the
 * owner is above them; "outsider-" accounts are out of scope; one support
 * account is disabled.
 */
abstract class AbstractImpersonationTestCase extends WebTestCase
{
    use DatabaseTestTrait;

    protected KernelBrowser $client;

    protected EntityManagerInterface $entityManager;

    /**
     * @var list<SecurityEvent>
     */
    protected array $events = [];

    protected static function getKernelClass(): string
    {
        return ImpersonationAppKernel::class;
    }

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->client->disableReboot();
        $this->entityManager = $this->createDatabaseSchema();
        self::getContainer()->get('cache.rate_limiter')->clear();
        self::getContainer()->get('cache.app')->clear();

        $this->createUser('manager', ['ROLE_MANAGER']);
        $this->createUser('owner', ['ROLE_OWNER']);
        $this->createUser('support-a', ['ROLE_SUPPORT']);
        $this->createUser('support-b', ['ROLE_SUPPORT']);
        $this->createUser('support-off', ['ROLE_SUPPORT'], enabled: false);
        $this->createUser('outsider-support', ['ROLE_SUPPORT']);
        $this->entityManager->flush();

        self::getContainer()->get('event_dispatcher')->addListener(
            SecurityEvent::class,
            fn (SecurityEvent $event) => $this->events[] = $event
        );
    }

    protected function getToken(): ?TokenInterface
    {
        return self::getContainer()->get('security.token_storage')->getToken();
    }

    protected function getService(): ImpersonationService
    {
        return self::getContainer()->get(ImpersonationService::class);
    }

    protected function find(string $username): User
    {
        return $this->entityManager->getRepository(User::class)->findOneBy(['username' => $username]);
    }

    /**
     * @param list<string> $roles
     */
    protected function createUser(string $username, array $roles, bool $enabled = true): void
    {
        $this->entityManager->persist(
            (new User())
                ->setEmail($username . '@example.com')
                ->setUsername($username)
                ->setPassword('secret')
                ->setRoles($roles)
                ->setEnabled($enabled)
                ->setEmailTwoFactorEnabled(false)
        );
    }

    protected function login(string $username): void
    {
        $this->post('form-login_form', 'login_form', ['identifier' => $username, 'password' => 'secret']);
    }

    protected function chooseAccount(string $identifier): array
    {
        return $this->post('form-impersonate_form', 'impersonate_form', ['account' => $identifier]);
    }

    protected function post(string $name, string $formName, array $data): array
    {
        $this->client->request(
            'POST',
            '/_forms/submit/' . $name,
            [$formName => $data + ['_token' => 'csrf-token']],
            [],
            ['HTTP_ACCEPT' => 'application/json', 'HTTP_ORIGIN' => 'http://localhost']
        );

        return json_decode($this->client->getResponse()->getContent(), true);
    }

    /**
     * @param list<User>|null $users
     *
     * @return list<string>
     */
    protected function identifiers(?array $users): array
    {
        return array_map(static fn (User $user) => $user->getUserIdentifier(), $users ?? []);
    }

    /**
     * @return list<string>
     */
    protected function types(): array
    {
        return array_map(static fn (SecurityEvent $event) => $event->type->value, $this->events);
    }

    protected function lastDenial(): SecurityEvent
    {
        $denials = array_values(array_filter($this->events, static fn (SecurityEvent $event) => $event->type->value === 'access.denied'));

        return end($denials);
    }
}
