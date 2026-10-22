<?php

namespace Wexample\SymfonyUser\Tests\Integration;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Wexample\SymfonyUser\Tests\Fixtures\App\Entity\User;
use Wexample\SymfonyUser\Tests\Fixtures\App\ProfileAppKernel;
use Wexample\SymfonyUser\Tests\Traits\DatabaseTestTrait;

/**
 * The account changing what is its own to change: its name, its language.
 * Two languages are enabled, so the field is there to fill.
 */
class ProfileTest extends WebTestCase
{
    use DatabaseTestTrait;

    private KernelBrowser $client;

    private EntityManagerInterface $entityManager;

    protected static function getKernelClass(): string
    {
        return ProfileAppKernel::class;
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

    public function testAnAnonymousVisitorChangesNothing(): void
    {
        $this->save(['first_name' => 'Jane']);

        $this->assertResponseStatusCodeSame(403);
        $this->assertNull($this->find()->getFirstName());
    }

    public function testTheNameAndTheLanguageAreSaved(): void
    {
        $this->login();

        $this->assertTrue($this->save([
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'locale' => 'fr',
        ])['ok']);

        $user = $this->find();
        $this->assertSame('Jane Doe', $user->getDisplayName());
        $this->assertSame('JD', $user->getInitials());
        $this->assertSame('fr', $user->getLocale());
    }

    /**
     * No language chosen is a language to answer for: the mails of the
     * account go out in the default one.
     */
    public function testTheLanguageCanBeGivenBack(): void
    {
        $this->login();
        $this->save(['locale' => 'fr']);

        $this->assertTrue($this->save(['first_name' => 'Jane'])['ok']);
        $this->assertNull($this->find()->getLocale());
    }

    public function testALanguageTheApplicationDoesNotSpeakIsRefused(): void
    {
        $this->login();

        $this->assertFalse($this->save(['locale' => 'de'])['ok']);
        $this->assertNull($this->find()->getLocale());
    }

    public function testANameLongerThanTheColumnIsRefused(): void
    {
        $this->login();

        $this->assertFalse($this->save(['first_name' => str_repeat('a', 101)])['ok']);
        $this->assertNull($this->find()->getFirstName());
    }

    private function find(): User
    {
        $this->entityManager->clear();

        return $this->entityManager->getRepository(User::class)->findOneBy(['username' => 'jane']);
    }

    private function login(): void
    {
        $this->assertTrue($this->post('login_form', [
            'identifier' => 'jane',
            'password' => 'secret',
        ])['ok']);
    }

    private function save(array $data): ?array
    {
        return $this->post('profile_form', $data);
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
