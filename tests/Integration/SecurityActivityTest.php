<?php

namespace Wexample\SymfonyUser\Tests\Integration;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Wexample\SymfonyActivity\Class\ActivityEntry;
use Wexample\SymfonyActivity\Class\ActivitySubject;
use Wexample\SymfonyUser\Tests\Fixtures\App\ActivityAppKernel;
use Wexample\SymfonyUser\Tests\Fixtures\App\Entity\User;
use Wexample\SymfonyUser\Tests\Traits\DatabaseTestTrait;

/**
 * symfony-activity installed, `security` enabled: the security facts of an
 * account become its history — the login history is that category of it.
 */
class SecurityActivityTest extends WebTestCase
{
    use DatabaseTestTrait;

    private KernelBrowser $client;

    private EntityManagerInterface $entityManager;

    protected static function getKernelClass(): string
    {
        return ActivityAppKernel::class;
    }

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

    public function testTheSignInsOfAnAccountAreItsHistory(): void
    {
        $this->login('wrong');
        $this->login('secret');

        $history = $this->history();

        $this->assertSame(
            ['security.login.succeeded', 'security.login.failed'],
            array_map(static fn (ActivityEntry $entry) => $entry->getType(), $history)
        );
        $this->assertSame('password', $history[0]->getContext()['method']);
        $this->assertSame('bad_password', $history[1]->getContext()['cause']);
        $this->assertArrayHasKey('ip', $history[0]->getContext());
    }

    public function testAnAddressNoAccountHoldsHasNoHistory(): void
    {
        $this->post('nobody@example.com', 'secret');

        $this->entityManager->clear();
        $this->assertSame([], self::getContainer()->get('test.activity_repository')->findAll());
    }

    /**
     * @return list<ActivityEntry>
     */
    private function history(): array
    {
        $this->entityManager->clear();
        $user = $this->entityManager->getRepository(User::class)->findOneBy(['username' => 'jane']);

        return self::getContainer()->get('test.activity_repository')
            ->findRecent(new ActivitySubject(User::class, (string) $user->getId()), 10);
    }

    private function login(string $password): void
    {
        $this->post('jane', $password);
    }

    private function post(string $identifier, string $password): void
    {
        $this->client->request(
            'POST',
            '/_forms/submit/form-login_form',
            ['login_form' => ['identifier' => $identifier, 'password' => $password, '_token' => 'csrf-token']],
            [],
            ['HTTP_ACCEPT' => 'application/json', 'HTTP_ORIGIN' => 'http://localhost']
        );
    }
}
