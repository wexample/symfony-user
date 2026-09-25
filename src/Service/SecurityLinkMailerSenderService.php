<?php

namespace Wexample\SymfonyUser\Service;

use DateTimeImmutable;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Wexample\SymfonyUser\Entity\AbstractUser;
use Wexample\SymfonyUser\Enum\SecurityLinkType;
use Wexample\SymfonyUser\Interface\SecurityLinkSenderInterface;

/**
 * The sender comes from the mailer configuration of the application
 * (`framework.mailer.headers.From` or its envelope).
 */
class SecurityLinkMailerSenderService implements SecurityLinkSenderInterface
{
    private const array SUBJECTS = [
        SecurityLinkType::MAGIC_LINK->value => 'Your sign-in link',
        SecurityLinkType::PASSWORD_RESET->value => 'Reset your password',
    ];

    public function __construct(
        private readonly MailerInterface $mailer
    ) {
    }

    public function send(
        AbstractUser $user,
        SecurityLinkType $type,
        string $url,
        DateTimeImmutable $expiresAt
    ): void {
        $this->mailer->send(
            (new TemplatedEmail())
                ->to(new Address((string) $user->getEmail()))
                ->subject(self::SUBJECTS[$type->value])
                ->htmlTemplate('@WexampleSymfonyUserBundle/mails/' . $type->value . '.html.twig')
                ->context([
                    'url' => $url,
                    'expires_at' => $expiresAt,
                ])
        );
    }
}
