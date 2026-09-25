<?php

namespace Wexample\SymfonyUser\Tests\Integration;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Mime\Email;
use Wexample\SymfonyUser\Service\FormProcessor\SetPasswordFormProcessor;
use Wexample\SymfonyUser\Tests\Fixtures\App\Entity\User;
use Wexample\SymfonyUser\Tests\Traits\DatabaseTestTrait;

/**
 * The token mode, default of the fixture kernel.
 */
class PasswordResetTest extends WebTestCase
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
                ->setEmailTwoFactorEnabled(false)
        );
        $this->entityManager->flush();
    }

    public function testKnownAndUnknownAccountsGetTheSameAnswer(): void
    {
        $known = $this->requestReset('jane');
        $this->assertEmailCount(1);
        $unknown = $this->requestReset('john');

        $this->assertTrue($known['ok']);
        $this->assertSame($known, $unknown);
    }

    public function testTheLinkLetsTheUserChooseANewPasswordOnce(): void
    {
        $this->requestReset('jane');
        $link = $this->getResetLink();

        $this->client->request('GET', $link);
        $this->assertResponseRedirects('/password/new');

        $payload = $this->setPassword(self::STRONG_PASSWORD);
        $this->assertTrue($payload['ok']);
        $this->assertSame('redirect', $payload['action']['type']);

        // Signed in by the reset.
        $this->client->request('GET', '/protected');
        $this->assertResponseIsSuccessful();

        // The new password signed the link out.
        $this->client->request('GET', '/logout');
        $this->client->request('GET', $link);
        $this->assertResponseRedirects('/password/forgot?link=invalid');
        $this->client->request('GET', '/password/new');
        $this->assertResponseRedirects('/password/forgot');

        $this->assertTrue($this->login(self::STRONG_PASSWORD));
    }

    public function testATamperedLinkGrantsNothing(): void
    {
        $this->requestReset('jane');
        $link = preg_replace('/expires=(\d+)/', 'expires=9999999999', $this->getResetLink());

        $this->client->request('GET', $link);
        $this->assertResponseRedirects('/password/forgot?link=invalid');
        $this->assertSame(
            [SetPasswordFormProcessor::ERROR_NO_PROOF],
            $this->setPassword(self::STRONG_PASSWORD)['form']['errors']['form']
        );
        $this->assertTrue($this->login('secret'));
    }

    private function requestReset(string $identifier): array
    {
        return $this->post('form-password_reset_request_form', 'password_reset_request_form', [
            'identifier' => $identifier,
        ]);
    }

    private function setPassword(string $password): array
    {
        return $this->post('form-set_password_form', 'set_password_form', [
            'new_password' => ['first' => $password, 'second' => $password],
        ]);
    }

    private function login(string $password): bool
    {
        return $this->post('form-login_form', 'login_form', [
            'identifier' => 'jane',
            'password' => $password,
        ])['ok'];
    }

    private function getResetLink(): string
    {
        $email = $this->getMailerMessage();
        $this->assertInstanceOf(Email::class, $email);
        preg_match('#href="([^"]+)"#', (string) $email->getHtmlBody(), $matches);

        return html_entity_decode($matches[1]);
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
