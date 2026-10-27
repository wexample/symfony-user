<?php

namespace Wexample\SymfonyUser\Tests\Integration;

use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Wexample\SymfonyApi\Helper\ApiResponseNormHelper;
use Wexample\SymfonyUser\Tests\Fixtures\App\AdministrationAppKernel;
use Wexample\SymfonyUser\Tests\Fixtures\App\Entity\User;
use Wexample\SymfonyUser\Tests\Traits\DatabaseTestTrait;

/**
 * `GET /_api/user/list`, the accounts table's source: `page_role:
 * ROLE_MANAGER`. Every account, searched, sorted and paged, in the norm the
 * front reads — typed `user` whatever the application's class.
 */
class UserListApiTest extends WebTestCase
{
    use DatabaseTestTrait;

    private KernelBrowser $client;

    protected static function getKernelClass(): string
    {
        return AdministrationAppKernel::class;
    }

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->client->disableReboot();
        $entityManager = $this->createDatabaseSchema();
        self::getContainer()->get('cache.rate_limiter')->clear();
        self::getContainer()->get('cache.app')->clear();

        foreach (['manager' => ['ROLE_MANAGER'], 'customer' => ['ROLE_CUSTOMER'], 'nobody' => []] as $username => $roles) {
            $entityManager->persist(
                (new User())
                    ->setEmail($username . '@example.com')
                    ->setUsername($username)
                    ->setPassword('secret')
                    ->setRoles($roles)
                    ->setEnabled(true)
                    ->setEmailTwoFactorEnabled(false)
            );
        }
        $entityManager->persist(
            (new User())->setEmail('locked@example.com')->setUsername('locked')->setPassword('secret')->setEnabled(true)->setLocked(true)
        );
        $entityManager->flush();
    }

    public function testTheAccountsComeInTheNormTheFrontReads(): void
    {
        $this->login('manager');

        $data = $this->list('?sort=email');

        $this->assertSame([], ApiResponseNormHelper::problems($data));
        $this->assertSame(4, $data['data']['pagination']['total']);
        $this->assertSame(
            ['customer@example.com', 'locked@example.com', 'manager@example.com', 'nobody@example.com'],
            array_map(static fn (array $item) => $item['entity']['email'], $data['data']['items'])
        );

        // The fixture's class is Tests\Fixtures\App\Entity\User: the type stays `user`.
        $locked = $data['data']['items'][1];
        $this->assertSame('user', $locked['type']);
        $this->assertSame('locked', $locked['entity']['state']);
        $this->assertSame(['ROLE_CUSTOMER', 'ROLE_USER'], $data['data']['items'][0]['entity']['roles']);
        $this->assertArrayNotHasKey('password', $locked['entity']);
    }

    public function testASearchAndAPageNarrowTheList(): void
    {
        $this->login('manager');

        $this->assertSame(1, $this->list('?search=CUSTOM')['data']['pagination']['total']);
        $this->assertCount(2, $this->list('?length=2&page=0')['data']['items']);

        $this->client->request('GET', '/_api/user/list?sort=password', server: ['HTTP_ACCEPT' => 'application/json']);
        $this->assertResponseStatusCodeSame(400);
    }

    public function testWithoutTheRoleTheListIsRefused(): void
    {
        $this->login('nobody');

        $this->client->request('GET', '/_api/user/list', server: ['HTTP_ACCEPT' => 'application/json']);
        $this->assertResponseStatusCodeSame(403);
    }

    private function list(string $query = ''): array
    {
        $this->client->request('GET', '/_api/user/list' . $query, server: ['HTTP_ACCEPT' => 'application/json']);
        $this->assertResponseIsSuccessful();

        return json_decode($this->client->getResponse()->getContent(), true);
    }

    private function login(string $username): void
    {
        $this->client->request(
            'POST',
            '/_forms/submit/form-login_form',
            ['login_form' => ['identifier' => $username, 'password' => 'secret', '_token' => 'csrf-token']],
            [],
            ['HTTP_ACCEPT' => 'application/json', 'HTTP_ORIGIN' => 'http://localhost']
        );
        $this->assertTrue(json_decode($this->client->getResponse()->getContent(), true)['ok']);
    }
}
