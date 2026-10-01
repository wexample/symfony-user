<?php

namespace Wexample\SymfonyUser\Controller\Pages;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Wexample\SymfonyLoader\Controller\AbstractPagesController;
use Wexample\SymfonyUser\Service\FormProcessor\PasswordResetRequestFormProcessor;
use Wexample\SymfonyUser\Service\FormProcessor\SetPasswordFormProcessor;
use Wexample\SymfonyUser\Service\ActivationProgressService;
use Wexample\SymfonyUser\Service\PasswordResetService;
use Wexample\SymfonyUser\Traits\SymfonyUserBundleClassTrait;

#[Route(path: '/password/', name: 'user_password_')]
final class PasswordController extends AbstractPagesController
{
    use SymfonyUserBundleClassTrait;

    public const string ROUTE_FORGOT = 'user_password_forgot';
    public const string ROUTE_RESET = 'user_password_reset';
    public const string ROUTE_NEW = 'user_password_new';
    public const string ROUTE_ACTIVATE = 'user_password_activate';

    public const string LINK_INVALID = 'invalid';

    #[Route(path: 'forgot', name: 'forgot')]
    public function forgot(
        Request $request,
        PasswordResetRequestFormProcessor $processor
    ): Response {
        return $this->renderPage('forgot', [
            'link_invalid' => $request->query->get('link') === self::LINK_INVALID,
            'password_reset_request_form' => $processor->createForm()->createView(),
        ]);
    }

    /**
     * The link of the reset mail. It only grants the proof, then moves on to a
     * URL that does not carry the signature, kept out of history and referers.
     */
    #[Route(path: 'reset', name: 'reset')]
    public function reset(
        Request $request,
        PasswordResetService $passwordResetService
    ): Response {
        if ($passwordResetService->consumeResetLink(
            (string) $request->query->get('user'),
            $request->query->getInt('expires'),
            (string) $request->query->get('hash')
        )) {
            return $this->redirectToRoute(self::ROUTE_NEW);
        }

        return $this->redirectToRoute(self::ROUTE_FORGOT, ['link' => self::LINK_INVALID]);
    }

    /**
     * The link of the activation mail: the first password of an account an
     * administrator created. A dead link explains itself, instead of offering
     * a reset the account cannot ask for yet.
     */
    #[Route(path: 'activate', name: 'activate')]
    public function activate(
        Request $request,
        PasswordResetService $passwordResetService,
        ActivationProgressService $activationProgress
    ): Response {
        if ($passwordResetService->consumeActivationLink(
            (string) $request->query->get('user'),
            $request->query->getInt('expires'),
            (string) $request->query->get('hash')
        )) {
            $activationProgress->start();

            return $this->redirectToRoute(self::ROUTE_NEW);
        }

        return $this->renderPage('activation_invalid');
    }

    #[Route(path: 'new', name: 'new')]
    public function new(
        PasswordResetService $passwordResetService,
        SetPasswordFormProcessor $processor,
        ActivationProgressService $activationProgress
    ): Response {
        if (! $user = $passwordResetService->getProofUser()) {
            return $this->redirectToRoute(self::ROUTE_FORGOT);
        }

        return $this->renderPage('new', [
            'user' => $user,
            'activation_steps' => $activationProgress->steps(ActivationProgressService::STEP_PASSWORD),
            'set_password_form' => $processor->createForm()->createView(),
        ]);
    }
}
