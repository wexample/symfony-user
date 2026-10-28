<?php

namespace Wexample\SymfonyUser\Controller;

use Scheb\TwoFactorBundle\Security\Authentication\Token\TwoFactorTokenInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Wexample\SymfonyUser\Entity\AbstractUser;
use Wexample\SymfonyUser\Enum\SecurityEventType;
use Wexample\SymfonyUser\Routing\UserRoute;
use Wexample\SymfonyUser\Security\Handler\TwoFactorResponseHandler;
use Wexample\SymfonyUser\Service\SecurityJournalService;

/**
 * Development alone: a login waiting for its second factor goes on without
 * it, the password already given. The second factor stays as production has
 * it — asked, its page shown —, and skipping it is a click said apart on that
 * page rather than a configuration that drifts from production. The route
 * exists in `dev` only: anywhere else the path leads nowhere.
 */
final class TwoFactorSkipController extends AbstractController
{
    final public const string CSRF_TOKEN_ID = 'two-factor-skip';

    #[Route(path: '/login/2fa/skip', name: UserRoute::TWO_FACTOR_SKIP, methods: [Request::METHOD_GET], env: 'dev')]
    public function skip(
        Request $request,
        TokenStorageInterface $tokenStorage,
        TwoFactorResponseHandler $responseHandler,
        SecurityJournalService $journal
    ): Response {
        $token = $tokenStorage->getToken();

        if (! $token instanceof TwoFactorTokenInterface || ! $token->getUser() instanceof AbstractUser) {
            return $this->redirectToRoute(UserRoute::LOGIN);
        }

        if (! $this->isCsrfTokenValid(self::CSRF_TOKEN_ID, (string) $request->query->get('_token'))) {
            return $this->redirectToRoute(UserRoute::TWO_FACTOR);
        }

        $journal->record(SecurityEventType::SECOND_FACTOR_SKIPPED, $token->getUser());

        // Signed in as a right code would have left it: the token the
        // password gave, and the page the visitor was on their way to.
        $authenticated = $token->getAuthenticatedToken();
        $tokenStorage->setToken($authenticated);

        return $responseHandler->onAuthenticationSuccess($request, $authenticated);
    }
}
