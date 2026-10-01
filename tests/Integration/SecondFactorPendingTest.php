<?php

namespace Wexample\SymfonyUser\Tests\Integration;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Wexample\SymfonyUser\Tests\Fixtures\App\Entity\User;
use Wexample\SymfonyUser\Tests\Traits\DatabaseTestTrait;

/**
 * A correct password alone is worth nothing: no protected page, no side
 * effect, nothing left to resume once abandoned.
 */
class SecondFactorPendingTest extends WebTestCase
{
    use DatabaseTestTrait;

    private const string SESSION_COOKIE = 'MOCKSESSID';

    private KernelBrowser $client;

    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->client->disableReboot();
        $this->entityManager = $this->createDatabaseSchema();
        self::getContainer()->get('cache.rate_limiter')->clear();
        self::getContainer()->get('cache.app')->clear();

        $this->entityManager->persist(
            (new User())
                ->setEmail('jane@example.com')
                ->setUsername('jane')
                ->setPassword('secret')
                ->setEnabled(true)
                ->setRoles(['ROLE_ALLOWED_TO_SWITCH'])
        );
        $this->entityManager->persist(
            (new User())->setEmail('john@example.com')->setUsername('john')->setPassword('secret')->setEnabled(true)
        );
        $this->entityManager->flush();
    }

    public function testThePasswordAloneOpensNothing(): void
    {
        $this->login(rememberMe: true);
        $this->assertNull($this->client->getCookieJar()->get('REMEMBERME'));

        foreach (['/protected', '/account/authenticator'] as $url) {
            $this->client->request('GET', $url);
            $this->assertResponseRedirects('/login/2fa', message: $url);

            $this->client->xmlHttpRequest('GET', $url);
            $this->assertResponseStatusCodeSame(401, $url);
        }

        // No impersonation from a password waiting for its code.
        $this->client->request('GET', '/protected?_switch_user=john');
        $this->assertResponseRedirects('/login/2fa');

        $this->assertNull($this->findJane()->getDateLastLogin());
    }

    public function testTheCodeCompletesTheSignInUnderANewSessionId(): void
    {
        $this->login(rememberMe: true);
        $pendingSessionId = $this->client->getCookieJar()->get(self::SESSION_COOKIE)->getValue();

        $this->assertTrue($this->submitCode($this->getLastCode())['ok']);

        $this->assertNotSame($pendingSessionId, $this->client->getCookieJar()->get(self::SESSION_COOKIE)->getValue());
        $this->assertNotNull($this->findJane()->getDateLastLogin());
        $this->assertNotNull($this->client->getCookieJar()->get('REMEMBERME'));
    }

    public function testAnAbandonedSignInStartsOverFromThePassword(): void
    {
        $this->login();
        $code = $this->getLastCode();

        // Twenty minutes later.
        $session = $this->client->getRequest()->getSession();
        $session->set('wexample_user_two_factor_started', time() - 1200);
        $session->save();

        $this->client->request('GET', '/login/2fa');
        $this->assertResponseRedirects('/login');

        $this->client->request('GET', '/protected');
        $this->assertResponseRedirects('/login');

        // The code it was given leads nowhere either.
        $this->assertFalse($this->submitCode($code)['ok'] ?? false);
        $this->client->request('GET', '/protected');
        $this->assertResponseRedirects('/login');
    }

    private function findJane(): User
    {
        $entityManager = self::getContainer()->get('doctrine')->getManager();
        $entityManager->clear();

        return $entityManager->getRepository(User::class)->findOneBy(['username' => 'jane']);
    }

    private function login(bool $rememberMe = false): void
    {
        $this->post('form-login_form', 'login_form', ['identifier' => 'jane', 'password' => 'secret'] + ($rememberMe ? ['remember_me' => '1'] : []));
    }

    private function submitCode(string $code): ?array
    {
        return $this->post('form-two_factor_code_form', 'two_factor_code_form', ['code' => $code]);
    }

    private function getLastCode(): string
    {
        preg_match('#<strong>(\d{6})</strong>#', (string) $this->getMailerMessage()->getHtmlBody(), $matches);

        return $matches[1];
    }

    private function post(string $name, string $formName, array $data): ?array
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
}
