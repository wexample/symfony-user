<?php

namespace Wexample\SymfonyUser\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Security\Http\Event\CheckPassportEvent;
use Wexample\SymfonyUser\Security\UserChecker;

/**
 * Refuses a locked or disabled account as soon as its credentials are checked,
 * whatever the authenticator. Symfony would only do it on the authentication
 * success, which scheb/2fa-bundle withholds until the second factor: the
 * account would get a code before being refused.
 */
class AccountStatusSubscriber implements EventSubscriberInterface
{
    /**
     * After CheckCredentialsListener (0): a wrong password fails first, and
     * the account state stays hidden from whoever does not know it.
     */
    public const int PRIORITY = -256;

    public function __construct(
        private readonly UserChecker $userChecker
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [CheckPassportEvent::class => ['onCheckPassport', self::PRIORITY]];
    }

    public function onCheckPassport(CheckPassportEvent $event): void
    {
        $this->userChecker->checkAccountStatus($event->getPassport()->getUser());
    }
}
