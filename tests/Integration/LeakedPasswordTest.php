<?php

namespace Wexample\SymfonyUser\Tests\Integration;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Wexample\SymfonyUser\Tests\Fixtures\App\Entity\User;
use Wexample\SymfonyUser\Tests\Fixtures\App\LeakedPasswordAppKernel;
use Wexample\SymfonyUser\Tests\Traits\DatabaseTestTrait;

/**
 * `password.refuse_leaked: true`. Both passwords below are long and strong
 * enough for the other constraints, so only the breach range tells them
 * apart: the first one is in the mocked answer, the second is not.
 */
class LeakedPasswordTest extends WebTestCase
{
    use DatabaseTestTrait;

    private const string LEAKED_PASSWORD = 'Tr0mb0ne-Caravan-82';

    private const string SAFE_PASSWORD = 'Zz-Quarry-Lantern-41';

    private KernelBrowser $client;

    private EntityManagerInterface $entityManager;

    protected static function getKernelClass(): string
    {
        return LeakedPasswordAppKernel::class;
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

        $this->assertTrue($this->post('login_form', [
            'identifier' => 'jane',
            'password' => 'secret',
        ])['ok']);
    }

    public function testAPasswordFoundInTheBreachRangeIsRefused(): void
    {
        $this->assertFalse($this->changePassword(self::LEAKED_PASSWORD)['ok']);
    }

    public function testAPasswordAbsentFromItIsAccepted(): void
    {
        $this->assertTrue($this->changePassword(self::SAFE_PASSWORD)['ok']);
    }

    private function changePassword(string $new): ?array
    {
        return $this->post('change_password_form', [
            'current_password' => 'secret',
            'new_password' => ['first' => $new, 'second' => $new],
        ]);
    }

    private function post(string $formName, array $data): ?array
    {
        $this->client->request(
            'POST',
            '/_forms/submit/form-' . $formName,
            [$formName => $data + ['_token' => 'csrf-token']],
            [],
            ['HTTP_ACCEPT' => 'application/json', 'HTTP_ORIGIN' => 'http://localhost']
        );

        return json_decode($this->client->getResponse()->getContent(), true);
    }
}
