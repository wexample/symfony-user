<?php

namespace Wexample\SymfonyUser\Tests\Integration;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Wexample\SymfonyUser\Service\FormProcessor\SetPasswordFormProcessor;
use Wexample\SymfonyUser\Service\PasswordUpdaterService;
use Wexample\SymfonyUser\Tests\Fixtures\App\Entity\User;
use Wexample\SymfonyUser\Tests\Traits\DatabaseTestTrait;

/**
 * A password its holder did not choose: nothing is reached before it is
 * replaced, and choosing it asks for no current password — the one they just
 * typed is the one being taken away.
 */
class ForcedPasswordChangeTest extends WebTestCase
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

        $this->entityManager->persist($this->createUser('jane'));
        $this->entityManager->persist($this->createUser('admin')->setRoles(['ROLE_ADMIN']));
        $this->entityManager->flush();
    }

    public function testNothingIsReachedBeforeThePasswordIsReplaced(): void
    {
        $this->requireChange('jane');
        $this->login('jane');

        $this->client->request('GET', '/protected');
        $this->assertResponseRedirects('/password/new');

        $this->client->xmlHttpRequest('GET', '/protected');
        $this->assertResponseStatusCodeSame(403);
    }

    /**
     * Without the flag, the page and its form belong to the reset walk alone:
     * a signed-in user holding no proof sets nothing, and is sent to ask for
     * a link.
     */
    public function testASignedInUserOwingNothingSetsNoPasswordWithoutAProof(): void
    {
        $this->login('jane');

        $this->assertSame(
            [SetPasswordFormProcessor::ERROR_NO_PROOF],
            $this->setPassword(self::STRONG_PASSWORD)['form']['errors']['form']
        );

        $this->client->request('GET', '/password/new');
        $this->assertResponseRedirects('/password/forgot');
    }

    public function testChoosingOneClearsTheFlagAndOpensTheWay(): void
    {
        $this->requireChange('jane');
        $this->login('jane');

        $this->assertTrue($this->setPassword(self::STRONG_PASSWORD)['ok']);
        $this->assertFalse($this->find('jane')->isPasswordChangeRequired());

        $this->client->request('GET', '/protected');
        $this->assertResponseIsSuccessful();

        $this->client->request('GET', '/logout');
        $this->assertTrue($this->post('login_form', [
            'identifier' => 'jane',
            'password' => self::STRONG_PASSWORD,
        ])['ok']);
    }

    public function testAPasswordAnAdministratorSetsIsOwedAChange(): void
    {
        $this->login('admin');

        self::getContainer()->get(PasswordUpdaterService::class)->update(
            $this->find('jane'),
            self::STRONG_PASSWORD
        );

        $this->assertTrue($this->find('jane')->isPasswordChangeRequired());
    }

    public function testAPasswordItsHolderChoosesIsNot(): void
    {
        $this->requireChange('jane');
        $this->login('jane');

        $this->setPassword(self::STRONG_PASSWORD);

        $this->assertFalse($this->find('jane')->isPasswordChangeRequired());
    }

    private function createUser(string $username): User
    {
        return (new User())
            ->setEmail($username . '@example.com')
            ->setUsername($username)
            ->setPassword('secret')
            ->setEnabled(true)
            ->setEmailTwoFactorEnabled(false);
    }

    private function find(string $username): User
    {
        $this->entityManager->clear();

        return $this->entityManager->getRepository(User::class)->findOneBy(['username' => $username]);
    }

    private function requireChange(string $username): void
    {
        $this->find($username)->setPasswordChangeRequired(true);
        $this->entityManager->flush();
    }

    private function login(string $username): void
    {
        $this->assertTrue($this->post('login_form', [
            'identifier' => $username,
            'password' => 'secret',
        ])['ok']);
    }

    private function setPassword(string $new): ?array
    {
        return $this->post('set_password_form', [
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
