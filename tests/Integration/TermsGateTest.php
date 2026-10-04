<?php

namespace Wexample\SymfonyUser\Tests\Integration;

use Doctrine\ORM\EntityManagerInterface;
use LogicException;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Wexample\SymfonyUser\Entity\TermsAcceptance;
use Wexample\SymfonyUser\Repository\TermsAcceptanceRepository;
use Wexample\SymfonyUser\Service\FormProcessor\TermsAcceptFormProcessor;
use Wexample\SymfonyUser\Service\MagicLinkService;
use Wexample\SymfonyUser\Tests\Fixtures\App\Entity\User;
use Wexample\SymfonyUser\Tests\Fixtures\App\TermsAppKernel;
use Wexample\SymfonyUser\Tests\Traits\DatabaseTestTrait;

/**
 * Terms of use `v2` in force: nothing is reached before they are accepted.
 */
class TermsGateTest extends WebTestCase
{
    use DatabaseTestTrait;

    private KernelBrowser $client;

    private EntityManagerInterface $entityManager;

    protected static function getKernelClass(): string
    {
        return TermsAppKernel::class;
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

    public function testNothingIsReachedBeforeTheTermsAreAccepted(): void
    {
        $this->login();

        $this->client->request('GET', '/protected');
        $this->assertResponseRedirects('/account/terms');

        $this->client->xmlHttpRequest('GET', '/protected');
        $this->assertResponseStatusCodeSame(403);

        // Their text stays readable.
        $this->client->request('GET', '/terms-text');
        $this->assertResponseIsSuccessful();
    }

    public function testAcceptingRecordsTheProofAndOpensTheWay(): void
    {
        $this->login();

        $this->assertTrue($this->accept('v2')['ok']);

        $this->client->request('GET', '/protected');
        $this->assertResponseIsSuccessful();

        [$acceptance] = $this->findHistory();
        $this->assertSame('v2', $acceptance->getVersion());
        $this->assertSame('UTC', $acceptance->getAcceptedAt()->getTimezone()->getName());
        $this->assertNotEmpty($acceptance->getIp());
    }

    public function testNothingIsAcceptedUnticked(): void
    {
        $this->login();

        $this->assertFalse($this->accept('v2', false)['ok']);
        $this->assertSame([], $this->findHistory());
    }

    public function testANewVersionAsksAgainAndKeepsTheHistory(): void
    {
        // Accepted `v1` before `v2` was published.
        $this->entityManager->persist(new TermsAcceptance($this->findJane(), 'v1', '127.0.0.1'));
        $this->entityManager->flush();

        $this->login();
        $this->client->request('GET', '/protected');
        $this->assertResponseRedirects('/account/terms');

        $this->accept('v2');

        $this->assertSame(['v1', 'v2'], array_map(static fn (TermsAcceptance $acceptance) => $acceptance->getVersion(), $this->findHistory()));
    }

    public function testAnotherVersionThanTheOneInForceIsRefused(): void
    {
        $this->login();

        $payload = $this->accept('v1');

        $this->assertSame([TermsAcceptFormProcessor::ERROR_OUTDATED], $payload['form']['errors']['form']);
        $this->assertSame([], $this->findHistory());
        $this->client->request('GET', '/protected');
        $this->assertResponseRedirects('/account/terms');
    }

    public function testTheGateWaitsForTheSecondFactor(): void
    {
        $jane = self::getContainer()->get('doctrine')->getManager()->find(User::class, $this->findJane()->getId());
        $jane->setEmailTwoFactorEnabled(true);
        self::getContainer()->get('doctrine')->getManager()->flush();

        $this->login();

        // Waiting for the code, a public page stays what it is for anyone.
        $this->client->request('GET', '/public');
        $this->assertResponseIsSuccessful();

        $this->client->request('GET', '/protected');
        $this->assertResponseRedirects('/login/2fa');
    }

    public function testAMagicLinkMeetsTheGateToo(): void
    {
        $link = self::getContainer()->get(MagicLinkService::class)->createLink($this->findJane());

        $this->client->request('GET', $link->getUrl());
        $this->client->request('GET', '/protected');

        $this->assertResponseRedirects('/account/terms');
    }

    public function testAnAdministratorImpersonatingCannotAcceptInTheUsersPlace(): void
    {
        $admin = (new User())
            ->setEmail('admin@example.com')
            ->setUsername('admin')
            ->setPassword('secret')
            ->setEnabled(true)
            ->setEmailTwoFactorEnabled(false)
            ->setRoles(['ROLE_ALLOWED_TO_SWITCH']);
        $this->entityManager->persist($admin);
        $this->entityManager->persist(new TermsAcceptance($admin, 'v2'));
        $this->entityManager->flush();

        $this->post('form-login_form', 'login_form', ['identifier' => 'admin', 'password' => 'secret']);
        // Through the impersonation form: a bare switch link is refused.
        $switch = $this->post('form-impersonate_form', 'impersonate_form', ['account' => 'jane@example.com']);
        $this->client->request('GET', $switch['action']['url']);
        $this->client->request('GET', '/protected');
        $this->assertResponseIsSuccessful();

        $payload = $this->accept('v2');
        $this->assertSame([TermsAcceptFormProcessor::ERROR_IMPERSONATION], $payload['form']['errors']['form']);
        $this->assertSame([], $this->findHistory());
    }

    public function testAnAcceptanceIsNeverChangedNorRemoved(): void
    {
        $acceptance = new TermsAcceptance($this->findJane(), 'v1');
        $this->entityManager->persist($acceptance);
        $this->entityManager->flush();

        $this->expectException(LogicException::class);
        $this->entityManager->remove($acceptance);
        $this->entityManager->flush();
    }

    private function findJane(): User
    {
        return self::getContainer()->get('doctrine')->getManager()
            ->getRepository(User::class)->findOneBy(['username' => 'jane']);
    }

    /**
     * @return list<TermsAcceptance>
     */
    private function findHistory(): array
    {
        self::getContainer()->get('doctrine')->getManager()->clear();

        return self::getContainer()->get(TermsAcceptanceRepository::class)->findHistory($this->findJane());
    }

    private function login(): void
    {
        $this->post('form-login_form', 'login_form', ['identifier' => 'jane', 'password' => 'secret']);
    }

    private function accept(string $version, bool $ticked = true): array
    {
        return $this->post('form-terms_accept_form', 'terms_accept_form', array_filter([
            'version' => $version,
            'accepted' => $ticked ? '1' : null,
        ]));
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
}
