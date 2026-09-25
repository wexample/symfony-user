<?php

namespace Wexample\SymfonyUser\Controller\Pages;

use Scheb\TwoFactorBundle\Security\Authentication\Token\TwoFactorTokenInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Wexample\SymfonyLoader\Controller\AbstractPagesController;
use Wexample\SymfonyUser\Entity\AbstractUser;
use Wexample\SymfonyUser\Form\TwoFactorCodeForm;
use Wexample\SymfonyUser\Service\FormProcessor\TwoFactorCodeFormProcessor;
use Wexample\SymfonyUser\Service\TwoFactorCodeService;
use Wexample\SymfonyUser\Traits\SymfonyUserBundleClassTrait;

/**
 * The `auth_form_path` of the firewall: where a login waits for its code.
 */
#[Route(path: '/login/2fa', name: 'user_security_two_factor')]
final class TwoFactorController extends AbstractPagesController
{
    use SymfonyUserBundleClassTrait;

    public const string ROUTE_FORM = 'user_security_two_factor';
    public const string ROUTE_RESEND = 'user_security_two_factor_resend';

    #[Route(path: '', name: '')]
    public function form(
        Request $request,
        TokenStorageInterface $tokenStorage,
        TwoFactorCodeFormProcessor $formProcessor,
        TwoFactorCodeService $codeService
    ): Response {
        if (! $user = $this->getPendingUser($tokenStorage)) {
            return $this->redirectToRoute(SecurityController::ROUTE_LOGIN);
        }

        $backupCode = $request->query->getBoolean('backup');
        $form = $formProcessor->createForm(null, [TwoFactorCodeForm::OPTION_BACKUP_CODE => $backupCode]);

        if ($request->query->has('failed')) {
            $formProcessor->addFailure($form, $codeService->getLastFailure());
        }

        return $this->renderPage('index', [
            'email' => $user->getEmail(),
            'method' => $backupCode ? 'backup_code' : $tokenStorage->getToken()->getCurrentTwoFactorProvider(),
            'has_backup_codes' => $user->countBackupCodes() > 0,
            'can_resend' => $codeService->canResend(),
            'two_factor_code_form' => $form->createView(),
        ]);
    }

    #[Route(path: '/resend', name: '_resend', methods: [Request::METHOD_POST])]
    public function resend(
        TokenStorageInterface $tokenStorage,
        TwoFactorCodeService $codeService
    ): Response {
        if (($user = $this->getPendingUser($tokenStorage)) && $codeService->canResend()) {
            $codeService->send($user);
        }

        return $this->redirectToRoute(self::ROUTE_FORM);
    }

    private function getPendingUser(TokenStorageInterface $tokenStorage): ?AbstractUser
    {
        $token = $tokenStorage->getToken();
        $user = $token instanceof TwoFactorTokenInterface ? $token->getUser() : null;

        return $user instanceof AbstractUser ? $user : null;
    }
}
