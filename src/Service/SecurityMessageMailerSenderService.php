<?php

namespace Wexample\SymfonyUser\Service;

use DateTimeImmutable;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Wexample\SymfonyTranslations\Translation\Translator;
use Wexample\SymfonyUser\Entity\AbstractUser;
use Wexample\SymfonyUser\Enum\SecurityMessageType;
use Wexample\SymfonyUser\Interface\SecurityMessageSenderInterface;

/**
 * The sender comes from the mailer configuration of the application
 * (`framework.mailer.headers.From` or its envelope). Every text, subject
 * included, comes from the yml next to the template, read as `@mail::`.
 */
class SecurityMessageMailerSenderService implements SecurityMessageSenderInterface
{
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly Translator $translator,
    ) {
    }

    public function send(
        AbstractUser $user,
        SecurityMessageType $type,
        string $value,
        DateTimeImmutable $expiresAt
    ): void {
        $template = '@WexampleSymfonyUserBundle/mails/' . $type->value . '.html.twig';

        // Held for the rendering too, which the mailer does within send().
        $this->translator->setDomainFromTemplatePath(Translator::DOMAIN_TYPE_MAIL, $template);

        try {
            $this->mailer->send(
                (new TemplatedEmail())
                    ->to(new Address((string) $user->getEmail()))
                    ->subject($this->translator->trans('@mail::subject'))
                    ->htmlTemplate($template)
                    ->context([
                        'value' => $value,
                        'expires_at' => $expiresAt,
                    ])
            );
        } finally {
            $this->translator->revertDomain(Translator::DOMAIN_TYPE_MAIL);
        }
    }
}
