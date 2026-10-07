<?php

namespace Wexample\SymfonyUser\Tests\Integration;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Wexample\SymfonyUser\EventSubscriber\SessionIdleSubscriber;
use Wexample\SymfonyUser\Tests\Fixtures\App\Entity\User;
use Wexample\SymfonyUser\Tests\Fixtures\App\IdleSessionAppKernel;
use Wexample\SymfonyUser\Tests\Traits\DatabaseTestTrait;

/**
 * `session.idle_lifetime: 900`: a session nobody asks anything of for a
 * quarter of an hour is signed out, remember-me cookie included.
 */
class IdleSessionTest extends WebTestCase
{
    use DatabaseTestTrait;

    private KernelBrowser $client;

    private EntityManagerInterface $entityManager;

    protected static function getKernelClass(): string
    {
        return IdleSessionAppKernel::class;
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

    public function testASessionLeftAloneIsSignedOut(): void
    {
        $this->login(rememberMe: true);
        $this->assertNotNull($this->client->getCookieJar()->get('REMEMBERME'));

        $this->leaveAlone(1200);

        $this->client->request('GET', '/protected');
        $this->assertResponseRedirects('/login?session=expired');

        // Nothing signs the account back in on its own.
        $this->assertNull($this->client->getCookieJar()->get('REMEMBERME'));
        $this->client->request('GET', '/protected');
        $this->assertResponseRedirects('/login');
    }

    public function testAnAjaxCallGetsThe401ItKnowsHowToFollow(): void
    {
        $this->login();
        $this->leaveAlone(1200);

        $this->client->xmlHttpRequest('GET', '/protected');
        $this->assertResponseStatusCodeSame(401);
        $this->assertSame(
            '/login?session=expired',
            json_decode($this->client->getResponse()->getContent(), true)['action']['url']
        );
    }

    public function testEachPageReadPushesTheDelayBack(): void
    {
        $this->login();
        $this->leaveAlone(600);

        $this->client->request('GET', '/protected');
        $this->assertResponseIsSuccessful();

        // Ten minutes after that one, so twenty since the sign-in.
        $this->leaveAlone(600);

        $this->client->request('GET', '/protected');
        $this->assertResponseIsSuccessful();
    }

    /**
     * A script polling under /_ is held to the delay and does not push it
     * back: a page left open is nobody reading it.
     */
    public function testAProgramRequestIsHeldToTheDelayWithoutPushingItBack(): void
    {
        $this->login();
        $this->leaveAlone(600);

        $this->client->request('GET', '/_program');
        $this->assertResponseIsSuccessful();
        $this->assertGreaterThan(300, time() - $this->lastSeen());

        $this->leaveAlone(1200);

        $this->client->request('GET', '/_program');
        $this->assertResponseRedirects('/login?session=expired');
    }

    private function lastSeen(): int
    {
        return $this->client->getRequest()->getSession()->get(SessionIdleSubscriber::SESSION_LAST_SEEN);
    }

    /**
     * Puts the last request of the session that many seconds back, which is
     * the only way to leave it alone without waiting for it.
     */
    private function leaveAlone(int $seconds): void
    {
        $session = $this->client->getRequest()->getSession();
        $session->set(SessionIdleSubscriber::SESSION_LAST_SEEN, time() - $seconds);
        $session->save();
    }

    private function login(bool $rememberMe = false): void
    {
        $this->client->request(
            'POST',
            '/_forms/submit/form-login_form',
            ['login_form' => ['identifier' => 'jane', 'password' => 'secret', '_token' => 'csrf-token']
                + ($rememberMe ? ['remember_me' => '1'] : [])],
            [],
            ['HTTP_ACCEPT' => 'application/json', 'HTTP_ORIGIN' => 'http://localhost']
        );

        $this->assertTrue(json_decode($this->client->getResponse()->getContent(), true)['ok']);
    }
}
