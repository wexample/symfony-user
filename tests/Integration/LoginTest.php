<?php

namespace Wexample\SymfonyUser\Tests\Integration;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Wexample\SymfonyUser\Security\UserChecker;
use Wexample\SymfonyUser\Service\FormProcessor\LoginFormProcessor;
use Wexample\SymfonyUser\Tests\Fixtures\App\Entity\User;
use Wexample\SymfonyUser\Tests\Traits\DatabaseTestTrait;

/**
 * Logs in over HTTP through the forms bundle submit route, the way the
 * login form does from a page or from an ajax modal.
 */
class LoginTest extends WebTestCase
{
    use DatabaseTestTrait;

    private const string SUBMIT_PATH = '/_forms/submit/form-login_form';

    private KernelBrowser $client;

    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        // The database lives in memory: a rebooted kernel would start on an
        // empty one at every request.
        $this->client->disableReboot();
        $this->entityManager = $this->createDatabaseSchema();

        $this->createUser('jane@example.com', 'jane');
        $this->createUser('locked@example.com', 'locked')->setLocked(true);
        $this->createUser('inactive@example.com', 'inactive')->setEnabled(false);
        $this->entityManager->flush();
    }

    public function testLoginByEmail(): void
    {
        $payload = $this->submitJson('jane@example.com', 'secret');

        $this->assertTrue($payload['ok']);
        $this->assertSame(['type' => 'redirect', 'url' => '/'], $payload['action']);
        $this->assertLoggedIn();
    }

    public function testLoginByUsernameUpdatesTheLastLogin(): void
    {
        $this->assertTrue($this->submitJson('JANE', 'secret')['ok']);

        $this->entityManager->clear();
        $user = $this->entityManager->getRepository(User::class)->findOneBy(['username' => 'jane']);
        $this->assertNotNull($user->getDateLastLogin());
    }

    public function testWrongPasswordAndUnknownUserGiveTheSameError(): void
    {
        $wrongPassword = $this->submitJson('jane@example.com', 'wrong');
        $unknownUser = $this->submitJson('john@example.com', 'secret');

        $this->assertFalse($wrongPassword['ok']);
        $this->assertSame(
            ['@form::' . LoginFormProcessor::ERROR_INVALID_CREDENTIALS],
            $wrongPassword['form']['errors']['form']
        );
        $this->assertSame($wrongPassword['form']['errors'], $unknownUser['form']['errors']);
        $this->assertNotLoggedIn();
    }

    public function testLockedAndInactiveAccountsAreRefused(): void
    {
        $this->assertSame(
            ['@form::' . UserChecker::ERROR_ACCOUNT_LOCKED],
            $this->submitJson('locked', 'secret')['form']['errors']['form']
        );
        $this->assertSame(
            ['@form::' . UserChecker::ERROR_ACCOUNT_DISABLED],
            $this->submitJson('inactive', 'secret')['form']['errors']['form']
        );
        $this->assertNotLoggedIn();
    }

    public function testAccountStateStaysHiddenBehindAWrongPassword(): void
    {
        $this->assertSame(
            ['@form::' . LoginFormProcessor::ERROR_INVALID_CREDENTIALS],
            $this->submitJson('locked', 'wrong')['form']['errors']['form']
        );
    }

    public function testAMissingCsrfTokenIsRefused(): void
    {
        $this->submit('jane', 'secret', ['HTTP_ACCEPT' => 'application/json'], '');

        $this->assertSame(
            ['@form::' . LoginFormProcessor::ERROR_INVALID_CSRF],
            json_decode($this->client->getResponse()->getContent(), true)['form']['errors']['form']
        );
        $this->assertNotLoggedIn();
    }

    public function testTheTargetPathSetBeforeLoginIsHonoured(): void
    {
        $this->client->request('GET', '/protected');
        $this->assertResponseRedirects('/login');

        $this->assertSame('/protected', parse_url($this->submitJson('jane', 'secret')['action']['url'], PHP_URL_PATH));
    }

    public function testAnAjaxCallToAProtectedUrlGetsJson(): void
    {
        $this->client->xmlHttpRequest('GET', '/protected');

        $this->assertResponseStatusCodeSame(401);
        $this->assertSame(
            ['ok' => false, 'action' => ['type' => 'redirect', 'url' => '/login']],
            json_decode($this->client->getResponse()->getContent(), true)
        );
    }

    public function testAPageSubmissionRedirects(): void
    {
        $this->submit('jane', 'wrong', ['HTTP_REFERER' => 'http://localhost/some/page']);
        $this->assertResponseRedirects('http://localhost/some/page');

        $this->submit('jane', 'wrong', ['HTTP_REFERER' => 'https://evil.example/']);
        $this->assertResponseRedirects('/login');

        $this->submit('jane', 'secret');
        $this->assertResponseRedirects('/');
        $this->assertLoggedIn();
    }

    private function createUser(string $email, string $username): User
    {
        $user = (new User())
            ->setEmail($email)
            ->setUsername($username)
            ->setPassword('secret')
            ->setEnabled(true);

        $this->entityManager->persist($user);

        return $user;
    }

    private function submit(
        string $identifier,
        string $password,
        array $server = [],
        string $csrfToken = 'csrf-token'
    ): void {
        // A same-origin request carrying the placeholder token is what the
        // stateless CSRF check accepts without any cookie.
        $this->client->request('POST', self::SUBMIT_PATH, [
            'login_form' => [
                'identifier' => $identifier,
                'password' => $password,
                '_token' => $csrfToken,
            ],
        ], [], $server + ['HTTP_ORIGIN' => 'http://localhost']);
    }

    private function submitJson(string $identifier, string $password): array
    {
        $this->submit($identifier, $password, ['HTTP_ACCEPT' => 'application/json']);

        return json_decode($this->client->getResponse()->getContent(), true);
    }

    private function assertLoggedIn(): void
    {
        $this->client->request('GET', '/protected');
        $this->assertResponseIsSuccessful();
    }

    private function assertNotLoggedIn(): void
    {
        $this->client->request('GET', '/protected');
        $this->assertResponseRedirects('/login');
    }
}
