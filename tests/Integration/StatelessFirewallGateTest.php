<?php

namespace Wexample\SymfonyUser\Tests\Integration;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Wexample\SymfonyUser\Tests\Fixtures\App\Entity\User;
use Wexample\SymfonyUser\Tests\Fixtures\App\StrictAppKernel;
use Wexample\SymfonyUser\Tests\Traits\DatabaseTestTrait;

/**
 * An administrator must set an authenticator app up (`app_required_roles`),
 * and has not: the pages hold them on the setup page, a stateless firewall —
 * a token, a script — lets their request through.
 */
class StatelessFirewallGateTest extends WebTestCase
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

        $admin = (new User())
            ->setEmail('admin@example.com')
            ->setUsername('admin')
            ->setPassword('secret')
            ->setEnabled(true)
            ->setRoles(['ROLE_ADMIN']);
        $this->entityManager->persist($admin);
        $this->entityManager->flush();
    }

    public function testAStatelessRequestIsNotHeldByTheGates(): void
    {
        $this->client->request('GET', '/api/protected', server: [
            'PHP_AUTH_USER' => 'admin',
            'PHP_AUTH_PW' => 'secret',
            'HTTP_ACCEPT' => 'application/json',
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertSame('api protected', $this->client->getResponse()->getContent());
    }

    public function testTheSameAccountOnThePagesIsHeld(): void
    {
        $this->client->loginUser($this->entityManager->getRepository(User::class)->findOneBy(['username' => 'admin']), 'main');

        $this->client->request('GET', '/protected', server: ['HTTP_ACCEPT' => 'application/json']);

        $this->assertResponseStatusCodeSame(403);
    }
}
