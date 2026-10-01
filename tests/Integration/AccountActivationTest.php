<?php

namespace Wexample\SymfonyUser\Tests\Integration;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Mime\Email;
use Wexample\SymfonyUser\Enum\AccountAdministrationRefusal;
use Wexample\SymfonyUser\Event\SecurityEvent;
use Wexample\SymfonyUser\Exception\AccountAdministrationException;
use Wexample\SymfonyUser\Service\AccountAdministrationService;
use Wexample\SymfonyUser\Service\PasswordResetService;
use Wexample\SymfonyUser\Tests\Fixtures\App\Entity\User;
use Wexample\SymfonyUser\Tests\Fixtures\App\ManagesAppKernel;
use Wexample\SymfonyUser\Tests\Traits\DatabaseTestTrait;

/**
 * Accounts created by a team administrator, who manages ROLE_MEMBER; the
 * "outsider-" accounts are out of their scope.
 */
class AccountActivationTest extends WebTestCase
{
    use DatabaseTestTrait;

    private const string PASSWORD = 'correct horse battery staple';

    private KernelBrowser $client;

    private EntityManagerInterface $entityManager;

    private AccountAdministrationService $administration;

    private User $teamAdmin;

    /**
     * @var list<string>
     */
    private array $journal = [];

    protected static function getKernelClass(): string
    {
        return ManagesAppKernel::class;
    }

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->client->disableReboot();
        $this->entityManager = $this->createDatabaseSchema();
        self::getContainer()->get('cache.rate_limiter')->clear();
        self::getContainer()->get('cache.app')->clear();
        $this->administration = self::getContainer()->get('test.account_administration');

        // symfony-loader declares its Twig globals at the first page it
        // renders: a mail rendered before it, in the same process, would
        // make every page fail. A page first.
        $this->client->request('GET', '/password/activate');

        $this->teamAdmin = (new User())
            ->setEmail('team-admin@example.com')
            ->setUsername('team-admin')
            ->setPassword('secret')
            ->setRoles(['ROLE_TEAM_ADMIN'])
            ->setEnabled(true);
        $this->entityManager->persist($this->teamAdmin);
        $this->entityManager->flush();

