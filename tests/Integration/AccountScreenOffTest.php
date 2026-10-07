<?php

namespace Wexample\SymfonyUser\Tests\Integration;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Wexample\SymfonyUser\Tests\Fixtures\App\Entity\User;
use Wexample\SymfonyUser\Tests\Traits\DatabaseTestTrait;

/**
 * No `administration.page_role`, which is the default: the screens are not
 * there at all, for anyone — the way the impersonation page is absent
 * without `switch_user`.
 */
class AccountScreenOffTest extends WebTestCase
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
                ->setEmail('admin@example.com')
                ->setUsername('admin')
                ->setPassword('secret')
                ->setRoles(['ROLE_ADMIN'])
                ->setEnabled(true)
                ->setEmailTwoFactorEnabled(false)
        );
        $this->entityManager->flush();
    }

    public function testTheScreensAndTheirActionsAreNotFound(): void
    {
        $this->client->request('POST', '/_forms/submit/form-login_form', [
            'login_form' => ['identifier' => 'admin', 'password' => 'secret', '_token' => 'csrf-token'],
        ], [], ['HTTP_ACCEPT' => 'application/json', 'HTTP_ORIGIN' => 'http://localhost']);

        $id = (string) $this->entityManager->getRepository(User::class)->findOneBy(['username' => 'admin'])->getId();

        $this->client->request('GET', '/accounts');
        $this->assertResponseStatusCodeSame(404);

        $this->client->request('GET', '/accounts/' . $id);
        $this->assertResponseStatusCodeSame(404);

        $this->client->request('POST', '/accounts/' . $id . '/deactivate', ['_token' => 'csrf-token'], [], [
            'HTTP_ORIGIN' => 'http://localhost',
        ]);
        $this->assertResponseStatusCodeSame(404);
    }

    /**
     * Nor do the forms behind them write anything: they are reachable by
     * their own URL, and answer the same refusal.
     */
    public function testTheFormsBehindThemAreRefused(): void
    {
        $this->client->request('POST', '/_forms/submit/form-account_create_form', [
            'account_create_form' => ['email' => 'new@example.com', '_token' => 'csrf-token'],
        ], [], ['HTTP_ACCEPT' => 'application/json', 'HTTP_ORIGIN' => 'http://localhost']);

        $this->assertResponseStatusCodeSame(403);
        $this->assertNull($this->entityManager->getRepository(User::class)->findOneBy(['email' => 'new@example.com']));
    }
}
