<?php

namespace Wexample\SymfonyUser\Tests\Integration;

use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;
use Wexample\SymfonyUser\Controller\TotpSetupSkipController;
use Wexample\SymfonyUser\Tests\Fixtures\App\Entity\User;
use Wexample\SymfonyUser\Tests\Fixtures\App\StrictAppKernel;
use Wexample\SymfonyUser\Tests\Traits\DatabaseTestTrait;

/**
 * `app_required_roles: [ROLE_ADMIN]`: development alone lets an administrator
 * go on without the app, for the session; the route exists nowhere else, and
 * asks the token of the page.
 */
class TotpSetupSkipTest extends WebTestCase
{
    use DatabaseTestTrait;

    private KernelBrowser $client;

    protected static function getKernelClass(): string
    {
        return StrictAppKernel::class;
    }

    public function testOutsideDevelopmentTheGateHolds(): void
    {
        $this->boot('test');
        $this->signIn();

        $this->client->request('GET', '/account/authenticator/setup/skip');
        $this->assertResponseStatusCodeSame(404);

        $this->client->request('GET', '/protected');
        $this->assertResponseRedirects('/account/authenticator');
    }

    public function testDevelopmentGoesOnWithoutTheApp(): void
    {
        $this->boot('dev');
        $this->signIn();

        $this->client->request('GET', '/protected');
        $this->assertResponseRedirects('/account/authenticator');

        // Without the page's token, nothing happens.
        $this->client->request('GET', '/account/authenticator/setup/skip?_token=forged');
        $this->assertResponseRedirects('/account/authenticator');
        $this->client->request('GET', '/protected');
        $this->assertResponseRedirects('/account/authenticator');

        $this->client->request('GET', '/account/authenticator/setup/skip?_token=' . $this->getToken());
        $this->assertResponseRedirects();

        $this->client->request('GET', '/protected');
        $this->assertResponseIsSuccessful();

        // The session alone: signed in again, the gate holds again.
        $this->client->request('GET', '/logout');
        $this->signIn();
        $this->client->request('GET', '/protected');
        $this->assertResponseRedirects('/account/authenticator');
    }

    private function boot(string $environment): void
    {
        $this->client = static::createClient(['environment' => $environment]);
        $this->client->disableReboot();
        $entityManager = $this->createDatabaseSchema();
        self::getContainer()->get('cache.rate_limiter')->clear();

        $entityManager->persist(
            (new User())
                ->setEmail('admin@example.com')
                ->setUsername('admin')
                ->setPassword('secret')
                ->setRoles(['ROLE_ADMIN'])
                ->setEnabled(true)
        );
        $entityManager->flush();
    }

    /**
     * The password, then the email code: in, and held by the gate.
     */
    private function signIn(): void
    {
        $this->post('form-login_form', 'login_form', ['identifier' => 'admin', 'password' => 'secret']);
        preg_match('#<strong>(\d{6})</strong>#', (string) $this->getMailerMessage()->getHtmlBody(), $matches);
        $this->assertTrue($this->post('form-two_factor_code_form', 'two_factor_code_form', ['code' => $matches[1]])['ok']);
    }

    private function post(string $name, string $formName, array $data): array
    {
        $this->client->request(
            'POST',
            '/_forms/submit/' . $name,
            [$formName => $data + ['_token' => 'csrf-token']],
            [],
            ['HTTP_ACCEPT' => 'application/json', 'HTTP_ORIGIN' => 'http://localhost']
        );

        return json_decode($this->client->getResponse()->getContent(), true);
    }

    /**
     * The token the page would have drawn, read in the session of the visitor.
     */
    private function getToken(): string
    {
        $request = new Request();
        $request->setSession($this->client->getRequest()->getSession());
        $requestStack = self::getContainer()->get('request_stack');
        $requestStack->push($request);
        $token = self::getContainer()->get('security.csrf.token_manager')->getToken(TotpSetupSkipController::CSRF_TOKEN_ID)->getValue();
        $requestStack->pop();
        $request->getSession()->save();

        return $token;
    }
}
