<?php

namespace Wexample\SymfonyUser\Tests\Integration;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Mime\Email;
use Wexample\SymfonyUser\Service\MagicLinkService;
use Wexample\SymfonyUser\Service\TwoFactorCodeService;
use Wexample\SymfonyUser\Tests\Fixtures\App\Entity\User;
use Wexample\SymfonyUser\Tests\Traits\DatabaseTestTrait;

class TwoFactorTest extends WebTestCase
{
    use DatabaseTestTrait;

    private KernelBrowser $client;

    private EntityManagerInterface $entityManager;

    private User $user;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->client->disableReboot();
        $this->entityManager = $this->createDatabaseSchema();
        self::getContainer()->get('cache.rate_limiter')->clear();
        self::getContainer()->get('cache.app')->clear();

        $this->user = (new User())
            ->setEmail('jane@example.com')
            ->setUsername('jane')
            ->setPassword('secret')
            ->setEnabled(true);
        $this->entityManager->persist($this->user);
        $this->entityManager->flush();
    }

    public function testThePasswordLeadsToTheCodeForm(): void
    {
        $payload = $this->login();

        $this->assertTrue($payload['ok']);
        $this->assertSame('/login/2fa', $payload['action']['url']);
        $this->assertEmailCount(1);
        $this->assertMatchesRegularExpression('/^\d{6}$/', $this->getLastCode());

        $this->client->request('GET', '/protected');
        $this->assertResponseRedirects('/login/2fa');

        $this->client->xmlHttpRequest('GET', '/protected');
        $this->assertResponseStatusCodeSame(401);
    }

    public function testAWrongCodeThenTheRightOne(): void
    {
        $this->login();
        $code = $this->getLastCode();

        $wrong = $this->submitCode($code === '000000' ? '111111' : '000000');
        $this->assertFalse($wrong['ok']);
        $this->assertSame(['@form::error.invalid'], $wrong['form']['errors']['form']);

        $right = $this->submitCode($code);
        $this->assertTrue($right['ok']);
        $this->assertSame('redirect', $right['action']['type']);

        $this->client->request('GET', '/protected');
        $this->assertResponseIsSuccessful();
    }

    public function testACodeDiesAfterTooManyWrongAttempts(): void
    {
        $this->login();
        $code = $this->getLastCode();
        $wrong = $code === '000000' ? '111111' : '000000';

        for ($attempt = 0; $attempt < TwoFactorCodeService::MAX_ATTEMPTS; ++$attempt) {
            $this->submitCode($wrong);
        }

        $this->assertSame(['@form::error.too_many_attempts'], $this->submitCode($code)['form']['errors']['form']);
    }

    public function testTheCodeCannotBeResentRightAway(): void
    {
        $this->login();
        $this->assertEmailCount(1);

        $this->client->request('POST', '/login/2fa/resend');
        $this->assertResponseRedirects('/login/2fa');
        $this->assertEmailCount(0);
    }

    public function testATrustedDeviceSkipsTheCodeUntilRevoked(): void
    {
        $this->login();
        $this->assertTrue($this->submitCode($this->getLastCode(), trusted: true)['ok']);
        $this->client->request('GET', '/logout');

        $this->assertSame('/', $this->login()['action']['url']);
        $this->client->request('GET', '/logout');

        // The test client resets the entity manager between requests.
        $entityManager = self::getContainer()->get('doctrine')->getManager();
        $entityManager->find(User::class, $this->user->getId())->revokeTrustedDevices();
        $entityManager->flush();

        $this->assertSame('/login/2fa', $this->login()['action']['url']);
    }

    public function testAnUntrustedDeviceIsAskedAgain(): void
    {
        $this->login();
        $this->submitCode($this->getLastCode(), trusted: false);
        $this->client->request('GET', '/logout');

        $this->assertSame('/login/2fa', $this->login()['action']['url']);
    }

    public function testAMagicLinkNeedsNoCode(): void
    {
        $link = self::getContainer()->get(MagicLinkService::class)->createLink($this->user);

        $this->client->request('GET', $link->getUrl());
        $this->client->request('GET', '/protected');

        $this->assertResponseIsSuccessful();
    }

    public function testCancellingLogsOut(): void
    {
        $this->login();
        $this->client->request('GET', '/logout');
        $this->client->request('GET', '/protected');

        $this->assertResponseRedirects('/login');
    }

    private function login(): array
    {
        return $this->post('form-login_form', 'login_form', ['identifier' => 'jane', 'password' => 'secret']);
    }

    private function submitCode(string $code, bool $trusted = false): array
    {
        return $this->post(
            'form-two_factor_code_form',
            'two_factor_code_form',
            ['code' => $code] + ($trusted ? ['trusted' => '1'] : [])
        );
    }

    private function getLastCode(): string
    {
        $email = $this->getMailerMessage(count(self::getMailerMessages()) - 1);
        $this->assertInstanceOf(Email::class, $email);
        preg_match('#<strong>(\d{6})</strong>#', (string) $email->getHtmlBody(), $matches);

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
