<?php

namespace Wexample\SymfonyUser\EventSubscriber;

use Scheb\TwoFactorBundle\Security\Authentication\Token\TwoFactorTokenInterface;
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

    /**
     * Marks the magic link sign-ins waiting for their second factor: the
     * proof is only granted once the code is checked too.
     */
    private const string SESSION_PENDING = 'wexample_user_password_reset_proof_pending';

    public function onLoginSuccess(LoginSuccessEvent $event): void
    {
        $user = $event->getUser();

        if ($this->passwordResetService->getMode() !== PasswordResetMode::MAGIC_LINK
            || ! $user instanceof AbstractUser) {
            return;
        }

        $session = $event->getRequest()->getSession();
        $secondFactorPending = $event->getAuthenticatedToken() instanceof TwoFactorTokenInterface;

        // The route rather than the authenticator class: in debug, the
        // authenticator reaches the event wrapped in a traceable one.
        if ($event->getRequest()->attributes->get('_route') === SecurityController::ROUTE_LOGIN_LINK) {
            if ($secondFactorPending) {
                $session->set(self::SESSION_PENDING, $user->getUserIdentifier());

                return;
            }

            $this->passwordResetService->grantProof($user);

            return;
        }

        // The second factor of a magic link sign-in, now checked.
        if (! $secondFactorPending && $session->get(self::SESSION_PENDING) === $user->getUserIdentifier()) {
            $session->remove(self::SESSION_PENDING);
            $this->passwordResetService->grantProof($user);
        }
    }
}
