<?php

namespace Wexample\SymfonyUser\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Wexample\SymfonyUser\Entity\AbstractUser;
use Wexample\SymfonyUser\Routing\UserRoute;
use Wexample\SymfonyUser\Service\TotpService;

/**
 * New backup codes for the authenticator app of the signed-in user, shown
 * once on the page that follows.
 */
#[IsGranted('IS_AUTHENTICATED_FULLY')]
final class TotpBackupCodesController extends AbstractController
{
    #[Route(path: '/account/authenticator/backup-codes/regenerate', name: UserRoute::TOTP_REGENERATE, methods: [Request::METHOD_POST])]
    public function regenerate(TotpService $totpService): Response
    {
        $user = $this->getUser();

        if (! $user instanceof AbstractUser) {
            throw $this->createAccessDeniedException();
        }

        if ($user->isTotpAuthenticationEnabled()) {
            $totpService->regenerateBackupCodes($user);

            return $this->redirectToRoute(UserRoute::TOTP_BACKUP_CODES);
        }

        return $this->redirectToRoute(UserRoute::TOTP);
    }
}
