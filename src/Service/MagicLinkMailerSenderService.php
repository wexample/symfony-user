<?php

namespace Wexample\SymfonyUser\Service;

use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Security\Http\LoginLink\LoginLinkDetails;
use Wexample\SymfonyUser\Entity\AbstractUser;
use Wexample\SymfonyUser\Interface\MagicLinkSenderInterface;

/**
 * The sender comes from the mailer configuration of the application
 * (`framework.mailer.headers.From` or its envelope).
 */
class MagicLinkMailerSenderService implements MagicLinkSenderInterface
{
    public const string TEMPLATE = '@WexampleSymfonyUserBundle/mails/magic_link.html.twig';

    public function __construct(
        private readonly MailerInterface $mailer
    ) {
    }

    public function send(AbstractUser $user, LoginLinkDetails $link): void
    {
        $this->mailer->send(
            (new TemplatedEmail())
                ->to(new Address((string) $user->getEmail()))
                ->subject('Your sign-in link')
                ->htmlTemplate(self::TEMPLATE)
                ->context([
                    'url' => $link->getUrl(),
                    'expires_at' => $link->getExpiresAt(),
                ])
        );
    }
}
