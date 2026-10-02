<?php

namespace Wexample\SymfonyUser\Tests\Integration;

use Doctrine\ORM\EntityManagerInterface;
use OTPHP\TOTP;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Wexample\SymfonyUser\Service\TotpService;
use Wexample\SymfonyUser\Tests\Fixtures\App\Entity\User;
use Wexample\SymfonyUser\Tests\Traits\DatabaseTestTrait;

/**
 * Where the authenticator page sends a visitor: into the setup tunnel while
 * there is no app, nowhere once there is one. The steps themselves draw the
 * design system's stepper, which this fixture's bare layout cannot: they are
 * walked in an application (Sapiens, TotpSetupTunnelTest).
 */
class TotpSetupTunnelTest extends WebTestCase
{
    use DatabaseTestTrait;

    private KernelBrowser $client;

    private EntityManagerInterface $entityManager;

    private User $user;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->client->disableReboot();
        $this->entityManager = $this->createDatabaseSchema();

        $this->user = (new User())
            ->setEmail('jane@example.com')
            ->setUsername('jane')
            ->setPassword('secret')
            ->setEnabled(true);
        $this->entityManager->persist($this->user);
        $this->entityManager->flush();

        $this->client->loginUser($this->user, 'main');
    }

    public function testWithoutAnAppThePageOpensTheTunnelAtItsFirstStep(): void
    {
        $this->client->request('GET', '/account/authenticator');
        $this->assertResponseRedirects('/account/authenticator/setup');

        $this->client->request('GET', '/account/authenticator/setup');
        $this->assertResponseRedirects('/account/authenticator/setup/scan');
    }

    public function testWithAnAppThereIsNothingToScan(): void
    {
        // Turned on as the tunnel's verify step does, through the service, with
        // a session of its own for the pending secret.
        $request = new Request();
        $request->setSession(new Session(new MockArraySessionStorage()));
        $requestStack = self::getContainer()->get('request_stack');
        $requestStack->push($request);
        $service = self::getContainer()->get(TotpService::class);
        $this->assertTrue($service->confirm($this->user, TOTP::createFromSecret($service->getPendingSecret())->now()));
        $requestStack->pop();

        $this->client->request('GET', '/account/authenticator/setup/scan');
        $this->assertResponseRedirects('/account/authenticator');
    }
}
