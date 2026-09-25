<?php

namespace Wexample\SymfonyUser\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;
use Wexample\SymfonyUser\Controller\Pages\SecurityController;
use Wexample\SymfonyUser\Entity\AbstractUser;
use Wexample\SymfonyUser\Enum\PasswordResetMode;
use Wexample\SymfonyUser\Service\PasswordResetService;

/**
 * In the magic link mode, following a magic link proves the user owns the
 * mailbox, as a reset link would: they may set a new password for a while.
 */
class PasswordResetProofSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly PasswordResetService $passwordResetService
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [LoginSuccessEvent::class => 'onLoginSuccess'];
    }

    public function onLoginSuccess(LoginSuccessEvent $event): void
    {
        $user = $event->getUser();

        // The route rather than the authenticator class: in debug, the
        // authenticator reaches the event wrapped in a traceable one.
        if ($this->passwordResetService->getMode() !== PasswordResetMode::MAGIC_LINK
            || ! $user instanceof AbstractUser
            || $event->getRequest()->attributes->get('_route') !== SecurityController::ROUTE_LOGIN_LINK) {
            return;
        }

        $this->passwordResetService->grantProof($user);
    }
}
