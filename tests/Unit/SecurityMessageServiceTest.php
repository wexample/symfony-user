<?php

namespace Wexample\SymfonyUser\Tests\Unit;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Messenger\MessageBus;
use Wexample\SymfonyUser\Enum\SecurityMessageType;
use Wexample\SymfonyUser\Event\SecurityEvent;
use Wexample\SymfonyUser\Interface\SecurityMessageSenderInterface;
use Wexample\SymfonyUser\Service\SecurityJournalService;
use Wexample\SymfonyUser\Service\SecurityMessageService;
use Wexample\SymfonyUser\Tests\Fixtures\App\Entity\User;

class SecurityMessageServiceTest extends TestCase
{
    public function testAFailedSendIsJournaledWithoutItsMessageThenThrown(): void
    {
        $dispatcher = new EventDispatcher();
        $events = [];
        $dispatcher->addListener(SecurityEvent::class, function (SecurityEvent $event) use (&$events) {
            $events[] = $event;
        });

        $sender = new class implements SecurityMessageSenderInterface {
            public function send($user, SecurityMessageType $type, string $value, DateTimeImmutable $expiresAt): void
            {
                throw new RuntimeException('Rejected jane@example.com: https://example.com/?hash=secret');
            }
        };

        $service = new SecurityMessageService(
            new MessageBus(),
            $sender,
            new SecurityJournalService($dispatcher, new RequestStack(), new Security(new Container()), 'secret')
        );

        try {
            $service->send((new User())->setEmail('jane@example.com'), SecurityMessageType::PASSWORD_RESET, 'https://example.com/?hash=secret', new DateTimeImmutable());
            $this->fail('The failure must reach the transport, to be retried.');
        } catch (RuntimeException) {
        }

        $this->assertCount(1, $events);
        $this->assertSame('security_message.failed', $events[0]->type->value);
        $this->assertSame(RuntimeException::class, $events[0]->cause);
        $this->assertSame(['message_type' => 'password_reset'], $events[0]->extra);
    }
}
