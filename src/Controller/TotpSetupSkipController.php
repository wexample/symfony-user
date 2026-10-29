<?php

namespace Wexample\SymfonyUser\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Wexample\SymfonyUser\Entity\AbstractUser;
use Wexample\SymfonyUser\Enum\SecurityEventType;
use Wexample\SymfonyUser\Routing\UserRoute;
use Wexample\SymfonyUser\Security\Gate\TotpSetupGate;
use Wexample\SymfonyUser\Service\PostLoginTargetService;
use Wexample\SymfonyUser\Service\SecurityJournalService;

/**
 * Development alone: an account held on the authenticator's setup page goes
 * on without the app, for this session. The gate stays as production has it
 * — the app asked, its page shown —, and skipping it is a click said apart
 * on that page rather than a configuration that drifts from production. The
 * route exists in `dev` only: anywhere else the path leads nowhere.
 */
#[IsGranted('IS_AUTHENTICATED')]
final class TotpSetupSkipController extends AbstractController
{
    final public const string CSRF_TOKEN_ID = 'totp-setup-skip';

    // Before the tunnel's `/setup/{step}`, which would read `skip` as a step.
    #[Route(path: '/account/authenticator/setup/skip', name: UserRoute::TOTP_SETUP_SKIP, methods: [Request::METHOD_GET], priority: 1, env: 'dev')]
    public function skip(
        Request $request,
        TokenStorageInterface $tokenStorage,
        PostLoginTargetService $postLoginTarget,
        SecurityJournalService $journal
    ): Response {
        $token = $tokenStorage->getToken();
        $user = $token?->getUser();

        if (! $user instanceof AbstractUser) {
            return $this->redirectToRoute(UserRoute::LOGIN);
        }

        if (! $this->isCsrfTokenValid(self::CSRF_TOKEN_ID, (string) $request->query->get('_token'))) {
            return $this->redirectToRoute(UserRoute::TOTP);
        }

        $journal->record(SecurityEventType::ACCOUNT_TOTP_SETUP_SKIPPED, $user);
        $request->getSession()->set(TotpSetupGate::SESSION_SKIPPED, true);

        return $this->redirect($postLoginTarget->getRoleUrl($request, $token));
    }
}
