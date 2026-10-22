<?php

namespace Wexample\SymfonyUser\Tests\Integration;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Wexample\SymfonyUser\Event\SecurityEvent;
use Wexample\SymfonyUser\Security\Token\AccountPickerToken;
use Wexample\SymfonyUser\Service\AccountPickerService;
use Wexample\SymfonyUser\Tests\Fixtures\App\AccountPickerAppKernel;
use Wexample\SymfonyUser\Tests\Fixtures\App\Entity\User;
use Wexample\SymfonyUser\Tests\Traits\DatabaseTestTrait;

/**
 * The firewall lists AccountPickerAuthenticator: choosing an active account
 * signs in, with no password and no second factor.
 */
class AccountPickerTest extends WebTestCase
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
        return AccountPickerAppKernel::class;
    }

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->client->disableReboot();
        $this->entityManager = $this->createDatabaseSchema();
        self::getContainer()->get('cache.rate_limiter')->clear();
        self::getContainer()->get('cache.app')->clear();

        // Asked a code at every password login: the picker asks none.
        foreach (['jane' => true, 'john' => true, 'off' => false] as $username => $enabled) {
            $this->entityManager->persist(
                (new User())
                    ->setEmail($username . '@example.com')
                    ->setUsername($username)
                    ->setPassword('secret')
                    ->setEnabled($enabled)
                    ->setEmailTwoFactorEnabled(true)
            );
        }
        $this->entityManager->flush();

        self::getContainer()->get('event_dispatcher')->addListener(
            SecurityEvent::class,
            fn (SecurityEvent $event) => $this->events[] = $event
        );
    }

    public function testChoosingAnAccountSignsInWithoutASecondFactor(): void
    {
        $payload = $this->pick('jane@example.com');

        $this->assertTrue($payload['ok']);
        $this->assertSame('/', $payload['action']['url']);
        $this->assertInstanceOf(AccountPickerToken::class, $this->getToken());
        $this->assertSame('jane@example.com', $this->getToken()->getUserIdentifier());
        $this->assertEmailCount(0);

        $this->client->request('GET', '/protected');
        $this->assertResponseIsSuccessful();

        $login = array_values(array_filter($this->events, static fn (SecurityEvent $event) => $event->type->value === 'login.succeeded'));
        $this->assertSame('account_picker', $login[0]->method);
    }

    public function testOnlyAnActiveAccountIsSignedIn(): void
    {
        foreach (['off@example.com', 'nobody@example.com'] as $identifier) {
            $payload = $this->pick($identifier);
            $this->assertFalse($payload['ok'], $identifier);
            $this->assertSame(['@form::error.not_allowed'], $payload['form']['errors']['form']);
        }

        $this->assertNull($this->getToken());
    }

    public function testAForgedSubmissionIsRefused(): void
    {
        $payload = $this->pick('jane@example.com', 'forged');

        $this->assertFalse($payload['ok']);
        $this->assertSame(['@form::error.invalid_csrf'], $payload['form']['errors']['form']);
    }

    public function testTheActiveAccountsAreListedOrSearched(): void
    {
        /** @var AccountPickerService $service */
        $service = self::getContainer()->get(AccountPickerService::class);

        $this->assertSame(
            ['jane@example.com', 'john@example.com'],
            array_map(static fn (User $user) => $user->getUserIdentifier(), $service->listAccounts())
        );
        $this->assertSame(['john@example.com'], array_map(static fn (User $user) => $user->getUserIdentifier(), $service->searchAccounts('JOH')));
        $this->assertSame([], $service->searchAccounts('j'));
    }

    protected function pick(string $identifier, string $token = 'csrf-token'): array
    {
        $this->client->request(
            'POST',
            '/_forms/submit/form-account_picker_form',
            ['account_picker_form' => ['account' => $identifier, '_token' => $token]],
            [],
            ['HTTP_ACCEPT' => 'application/json', 'HTTP_ORIGIN' => 'http://localhost']
        );

        return json_decode($this->client->getResponse()->getContent(), true);
    }

    protected function getToken(): mixed
    {
        return self::getContainer()->get('security.token_storage')->getToken();
    }
}
