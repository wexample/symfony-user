<?php

namespace Wexample\SymfonyUser\EventSubscriber;

use Monolog\Attribute\WithMonologChannel;
use Wexample\SymfonySecurity\EventSubscriber\AbstractSecurityEventLogSubscriber;
use Wexample\SymfonyUser\Event\SecurityEvent;

/**
 * The default recorder of the security facts: a log channel of their own,
 * `user_security`. Storing, purging and reading them belong to an audit
 * package, listening to the same events.
 */
#[WithMonologChannel('user_security')]
class SecurityJournalLogSubscriber extends AbstractSecurityEventLogSubscriber
{
    public static function getSubscribedEvents(): array
    {
        return [SecurityEvent::class => 'onSecurityEvent'];
    }
}
