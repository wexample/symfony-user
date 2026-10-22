<?php

namespace Wexample\SymfonyUser\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Wexample\SymfonyUser\Routing\UserRoute;
use Wexample\SymfonyUser\Service\ActivationProgressService;
use Wexample\SymfonyUser\Service\PasswordResetService;

/**
 * The links the package mails. They only grant the proof, then move on to a
 * URL that does not carry the signature, kept out of history and referers.
 */
final class PasswordLinkController extends AbstractController
{
    #[Route(path: '/password/reset', name: UserRoute::PASSWORD_RESET)]
    public function reset(
        Request $request,
        PasswordResetService $passwordResetService
    ): Response {
        if ($passwordResetService->consumeResetLink(
            (string) $request->query->get('user'),
            $request->query->getInt('expires'),
            (string) $request->query->get('hash')
        )) {
            return $this->redirectToRoute(UserRoute::PASSWORD_NEW);
        }

        return $this->redirectToRoute(UserRoute::PASSWORD_FORGOT, [UserRoute::PARAMETER_LINK => UserRoute::LINK_INVALID]);
    }

    /**
     * The first password of an account an administrator created. A dead link
     * explains itself, instead of offering a reset the account cannot ask for
     * yet.
     */
    #[Route(path: '/password/activate', name: UserRoute::PASSWORD_ACTIVATE)]
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

            return $this->redirectToRoute(UserRoute::PASSWORD_NEW);
        }

        return $this->redirectToRoute(UserRoute::PASSWORD_ACTIVATION_INVALID);
    }
}
