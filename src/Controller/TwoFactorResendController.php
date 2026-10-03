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
use Wexample\SymfonyUser\Service\SecurityJournalService;
use Wexample\SymfonyUser\Service\TwoFactorCodeService;

/**
 * Sends the code of a login waiting for its second factor again.
 */
final class TwoFactorResendController extends AbstractController
{
    #[Route(path: '/login/2fa/resend', name: UserRoute::TWO_FACTOR_RESEND, methods: [Request::METHOD_POST])]
    public function resend(
        TokenStorageInterface $tokenStorage,
        TwoFactorCodeService $codeService,
        SecurityJournalService $journal
    ): Response {
        $token = $tokenStorage->getToken();
        $user = $token instanceof TwoFactorTokenInterface ? $token->getUser() : null;

        if ($user instanceof AbstractUser && $codeService->canResend()) {
            $journal->record(SecurityEventType::SECOND_FACTOR_CODE_RESENT, $user);
            $codeService->send($user);
        }

        return $this->redirectToRoute(UserRoute::TWO_FACTOR);
    }
}
