<?php

namespace Wexample\SymfonyUser\Service;

use DateTimeImmutable;
use Symfony\Component\Messenger\MessageBusInterface;
use Throwable;
use Wexample\SymfonyUser\Entity\AbstractUser;
use Wexample\SymfonyUser\Enum\SecurityEventType;
use Wexample\SymfonyUser\Enum\SecurityMessageType;
use Wexample\SymfonyUser\Interface\SecurityMessageSenderInterface;
use Wexample\SymfonyUser\Message\SendSecurityMessage;

/**
 * Where every security message leaves from.
 *
 * Link mails are queued on the message bus: routed to an asynchronous
 * transport, a request never waits on the mail provider; unrouted, they are
 * sent at once. Codes are sent at once: the user is waiting for them.
 *
 * Every message handed to the sender is journaled, sent or failed, with its
 * type and account — never its link, code or body.
 */
class SecurityMessageService
{
    public function __construct(
        private readonly MessageBusInterface $bus,
        private readonly SecurityMessageSenderInterface $sender,
        private readonly SecurityJournalService $journal,
    ) {
    }

    public function queue(AbstractUser $user, SecurityMessageType $type, ?string $targetPath = null): void
    {
        $this->bus->dispatch(new SendSecurityMessage($user::class, (string) $user->getId(), $type, $targetPath));
    }

    public function send(AbstractUser $user, SecurityMessageType $type, string $value, DateTimeImmutable $expiresAt): void
    {
        try {
            $this->sender->send($user, $type, $value, $expiresAt);
        } catch (Throwable $exception) {
            // Journaled without its message, which may quote the address;
            // the transport retries it, or keeps it in its failed queue.
            $this->journal->record(
                SecurityEventType::SECURITY_MESSAGE_FAILED,
                $user,
                $exception::class,
                extra: ['message_type' => $type->value]
            );

            throw $exception;
        }

        $this->journal->record(SecurityEventType::SECURITY_MESSAGE_SENT, $user, extra: ['message_type' => $type->value]);
    }
}
