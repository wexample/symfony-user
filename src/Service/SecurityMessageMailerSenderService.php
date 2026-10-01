<?php

namespace Wexample\SymfonyUser\Service;

use DateTimeImmutable;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Wexample\SymfonyMail\Service\MailSenderService;
use Wexample\SymfonyUser\Entity\AbstractUser;
use Wexample\SymfonyUser\Enum\SecurityMessageType;
use Wexample\SymfonyUser\Interface\SecurityMessageSenderInterface;

/**
 * Sends a security message by mail, through symfony-mail: texts beside the
 * template, the application's mail layout, and the account's language.
 *
 * The mail is handed to the transport, never queued rendered: link mails are
 * queued before, without their link, as SendSecurityMessage, and the link is
 * built by the worker just before this runs.
 */
class SecurityMessageMailerSenderService implements SecurityMessageSenderInterface
{
    public function __construct(
        private readonly MailSenderService $mailSender,
    ) {
    }

    public function send(
        AbstractUser $user,
        SecurityMessageType $type,
        string $value,
        DateTimeImmutable $expiresAt
    ): void {
        $this->mailSender->sendTo(
            $user,
            (new TemplatedEmail())
                ->htmlTemplate('@WexampleSymfonyUserBundle/mails/'.$type->value.'.html.twig')
                ->context([
                    'value' => $value,
                    'expires_at' => $expiresAt,
                ])
        );
    }
}
