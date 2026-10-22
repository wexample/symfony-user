<?php

namespace Wexample\SymfonyUser\Tests\Integration;

use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Wexample\SymfonyUser\Event\SecurityEvent;
use Wexample\SymfonyUser\Tests\Fixtures\App\Entity\User;
use Wexample\SymfonyUser\Tests\Traits\DatabaseTestTrait;

/**
 * The defaults: 3 mail requests per address and 20 per IP, an hour.
 */
class MailRequestLimitTest extends WebTestCase
{
    use DatabaseTestTrait;

    private KernelBrowser $client;

    /**
     * @var list<?string>
     */
    private array $causes = [];

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
        );
        $entityManager->flush();

        self::getContainer()->get('event_dispatcher')->addListener(
            SecurityEvent::class,
            function (SecurityEvent $event): void {
                if (in_array($event->type->value, ['password.reset_requested', 'magic_link.requested'], true)) {
                    $this->causes[] = $event->cause;
                }
            }
        );
    }

    public function testAnAddressGetsThreeMailsAnHourWhicheverTheForm(): void
    {
        $resetAnswer = $this->requestReset('jane');
        $this->assertEmailCount(1);
        $magicLinkAnswer = $this->requestMagicLink('jane@example.com');
        $this->assertEmailCount(1);
        $this->requestReset('JANE@example.com');
        $this->assertEmailCount(1);

        // The fourth: the same answer, no mail.
        $this->assertSame($magicLinkAnswer, $this->requestMagicLink('jane'));
        $this->assertEmailCount(0);
        $this->assertSame($resetAnswer, $this->requestReset('jane'));
        $this->assertEmailCount(0);
        $this->assertSame([null, null, null, 'rate_limited', 'rate_limited'], $this->causes);

        // Another address is not held by it.
        $this->assertSame($resetAnswer, $this->requestReset('john@example.com'));
        $this->assertSame('unknown_user', $this->causes[5]);
    }

    public function testAnIpGetsTwentyRequestsAnHour(): void
    {
        for ($i = 0; $i < 20; ++$i) {
            $answer = $this->requestReset('unknown-' . $i . '@example.com');
        }

        $this->assertSame($answer, $this->requestReset('jane'));
        $this->assertEmailCount(0);
        $this->assertSame('rate_limited', end($this->causes));

        // From elsewhere, the account still gets its mail.
        $this->requestReset('jane', '203.0.113.7');
        $this->assertEmailCount(1);
    }

    private function requestReset(string $identifier, string $ip = '127.0.0.1'): array
    {
        return $this->post('form-password_reset_request_form', 'password_reset_request_form', $identifier, $ip);
    }

    private function requestMagicLink(string $identifier): array
    {
        return $this->post('form-magic_link_request_form', 'magic_link_request_form', $identifier, '127.0.0.1');
    }

    private function post(string $name, string $formName, string $identifier, string $ip): array
    {
        $this->client->request(
            'POST',
            '/_forms/submit/' . $name,
            [$formName => ['identifier' => $identifier, '_token' => 'csrf-token']],
            [],
            ['HTTP_ACCEPT' => 'application/json', 'HTTP_ORIGIN' => 'http://localhost', 'REMOTE_ADDR' => $ip]
        );

        return json_decode($this->client->getResponse()->getContent(), true);
    }
}
