<?php

namespace Wexample\SymfonyUser\Tests\Integration;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Wexample\SymfonyUser\Controller\AccountActionController;
use Wexample\SymfonyUser\Service\FormProcessor\AccountCreateFormProcessor;
use Wexample\SymfonyUser\Tests\Fixtures\App\AdministrationAppKernel;
use Wexample\SymfonyUser\Tests\Fixtures\App\Entity\User;
use Wexample\SymfonyUser\Tests\Traits\DatabaseTestTrait;

/**
 * The administration screens, behind `administration.page_role:
 * ROLE_MANAGER`. A manager administers ROLE_MANAGER, ROLE_SUPPORT and
 * ROLE_CUSTOMER — the roles it reaches —, and nothing above.
 */
class AccountScreenTest extends WebTestCase
{
    use DatabaseTestTrait;

    private KernelBrowser $client;

    private EntityManagerInterface $entityManager;

    protected static function getKernelClass(): string
    {
        return AdministrationAppKernel::class;
    }

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->client->disableReboot();
        $this->entityManager = $this->createDatabaseSchema();
        self::getContainer()->get('cache.rate_limiter')->clear();

        $this->entityManager->persist($this->createUser('manager', ['ROLE_MANAGER']));
        $this->entityManager->persist($this->createUser('other-manager', ['ROLE_MANAGER']));
        $this->entityManager->persist($this->createUser('customer', ['ROLE_CUSTOMER']));
        $this->entityManager->persist($this->createUser('nobody'));
        $this->entityManager->flush();
    }

    public function testAnAccountWithoutTheRoleReachesNothing(): void
    {
        $this->login('nobody');

        $this->client->request('GET', '/accounts');
        $this->assertResponseStatusCodeSame(403);

        $this->client->request('GET', '/accounts/' . $this->id('customer'));
        $this->assertResponseStatusCodeSame(403);

        $this->act('customer', 'deactivate');
        $this->assertResponseStatusCodeSame(403);
        $this->assertTrue($this->find('customer')->isEnabled());
    }

    public function testAnIdNamingNoAccountIsNotFound(): void
    {
        $this->login('manager');

        foreach (['/accounts/not-a-uuid', '/accounts/0199a1b2-c3d4-7000-8000-000000000000'] as $url) {
            $this->client->request('GET', $url);
            $this->assertResponseStatusCodeSame(404, $url);
        }
    }

    public function testAnAccountIsOpenedWithoutAPasswordAndIsSentItsMail(): void
    {
        $this->login('manager');

        $payload = $this->create('new@example.com', ['role_support' => '1']);
        $this->assertTrue($payload['ok']);

        $account = $this->find('new@example.com', 'email');
        $this->assertNull($account->getPassword());
        $this->assertTrue($account->isEnabled());
        $this->assertContains('ROLE_SUPPORT', $account->getRoles());
        $this->assertStringContainsString('new@example.com', $this->getMailerMessage()->getTo()[0]->getAddress());
    }

    public function testAnAddressAnAccountAlreadyHoldsIsRefused(): void
    {
        $this->login('manager');

        $payload = $this->create('customer@example.com');

        $this->assertFalse($payload['ok']);
        $this->assertSame(
            [AccountCreateFormProcessor::ERROR_EMAIL_TAKEN],
            $payload['form']['errors']['fields']['account_create_form[email]']
        );
    }

    /**
     * ROLE_OWNER is above a manager: the form does not offer it, and a
     * submission carrying it is refused whole rather than quietly stripped.
     */
    public function testARoleTheActorDoesNotAdministerIsNotEvenOffered(): void
    {
        $this->login('manager');

        $this->assertFalse($this->create('owner@example.com', ['role_owner' => '1'])['ok']);
        $this->assertNull($this->findOrNull('owner@example.com'));
    }

    public function testTheRolesOfAnAccountAreSaved(): void
    {
        $this->login('manager');

        $this->assertTrue($this->setRoles('customer', ['role_support' => '1'])['ok']);

        $this->assertSame(['ROLE_SUPPORT', 'ROLE_USER'], $this->find('customer')->getRoles());
    }

    public function testAnActorDoesNotTakeTheirOwnRoleAway(): void
    {
        $this->login('manager');

        $payload = $this->setRoles('manager', []);

        $this->assertFalse($payload['ok']);
        $this->assertSame(['@form::error.refused.self_demotion'], $payload['form']['errors']['form']);
        $this->assertSame(['ROLE_MANAGER', 'ROLE_USER'], $this->find('manager')->getRoles());
    }

    public function testAnAccountIsDeactivatedAndPutBack(): void
    {
        $this->login('manager');
        $id = $this->id('customer');

        $this->act('customer', 'deactivate');
        $this->assertResponseRedirects('/accounts/' . $id . '?done=deactivate');
        $this->assertFalse($this->find('customer')->isEnabled());

        $this->act('customer', 'reactivate');
        $this->assertResponseRedirects('/accounts/' . $id . '?done=reactivate');
        $this->assertTrue($this->find('customer')->isEnabled());
    }

    public function testARefusedActionComesBackWithItsReason(): void
    {
        $this->login('manager');
        $id = $this->id('manager');

        $this->act('manager', 'deactivate');

        $this->assertResponseRedirects('/accounts/' . $id . '?refused=self_deactivation');
        $this->assertTrue($this->find('manager')->isEnabled());
    }

    /**
     * A form on another site, posting to this one with the administrator's
     * cookies: the origin of the request gives it away.
     */
    public function testAnActionPostedFromAnotherSiteDoesNothing(): void
    {
        $this->login('manager');

        $this->act('customer', 'deactivate', origin: 'http://evil.example');

        $this->assertResponseStatusCodeSame(403);
        $this->assertTrue($this->find('customer')->isEnabled());
    }

    public function testAnActionNobodyNamedIsNotFound(): void
    {
        $this->login('manager');

        $this->act('customer', 'promote');

        $this->assertResponseStatusCodeSame(404);
    }

    private function createUser(string $username, array $roles = []): User
    {
        return (new User())
            ->setEmail($username . '@example.com')
            ->setUsername($username)
            ->setPassword('secret')
            ->setRoles($roles)
            ->setEnabled(true)
            ->setEmailTwoFactorEnabled(false);
    }

    private function find(string $value, string $field = 'username'): User
    {
        return $this->findOrNull($value, $field);
    }

    private function findOrNull(string $value, string $field = 'email'): ?User
    {
        $this->entityManager->clear();

        return $this->entityManager->getRepository(User::class)->findOneBy([$field => $value]);
    }

    private function id(string $username): string
    {
        return (string) $this->find($username)->getId();
    }

    /**
     * The token id is the application's form one, which the fixture declares
     * stateless: the value is the cookie name, and the origin of the request
     * is what proves the click happened on the page.
     */
    private function act(string $username, string $action, string $origin = 'http://localhost'): void
    {
        $this->client->request(
            'POST',
            '/accounts/' . $this->id($username) . '/' . $action,
            ['_token' => 'csrf-token'],
            [],
            // The referer too: the test client fills it with the page it
            // came from, which would pass for the origin of the request.
            ['HTTP_ORIGIN' => $origin, 'HTTP_REFERER' => $origin . '/page']
        );
    }

    private function login(string $username): void
    {
        $this->assertTrue($this->post('login_form', [
            'identifier' => $username,
            'password' => 'secret',
        ])['ok']);
    }

    private function create(string $email, array $roles = []): ?array
    {
        return $this->post('account_create_form', ['email' => $email] + $roles);
    }

    private function setRoles(string $username, array $roles): ?array
    {
        return $this->post('account_roles_form', $roles, '/entity/' . $this->id($username));
    }

    private function post(string $formName, array $data, string $suffix = ''): ?array
    {
        $this->client->request(
            'POST',
            '/_forms/submit/form-' . $formName . $suffix,
            [$formName => $data + ['_token' => 'csrf-token']],
            [],
            ['HTTP_ACCEPT' => 'application/json', 'HTTP_ORIGIN' => 'http://localhost']
        );

        return json_decode($this->client->getResponse()->getContent(), true);
    }
}
