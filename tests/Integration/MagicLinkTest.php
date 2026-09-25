<?php

namespace Wexample\SymfonyUser\Tests\Integration;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Mime\Email;
use Wexample\SymfonyUser\Service\MagicLinkService;
use Wexample\SymfonyUser\Tests\Fixtures\App\Entity\User;
use Wexample\SymfonyUser\Tests\Traits\DatabaseTestTrait;

class MagicLinkTest extends WebTestCase
{
    use DatabaseTestTrait;

    private const string REQUEST_PATH = '/_forms/submit/form-magic_link_request_form';

    private KernelBrowser $client;

    private EntityManagerInterface $entityManager;

    private User $user;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->client->disableReboot();
        $this->entityManager = $this->createDatabaseSchema();
        // A refused link counts as a failed login for the throttling.
        self::getContainer()->get('cache.rate_limiter')->clear();

        $this->user = $this->createUser('jane');
        $this->createUser('locked')->setLocked(true);
        $this->entityManager->flush();
    }

    public function testKnownAndUnknownAccountsGetTheSameAnswer(): void
    {
        $known = $this->requestLink('jane');
        $this->assertEmailCount(1);
        $this->assertStringContainsString('/login/link?', $this->getLastLinkUrl());

        $unknown = $this->requestLink('john');
        $locked = $this->requestLink('locked');

        $this->assertTrue($known['ok']);
        $this->assertSame($known, $unknown);
        $this->assertSame($known, $locked);
        $this->assertStringEndsWith('::success.message', $known['notification']['message']);
    }

    public function testALinkSignsInOnce(): void
    {
        $url = $this->createLinkUrl();

        $this->client->request('GET', $url);
        $this->assertResponseRedirects('/');
        $this->assertLoggedIn();

        $this->client->request('GET', '/logout');
        $this->client->request('GET', $url);
        $this->assertResponseRedirects('/login');
        $this->assertNotLoggedIn();
    }

    public function testAnExpiredLinkIsRefused(): void
    {
        $url = $this->getLinkService()->createLink($this->user, null, -1)->getUrl();

        $this->client->request('GET', $url);
        $this->assertResponseRedirects('/login');
        $this->assertNotLoggedIn();
    }

    public function testAPasswordChangeKillsTheLinksSentBefore(): void
    {
        $url = $this->createLinkUrl();
        $this->user->setPassword('changed');
        $this->entityManager->flush();

        $this->client->request('GET', $url);
        $this->assertResponseRedirects('/login');
        $this->assertNotLoggedIn();
    }

    public function testALinkLandsOnItsTargetPath(): void
    {
        $this->client->request('GET', $this->createLinkUrl('/protected'));

        $this->assertResponseRedirects('http://localhost/protected');
    }

    public function testATargetPathOutsideTheSiteIsRejected(): void
    {
        $this->expectException(\LogicException::class);
        $this->createLinkUrl('//evil.example/');
    }

    private function createUser(string $username): User
    {
        $user = (new User())
            ->setEmail($username . '@example.com')
            ->setUsername($username)
            ->setPassword('secret')
            ->setEnabled(true);

        $this->entityManager->persist($user);

        return $user;
    }

    private function requestLink(string $identifier): array
    {
        $this->client->request(
            'POST',
            self::REQUEST_PATH,
            ['magic_link_request_form' => ['identifier' => $identifier, '_token' => 'csrf-token']],
            [],
            ['HTTP_ACCEPT' => 'application/json', 'HTTP_ORIGIN' => 'http://localhost']
        );

        return json_decode($this->client->getResponse()->getContent(), true);
    }

    private function getLastLinkUrl(): string
    {
        $email = $this->getMailerMessage();
        $this->assertInstanceOf(Email::class, $email);
        preg_match('#href="([^"]+)"#', (string) $email->getHtmlBody(), $matches);

        return html_entity_decode($matches[1]);
    }

    private function getLinkService(): MagicLinkService
    {
        return self::getContainer()->get(MagicLinkService::class);
    }

    private function createLinkUrl(?string $targetPath = null): string
    {
        return $this->getLinkService()->createLink($this->user, $targetPath)->getUrl();
    }

    private function assertLoggedIn(): void
    {
        $this->client->request('GET', '/protected');
        $this->assertResponseIsSuccessful();
    }

    private function assertNotLoggedIn(): void
    {
        $this->client->request('GET', '/protected');
        $this->assertResponseRedirects('/login');
    }
}
