<?php

namespace Wexample\SymfonyUser\Tests\Integration;

use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;
use Wexample\SymfonyUser\Controller\TwoFactorSkipController;
use Wexample\SymfonyUser\Tests\Fixtures\App\Entity\User;
use Wexample\SymfonyUser\Tests\Traits\DatabaseTestTrait;

/**
 * Development alone goes on without the code: the route exists nowhere else,
 * and asks the token of the page.
 */
class TwoFactorSkipTest extends WebTestCase
{
    use DatabaseTestTrait;

    private KernelBrowser $client;

    public function testOutsideDevelopmentThereIsNoWayAround(): void
    {
        $this->boot('test');
        $this->login();

        $this->client->request('GET', '/login/2fa/skip');
        $this->assertResponseStatusCodeSame(404);

        $this->client->request('GET', '/protected');
        $this->assertResponseRedirects('/login/2fa');
    }

    public function testDevelopmentSkipsTheCode(): void
    {
        $this->boot('dev');
        $this->login();

        // Without the page's token, nothing happens.
        $this->client->request('GET', '/login/2fa/skip?_token=forged');
        $this->assertResponseRedirects('/login/2fa');

        $this->client->request('GET', '/login/2fa/skip?_token=' . $this->getToken());
        $this->assertResponseRedirects();

        $this->client->request('GET', '/protected');
        $this->assertResponseIsSuccessful();
    }

    private function boot(string $environment): void
    {
        $this->client = static::createClient(['environment' => $environment]);
        $this->client->disableReboot();
        $entityManager = $this->createDatabaseSchema();
        self::getContainer()->get('cache.rate_limiter')->clear();

        $entityManager->persist(
            (new User())
                ->setEmail('jane@example.com')
                ->setUsername('jane')
                ->setPassword('secret')
                ->setEnabled(true)
        );
        $entityManager->flush();
    }

    private function login(): void
    {
        $this->client->request(
            'POST',
            '/_forms/submit/form-login_form',
            ['login_form' => ['identifier' => 'jane', 'password' => 'secret', '_token' => 'csrf-token']],
            [],
            ['HTTP_ACCEPT' => 'application/json', 'HTTP_ORIGIN' => 'http://localhost']
        );

        $this->assertSame('/login/2fa', json_decode($this->client->getResponse()->getContent(), true)['action']['url']);
    }

    /**
     * The token the page would have drawn, read in the session of the login.
     */
    private function getToken(): string
    {
        $request = new Request();
        $request->setSession($this->client->getRequest()->getSession());
        $requestStack = self::getContainer()->get('request_stack');
        $requestStack->push($request);
        $token = self::getContainer()->get('security.csrf.token_manager')->getToken(TwoFactorSkipController::CSRF_TOKEN_ID)->getValue();
        $requestStack->pop();
        $request->getSession()->save();

        return $token;
    }
}
