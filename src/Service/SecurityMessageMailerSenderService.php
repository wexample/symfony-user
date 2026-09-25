<?php

namespace Wexample\SymfonyUser\Service;

use DateTimeImmutable;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Wexample\SymfonyUser\Entity\AbstractUser;
use Wexample\SymfonyUser\Enum\SecurityMessageType;
use Wexample\SymfonyUser\Interface\SecurityMessageSenderInterface;

/**
 * The sender comes from the mailer configuration of the application
 * (`framework.mailer.headers.From` or its envelope).
 */
class SecurityMessageMailerSenderService implements SecurityMessageSenderInterface
{
    private const array SUBJECTS = [
        SecurityMessageType::MAGIC_LINK->value => 'Your sign-in link',
        SecurityMessageType::PASSWORD_RESET->value => 'Reset your password',
        SecurityMessageType::TWO_FACTOR_CODE->value => 'Your sign-in code',
    ];

    public function __construct(
        private readonly MailerInterface $mailer
    ) {
    }

    public function send(
        AbstractUser $user,
        SecurityMessageType $type,
        string $value,
        DateTimeImmutable $expiresAt
    ): void {
        $this->mailer->send(
            (new TemplatedEmail())
                ->to(new Address((string) $user->getEmail()))
                ->subject(self::SUBJECTS[$type->value])
                ->htmlTemplate('@WexampleSymfonyUserBundle/mails/' . $type->value . '.html.twig')
                ->context([
                    'value' => $value,
                    'expires_at' => $expiresAt,
                ])
        );
    }
}
