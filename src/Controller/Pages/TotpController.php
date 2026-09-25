<?php

namespace Wexample\SymfonyUser\Controller\Pages;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Wexample\SymfonyLoader\Controller\AbstractPagesController;
use Wexample\SymfonyUser\Entity\AbstractUser;
use Wexample\SymfonyUser\Service\FormProcessor\TotpDisableFormProcessor;
use Wexample\SymfonyUser\Service\FormProcessor\TotpEnableFormProcessor;
use Wexample\SymfonyUser\Service\TotpService;
use Wexample\SymfonyUser\Traits\SymfonyUserBundleClassTrait;

/**
 * The authenticator app of the signed-in user: set it up, get backup codes,
 * turn it off.
 */
#[Route(path: '/account/authenticator', name: 'user_totp_')]
#[IsGranted('IS_AUTHENTICATED_FULLY')]
final class TotpController extends AbstractPagesController
{
    use SymfonyUserBundleClassTrait;

    public const string ROUTE_INDEX = 'user_totp_index';
    public const string ROUTE_BACKUP_CODES = 'user_totp_backup_codes';
    public const string ROUTE_REGENERATE = 'user_totp_regenerate';

    #[Route(path: '', name: 'index')]
    public function index(
        TotpService $totpService,
        TotpEnableFormProcessor $enableFormProcessor,
        TotpDisableFormProcessor $disableFormProcessor
    ): Response {
        $user = $this->getAbstractUser();

        if ($user->isTotpAuthenticationEnabled()) {
            return $this->renderPage('index', [
                'enabled' => true,
                'backup_codes_count' => $user->countBackupCodes(),
                'totp_disable_form' => $disableFormProcessor->createForm()->createView(),
            ]);
        }

        return $this->renderPage('index', [
            'enabled' => false,
            'qr_code' => $totpService->getPendingQrCodeDataUri($user),
            'secret' => $totpService->getPendingSecret(),
            'totp_enable_form' => $enableFormProcessor->createForm()->createView(),
        ]);
    }

    /**
     * Shown once: the codes are not kept in clear anywhere.
     */
    #[Route(path: '/backup-codes', name: 'backup_codes')]
    public function backupCodes(TotpService $totpService): Response
    {
        if (! $codes = $totpService->pullNewBackupCodes()) {
            return $this->redirectToRoute(self::ROUTE_INDEX);
        }

        return $this->renderPage('backup_codes', ['codes' => $codes]);
    }

    #[Route(path: '/backup-codes/regenerate', name: 'regenerate', methods: [Request::METHOD_POST])]
    public function regenerate(TotpService $totpService): Response
    {
        $user = $this->getAbstractUser();

        if ($user->isTotpAuthenticationEnabled()) {
            $totpService->regenerateBackupCodes($user);

            return $this->redirectToRoute(self::ROUTE_BACKUP_CODES);
        }

        return $this->redirectToRoute(self::ROUTE_INDEX);
    }

    private function getAbstractUser(): AbstractUser
    {
        $user = $this->getUser();

        if (! $user instanceof AbstractUser) {
            throw $this->createAccessDeniedException();
        }

        return $user;
    }
}
