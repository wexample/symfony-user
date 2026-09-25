<?php

namespace Wexample\SymfonyUser\Tests\Integration;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Wexample\SymfonyUser\Tests\Fixtures\App\Entity\User;
use Wexample\SymfonyUser\Tests\Traits\DatabaseTestTrait;

class ChangePasswordTest extends WebTestCase
{
    use DatabaseTestTrait;

    private const string STRONG_PASSWORD = 'correct horse battery staple';

    private KernelBrowser $client;

    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->client->disableReboot();
        $this->entityManager = $this->createDatabaseSchema();
        self::getContainer()->get('cache.rate_limiter')->clear();

        $this->entityManager->persist(
            (new User())
                ->setEmail('jane@example.com')
                ->setUsername('jane')
                ->setPassword('secret')
                ->setEnabled(true)
        );
        $this->entityManager->flush();

        $this->assertTrue($this->post('form-login_form', 'login_form', [
            'identifier' => 'jane',
            'password' => 'secret',
        ])['ok']);
    }

    public function testAnonymousVisitorsCannotChangeAPassword(): void
    {
        $this->client->request('GET', '/logout');
        $this->changePassword('secret', self::STRONG_PASSWORD);

        $this->assertResponseStatusCodeSame(403);
    }

    public function testTheCurrentPasswordIsRequired(): void
    {
        $payload = $this->changePassword('wrong', self::STRONG_PASSWORD);

        $this->assertFalse($payload['ok']);
        $this->assertSame(
            ['@form::error.current_password'],
            $payload['form']['errors']['fields']['change_password_form[current_password]']
        );
    }

    public function testAWeakOrMistypedPasswordIsRefused(): void
    {
        $this->assertFalse($this->changePassword('secret', '12345678')['ok']);
        $this->assertFalse($this->changePassword('secret', self::STRONG_PASSWORD, 'another one')['ok']);
    }

    public function testThePasswordChangesAndTheSessionStays(): void
    {
        $this->assertTrue($this->changePassword('secret', self::STRONG_PASSWORD)['ok']);

        $this->client->request('GET', '/protected');
        $this->assertResponseIsSuccessful();

        $this->client->request('GET', '/logout');
        $this->assertFalse($this->post('form-login_form', 'login_form', ['identifier' => 'jane', 'password' => 'secret'])['ok']);
        $this->assertTrue($this->post('form-login_form', 'login_form', ['identifier' => 'jane', 'password' => self::STRONG_PASSWORD])['ok']);
    }

    private function changePassword(string $current, string $new, ?string $confirmation = null): ?array
    {
        return $this->post('form-change_password_form', 'change_password_form', [
            'current_password' => $current,
            'new_password' => ['first' => $new, 'second' => $confirmation ?? $new],
        ]);
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
