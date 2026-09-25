<?php

namespace Wexample\SymfonyUser\Tests\Integration;

use Doctrine\ORM\EntityManagerInterface;
use OTPHP\TOTP;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Wexample\SymfonyUser\Service\TotpSecretCipherService;
use Wexample\SymfonyUser\Service\TotpService;
use Wexample\SymfonyUser\Tests\Fixtures\App\Entity\User;
use Wexample\SymfonyUser\Tests\Traits\DatabaseTestTrait;

class TotpTest extends WebTestCase
{
    use DatabaseTestTrait;

    private KernelBrowser $client;

    private EntityManagerInterface $entityManager;

    private User $user;

    private ?Request $serviceRequest = null;

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

    public function testTheAppIsOnlyTurnedOnByOneOfItsCodes(): void
    {
        $this->withTotpService(function (TotpService $service): void {
            $secret = $service->getPendingSecret();
            $wrong = $this->now($secret) === '000000' ? '111111' : '000000';

            $this->assertStringStartsWith('data:image/svg+xml;base64,', $service->getPendingQrCodeDataUri($this->user));
            $this->assertFalse($service->confirm($this->user, $wrong));
            $this->assertFalse($this->user->isTotpAuthenticationEnabled());

            $this->assertTrue($service->confirm($this->user, $this->now($secret)));
            $this->assertTrue($this->user->isTotpAuthenticationEnabled());
            $this->assertSame(TotpService::BACKUP_CODES_COUNT, $this->user->countBackupCodes());
            $this->assertCount(TotpService::BACKUP_CODES_COUNT, $service->pullNewBackupCodes());
            $this->assertNull($service->pullNewBackupCodes());
        });
    }

    public function testTheSecretIsStoredEncrypted(): void
    {
        $secret = $this->enable()['secret'];

        $stored = $this->entityManager->getConnection()
            ->fetchOne('SELECT totp_secret_encrypted FROM "user"');

        $this->assertStringNotContainsString($secret, $stored);
        $this->assertSame($secret, self::getContainer()->get(TotpSecretCipherService::class)->decrypt($stored));

        $this->entityManager->clear();
        $this->assertTrue($this->entityManager->find(User::class, $this->user->getId())->isTotpAuthenticationEnabled());
    }

    public function testTheAppReplacesTheEmailCode(): void
    {
        $secret = $this->enable()['secret'];

        $this->assertSame('/login/2fa', $this->login()['action']['url']);
        $this->assertEmailCount(0);

        $this->assertFalse($this->submitCode('abcdef')['ok']);
        $this->assertTrue($this->submitCode($this->now($secret))['ok']);

        $this->client->request('GET', '/protected');
        $this->assertResponseIsSuccessful();
    }

    public function testABackupCodeWorksOnce(): void
    {
        $code = $this->enable()['codes'][0];

        $this->login();
        $this->assertTrue($this->submitCode($code)['ok']);
        $this->client->request('GET', '/logout');

        $this->login();
        $this->assertFalse($this->submitCode($code)['ok']);
    }

    public function testTurningTheAppOffBringsTheEmailCodeBack(): void
    {
        $this->enable();
        $this->withTotpService(
            fn (TotpService $service) => $service->disable($this->entityManager->find(User::class, $this->user->getId()))
        );

        $this->login();
        $this->assertEmailCount(1);
    }

    /**
     * @return array{secret: string, codes: list<string>}
     */
    private function enable(): array
    {
        return $this->withTotpService(function (TotpService $service): array {
            $secret = $service->getPendingSecret();
            $this->assertTrue($service->confirm($this->user, $this->now($secret)));

            return ['secret' => $secret, 'codes' => $service->pullNewBackupCodes()];
        });
    }

    /**
     * The service reads its pending secret in session: outside the client, it
     * is given a request of its own, taken off the stack afterwards — left
     * there, it would pass for the main request of the client's next ones.
     */
    private function withTotpService(callable $callback): mixed
    {
        if (! $this->serviceRequest) {
            $this->serviceRequest = new Request();
            $this->serviceRequest->setSession(new Session(new MockArraySessionStorage()));
        }

        $requestStack = self::getContainer()->get('request_stack');
        $requestStack->push($this->serviceRequest);

        try {
            return $callback(self::getContainer()->get(TotpService::class));
        } finally {
            $requestStack->pop();
        }
    }

    private function now(string $secret): string
    {
        return TOTP::createFromSecret($secret)->now();
    }

    private function login(): array
    {
        return $this->post('form-login_form', 'login_form', ['identifier' => 'jane', 'password' => 'secret']);
    }

    private function submitCode(string $code): array
    {
        return $this->post('form-two_factor_code_form', 'two_factor_code_form', ['code' => $code]);
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
