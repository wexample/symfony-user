<?php

namespace Wexample\SymfonyUser\Tests\Integration;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Wexample\SymfonyUser\Tests\Fixtures\App\Entity\User;
use Wexample\SymfonyUser\Tests\Traits\DatabaseTestTrait;

/**
 * The fixture signup tunnel: user-mail, then login when the account exists,
 * then done.
 */
class UserTunnelStepsTest extends WebTestCase
{
    use DatabaseTestTrait;

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

    public function testANewAddressCreatesOneInactiveAccount(): void
    {
        $this->client->request('GET', '/signup/user-mail');
        $this->assertStringContainsString('[[STEP:user-mail]]', $this->client->getResponse()->getContent());

        $this->submitEmail('New@Example.com');
        $this->assertResponseRedirects('/signup/done');

        $this->client->request('GET', '/signup/done');
        $this->assertStringContainsString('[[USER:new@example.com]]', $this->client->getResponse()->getContent());

        // Back, and another address: the same account, renamed.
        $this->client->request('GET', '/signup/user-mail');
        $this->submitEmail('other@example.com');
        $this->assertResponseRedirects('/signup/done');

        $users = $this->findUsers();
        $this->assertCount(2, $users);
        $this->assertArrayHasKey('other@example.com', $users);
        $this->assertFalse($users['other@example.com']->isEnabled());
    }

    public function testAnActivatedAccountSignsInThenGoesOn(): void
    {
        $this->client->request('GET', '/signup/user-mail');
        $this->submitEmail('jane@example.com');
        $this->assertResponseRedirects('/signup/login');

        $this->client->request('GET', '/signup/login');
        $this->assertStringContainsString('[[STEP:login]]', $this->client->getResponse()->getContent());
        $this->assertStringContainsString('[[IDENTIFIER:jane@example.com]]', $this->client->getResponse()->getContent());

        // The login form comes back to the step, which sends the visitor on.
        $payload = $this->login();
        $this->assertSame('/signup/login', $payload['action']['url']);

        $this->client->request('GET', '/signup/login');
        $this->assertResponseRedirects('/signup/done');

        $this->assertCount(1, $this->findUsers());
    }

    public function testASignedInVisitorSkipsTheLogin(): void
    {
        $this->client->request('GET', '/signup/user-mail');
        $this->login();

        $this->client->request('GET', '/signup/user-mail');
        $this->submitEmail('anything@example.com');
        $this->assertResponseRedirects('/signup/done');

        $this->client->request('GET', '/signup/done');
        $this->assertStringContainsString('[[USER:jane@example.com]]', $this->client->getResponse()->getContent());
        $this->assertCount(1, $this->findUsers());
    }

    private function submitEmail(string $email): void
    {
        $this->client->request(
            'POST',
            $this->client->getRequest()->getPathInfo(),
            ['user_mail_form' => ['email' => $email, '_token' => 'csrf-token']],
            [],
            ['HTTP_ORIGIN' => 'http://localhost']
        );
    }

    private function login(): array
    {
        $this->client->request(
            'POST',
            '/_forms/submit/form-login_form',
            ['login_form' => ['identifier' => 'jane', 'password' => 'secret', '_token' => 'csrf-token']],
            [],
            ['HTTP_ACCEPT' => 'application/json', 'HTTP_ORIGIN' => 'http://localhost']
        );

        return json_decode($this->client->getResponse()->getContent(), true);
    }

    /**
     * @return array<string, User>
     */
    private function findUsers(): array
    {
        $users = [];
        $entityManager = self::getContainer()->get('doctrine')->getManager();
        $entityManager->clear();

        foreach ($entityManager->getRepository(User::class)->findAll() as $user) {
            $users[$user->getEmail()] = $user;
        }

        return $users;
    }
}