        self::getContainer()->get('event_dispatcher')->addListener(
            SecurityEvent::class,
            fn (SecurityEvent $event) => $this->journal[] = $event->type->value
        );
    }

    public function testACreatedAccountChoosesItsPasswordThroughItsMail(): void
    {
        $member = $this->createAccount('member');

        $this->assertNull($member->getPassword());
        $this->assertTrue($member->isEnabled());
        $link = $this->getLastLink();
        $this->assertStringContainsString('/password/activate?', $link);

        // No password yet: nothing signs in.
        $this->assertFalse($this->login('member', ''));

        $this->client->request('GET', $link);
        $this->assertResponseRedirects('/password/new');
        $this->assertTrue($this->post('form-set_password_form', 'set_password_form', [
            'new_password' => ['first' => self::PASSWORD, 'second' => self::PASSWORD],
        ])['ok']);

        $this->client->request('GET', '/protected');
        $this->assertResponseIsSuccessful();

        // Activated: the link is dead.
        $this->client->request('GET', '/logout');
        $this->client->request('GET', $link);
        $this->assertResponseIsSuccessful();
        $this->client->request('GET', '/password/new');
        $this->assertResponseRedirects('/password/forgot');

        $this->assertSame(
            ['account.created', 'security_message.sent', 'account.activation_sent', 'account.activated'],
            array_values(array_filter($this->journal, fn (string $type) => str_starts_with($type, 'account.') || $type === 'security_message.sent'))
        );
    }

    public function testADeadLinkExplainsItself(): void
    {
        $member = $this->createAccount('member');
        [$expired] = self::getContainer()->get(PasswordResetService::class)->createActivationLink($member, -60);

        $this->client->request('GET', $expired);
        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('.page');

        // No proof granted.
        $this->client->request('GET', '/password/new');
        $this->assertResponseRedirects('/password/forgot');
    }

    public function testCreatingIsBoundByTheAdministrationRules(): void
    {
        $this->assertRefused(
            AccountAdministrationRefusal::ROLE_NOT_ASSIGNABLE,
            fn () => $this->createAccount('platform', ['ROLE_PLATFORM_ADMIN'])
        );
        $this->assertRefused(
            AccountAdministrationRefusal::TARGET_OUT_OF_SCOPE,
            fn () => $this->createAccount('outsider-member')
        );
        $this->assertEmailCount(0);
        $this->assertCount(1, $this->entityManager->getRepository(User::class)->findAll());
    }

    public function testAnAdministratorSendsThePasswordMailAgain(): void
    {
        $member = $this->createAccount('member');
        $firstLink = $this->getLastLink();

        // Waiting for its first password: the activation mail again.
        $this->administration->sendPasswordMail($this->teamAdmin, $member);
        $this->assertStringContainsString('/password/activate?', $this->getLastLink());

        // Activated: a mail to choose a new password.
        $member->setPassword('secret');
        $this->entityManager->flush();
        $this->administration->sendPasswordMail($this->teamAdmin, $member);
        $this->assertStringContainsString('/password/reset?', $this->getLastLink());

        // The password set killed the activation links.
        $this->client->request('GET', $firstLink);
        $this->assertResponseIsSuccessful();
        $this->client->request('GET', '/password/new');
        $this->assertResponseRedirects('/password/forgot');

        $this->administration->deactivate($this->teamAdmin, $member);
        $this->assertRefused(
            AccountAdministrationRefusal::ACCOUNT_INACTIVE,
            fn () => $this->administration->sendPasswordMail($this->teamAdmin, $member)
        );

        $platformAdmin = (new User())
            ->setEmail('platform-admin@example.com')
            ->setUsername('platform-admin')
            ->setPassword('secret')
            ->setRoles(['ROLE_PLATFORM_ADMIN'])
            ->setEnabled(true);
        $this->entityManager->persist($platformAdmin);
        $this->entityManager->flush();
        $this->assertRefused(
            AccountAdministrationRefusal::ROLE_NOT_ASSIGNABLE,
            fn () => $this->administration->sendPasswordMail($platformAdmin, $member)
        );

        $this->assertSame(
            ['account.activation_sent', 'account.activation_sent', 'account.password_mail_sent'],
            array_values(array_filter($this->journal, fn (string $type) => str_ends_with($type, '_sent') && str_starts_with($type, 'account.')))
        );
    }

    /**
     * @param list<string> $roles
     */
    private function createAccount(string $username, array $roles = ['ROLE_MEMBER']): User
    {
        $account = (new User())
            ->setEmail($username . '@example.com')
            ->setUsername($username)
            ->setRoles($roles)
            ->setEmailTwoFactorEnabled(false);

        $this->administration->createAccount($this->teamAdmin, $account);

        return $account;
    }

    private function getLastLink(): string
    {
        $messages = $this->getMailerMessages();
        $email = end($messages);
        $this->assertInstanceOf(Email::class, $email);
        preg_match('#href="([^"]+)"#', (string) $email->getHtmlBody(), $matches);

        return html_entity_decode($matches[1]);
    }

    private function login(string $identifier, string $password): bool
    {
        return $this->post('form-login_form', 'login_form', [
            'identifier' => $identifier,
            'password' => $password,
        ])['ok'];
    }

    private function post(string $name, string $formName, array $data): array
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

    private function assertRefused(AccountAdministrationRefusal $refusal, callable $change): void
    {
        try {
            $change();
        } catch (AccountAdministrationException $exception) {
            $this->assertSame($refusal, $exception->refusal);

            return;
        }

        $this->fail(sprintf('Expected the refusal "%s".', $refusal->value));
    }
}
