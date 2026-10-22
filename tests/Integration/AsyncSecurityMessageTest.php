<?php

namespace Wexample\SymfonyUser\Tests\Integration;

use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;
use Symfony\Component\Mime\Email;
use Wexample\SymfonyUser\Enum\SecurityMessageType;
use Wexample\SymfonyUser\Message\SendSecurityMessage;
use Wexample\SymfonyUser\Tests\Fixtures\App\AsyncMailAppKernel;
use Wexample\SymfonyUser\Tests\Fixtures\App\Entity\User;
use Wexample\SymfonyUser\Tests\Traits\DatabaseTestTrait;

/**
 * SendSecurityMessage routed to an asynchronous transport.
 */
class AsyncSecurityMessageTest extends WebTestCase
{
    use DatabaseTestTrait;

    private KernelBrowser $client;

    protected static function getKernelClass(): string
    {
        return AsyncMailAppKernel::class;
    }

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->client->disableReboot();
        $entityManager = $this->createDatabaseSchema();
        self::getContainer()->get('cache.rate_limiter')->clear();
        self::getContainer()->get('cache.app')->clear();

        $entityManager->persist(
            (new User())
                ->setEmail('jane@example.com')
                ->setUsername('jane')
                ->setPassword('secret')
                ->setEnabled(true)
                ->setEmailTwoFactorEnabled(false)
        );
        $entityManager->flush();
    }

    public function testTheRequestQueuesTheMailWithoutItsLink(): void
    {
        $this->client->request(
            'POST',
            '/_forms/submit/form-password_reset_request_form',
            ['password_reset_request_form' => ['identifier' => 'jane', '_token' => 'csrf-token']],
            [],
            ['HTTP_ACCEPT' => 'application/json', 'HTTP_ORIGIN' => 'http://localhost']
        );
        $this->assertTrue(json_decode($this->client->getResponse()->getContent(), true)['ok']);
        $this->assertEmailCount(0);

        /** @var InMemoryTransport $transport */
        $transport = self::getContainer()->get('messenger.transport.async');
        [$envelope] = $transport->getSent();
        $stored = serialize($envelope->getMessage());
        $this->assertStringNotContainsString('hash', $stored);
        $this->assertStringNotContainsString('http', $stored);
        $this->assertStringNotContainsString('jane', $stored);

        // The worker: the link is built now, and works.
        self::getContainer()->get('messenger.default_bus')->dispatch($envelope->with(new ReceivedStamp('async')));
        // Sent by the worker itself: nothing more was queued.
        $this->assertCount(1, $transport->getSent());
        $email = $this->getMailerMessage();
        $this->assertInstanceOf(Email::class, $email);
        preg_match('#href="([^"]+)"#', (string) $email->getHtmlBody(), $matches);

        $this->client->request('GET', html_entity_decode($matches[1]));
        $this->assertResponseRedirects('/password/new');
    }

    public function testTheWorkerReadsTheAccountAsItIsWhenItSends(): void
    {
        $jane = self::getContainer()->get('doctrine')->getRepository(User::class)->findOneBy(['username' => 'jane']);
        $bus = self::getContainer()->get('messenger.default_bus');

        // An activation queued, then the password chosen another way.
        $bus->dispatch(new SendSecurityMessage(User::class, (string) $jane->getId(), SecurityMessageType::ACCOUNT_ACTIVATION), [new ReceivedStamp('async')]);
        $this->assertEmailCount(0);

        // A reset queued, then the account disabled.
        $jane->setEnabled(false);
        $bus->dispatch(new SendSecurityMessage(User::class, (string) $jane->getId(), SecurityMessageType::PASSWORD_RESET), [new ReceivedStamp('async')]);
        $this->assertEmailCount(0);
    }
}
