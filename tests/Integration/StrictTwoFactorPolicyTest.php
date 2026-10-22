<?php

namespace Wexample\SymfonyUser\Tests\Integration;

use Doctrine\ORM\EntityManagerInterface;
use OTPHP\TOTP;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Wexample\SymfonyUser\Service\MagicLinkService;
use Wexample\SymfonyUser\Service\TotpService;
use Wexample\SymfonyUser\Tests\Fixtures\App\Entity\User;
use Wexample\SymfonyUser\Tests\Fixtures\App\StrictAppKernel;
use Wexample\SymfonyUser\Tests\Traits\DatabaseTestTrait;

/**
 * `two_factor.required`, `two_factor.app_required_roles: [ROLE_ADMIN]` and
 * `magic_link_login: false`: no way in on a single factor.
 */
class StrictTwoFactorPolicyTest extends WebTestCase
{
    use DatabaseTestTrait;

    private KernelBrowser $client;

    private EntityManagerInterface $entityManager;

    protected static function getKernelClass(): string
    {
        return StrictAppKernel::class;
    }

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->client->disableReboot();
        $this->entityManager = $this->createDatabaseSchema();
        self::getContainer()->get('cache.rate_limiter')->clear();
        self::getContainer()->get('cache.app')->clear();

        $this->createUser('jane')->setEmailTwoFactorEnabled(false);
        $this->createUser('admin')->setRoles(['ROLE_ADMIN']);
        $this->entityManager->flush();
    }

    public function testNoAccountIsExempt(): void
    {
        $this->assertSame('/login/2fa', $this->login('jane')['action']['url']);
        $this->assertEmailCount(1);
    }

    public function testAMagicLinkAsksTheSecondFactorToo(): void
    {
        $this->client->request('GET', $this->createLink('jane'));
        $this->client->request('GET', '/protected');

        $this->assertResponseRedirects('/login/2fa');
    }

    public function testTheLoginAfterAResetAsksTheSecondFactorToo(): void
    {
        $this->post('form-password_reset_request_form', 'password_reset_request_form', ['identifier' => 'jane']);
        preg_match('#href="([^"]+)"#', (string) $this->getMailerMessage()->getHtmlBody(), $matches);

        $this->client->request('GET', html_entity_decode($matches[1]));
        $password = 'correct horse battery staple';
        $this->assertTrue($this->post('form-set_password_form', 'set_password_form', [
            'new_password' => ['first' => $password, 'second' => $password],
        ])['ok']);

        $this->client->request('GET', '/protected');
        $this->assertResponseRedirects('/login/2fa');

        // Not prepared by this sign-in, the code leaves when its page shows.
        $this->client->request('GET', '/login/2fa');
        $this->assertEmailCount(1);
    }

    public function testTheLoginPageOffersNoMagicLink(): void
    {
        $this->client->request(
            'POST',
            '/_forms/submit/form-magic_link_request_form',
            ['magic_link_request_form' => ['identifier' => 'jane', '_token' => 'csrf-token']],
            [],
            ['HTTP_ACCEPT' => 'application/json', 'HTTP_ORIGIN' => 'http://localhost']
        );

        $this->assertTrue(json_decode($this->client->getResponse()->getContent(), true)['ok']);
        $this->assertEmailCount(0);
    }

    public function testAnAdministratorIsHeldUntilTheAppIsSetUp(): void
    {
        // The email code lets them in the first time, to set the app up.
        $this->login('admin');
        $this->assertTrue($this->submitCode($this->getLastCode())['ok']);

        $this->client->request('GET', '/protected');
        $this->assertResponseRedirects('/account/authenticator');

        $this->client->xmlHttpRequest('GET', '/protected');
        $this->assertResponseStatusCodeSame(403);

        $secret = $this->setAppUp('admin');

        $this->client->request('GET', '/protected');
        $this->assertResponseIsSuccessful();

        // The app replaces the email code, and cannot be turned off.
        $payload = $this->post('form-totp_disable_form', 'totp_disable_form', ['current_password' => 'secret']);
        $this->assertSame(['@form::error.required'], $payload['form']['errors']['form']);

        $this->client->request('GET', '/logout');
        $this->login('admin');
        $this->assertEmailCount(0);
        $this->assertTrue($this->submitCode(TOTP::createFromSecret($secret)->now())['ok']);
    }

    private function createUser(string $username): User
    {
        $user = (new User())
            ->setEmail($username . '@example.com')
            ->setUsername($username)
            ->setPassword('secret')
            ->setEnabled(true);
        $this->entityManager->persist($user);

        return $user;
    }

    private function findUser(string $username): User
    {
        return self::getContainer()->get('doctrine')->getManager()
            ->getRepository(User::class)->findOneBy(['username' => $username]);
    }

    private function createLink(string $username): string
    {
        return self::getContainer()->get(MagicLinkService::class)->createLink($this->findUser($username))->getUrl();
    }

    /**
     * Through the service, with a session of its own: the setup page itself
     * is a loader page, which the fixture kernel does not render.
     */
    private function setAppUp(string $username): string
    {
        $request = new Request();
        $request->setSession(new Session(new MockArraySessionStorage()));
        $requestStack = self::getContainer()->get('request_stack');
        $requestStack->push($request);

        try {
            $service = self::getContainer()->get(TotpService::class);
            $secret = $service->getPendingSecret();
            $this->assertTrue($service->confirm($this->findUser($username), TOTP::createFromSecret($secret)->now()));

            return $secret;
        } finally {
            $requestStack->pop();
        }
    }

    private function login(string $username): array
    {
        return $this->post('form-login_form', 'login_form', ['identifier' => $username, 'password' => 'secret']);
    }

    private function submitCode(string $code): array
    {
        return $this->post('form-two_factor_code_form', 'two_factor_code_form', ['code' => $code]);
    }

    private function getLastCode(): string
    {
        preg_match('#<strong>(\d{6})</strong>#', (string) $this->getMailerMessage()->getHtmlBody(), $matches);

        return $matches[1];
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
}
