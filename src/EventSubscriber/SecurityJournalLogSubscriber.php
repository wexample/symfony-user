<?php

namespace Wexample\SymfonyUser\EventSubscriber;

use Monolog\Attribute\WithMonologChannel;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Wexample\SymfonyUser\Event\SecurityEvent;

/**
 * The default recorder of the security facts: a log channel of their own,
 * `user_security`, so that their retention — they hold IP addresses — is set
 * apart from the technical logs. Storing, purging and reading them belong to
 * an audit package, listening to the same events.
 */
#[WithMonologChannel('user_security')]
class SecurityJournalLogSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly LoggerInterface $logger
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [SecurityEvent::class => 'onSecurityEvent'];
    }

    public function onSecurityEvent(SecurityEvent $event): void
    {
        $this->logger->log(
            $event->type->isFailure() ? 'warning' : 'info',
            $event->type->value,
            $event->toArray()
        );
    }
}
