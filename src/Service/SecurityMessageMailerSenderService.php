<?php

namespace Wexample\SymfonyUser\Service;

use DateTimeImmutable;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mime\Address;
use Wexample\SymfonyTranslations\Translation\Translator;
use Wexample\SymfonyUser\Entity\AbstractUser;
use Wexample\SymfonyUser\Enum\SecurityMessageType;
use Wexample\SymfonyUser\Interface\SecurityMessageSenderInterface;

/**
 * The sender comes from the mailer configuration of the application
 * (`framework.mailer.headers.From` or its envelope). Every text, subject
 * included, comes from the yml next to the template, read as `@mail::`.
 *
 * Handed to the transport itself, not to the mailer: the mailer queues on
 * Messenger when the application routes SendEmailMessage — the Flex recipe
 * does —, and the queue would keep the rendered mail, link included. Link
 * mails are queued before, without their link, as SendSecurityMessage.
 */
class SecurityMessageMailerSenderService implements SecurityMessageSenderInterface
{
    public function __construct(
        private readonly TransportInterface $transport,
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

        // Held for the rendering too, which the transport does within send().
        $this->translator->setDomainFromTemplatePath(Translator::DOMAIN_TYPE_MAIL, $template);

        try {
            $this->transport->send(
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
