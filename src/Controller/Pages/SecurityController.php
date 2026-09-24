<?php

namespace Wexample\SymfonyUser\Controller\Pages;

use LogicException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use Wexample\SymfonyLoader\Controller\AbstractPagesController;
use Wexample\SymfonyUser\Form\LoginForm;
use Wexample\SymfonyUser\Service\FormProcessor\LoginFormProcessor;
use Wexample\SymfonyUser\Traits\SymfonyUserBundleClassTrait;

#[Route(name: 'user_security_')]
final class SecurityController extends AbstractPagesController
{
    use SymfonyUserBundleClassTrait;

    public const string ROUTE_LOGIN = 'user_security_login';
    public const string ROUTE_LOGOUT = 'user_security_logout';

    #[Route(path: '/login', name: 'login')]
    public function login(
        LoginFormProcessor $loginFormProcessor,
        AuthenticationUtils $authenticationUtils
    ): Response {
        $form = $loginFormProcessor->createForm([
            LoginForm::FIELD_IDENTIFIER => $authenticationUtils->getLastUsername(),
        ]);

        if ($error = $authenticationUtils->getLastAuthenticationError()) {
            $loginFormProcessor->addAuthenticationError($form, $error);
        }

        return $this->renderPage('login', [
            'login_form' => $form->createView(),
        ]);
    }

    #[Route(path: '/logout', name: 'logout')]
    public function logout(): never
    {
        throw new LogicException('Intercepted by the logout key of the firewall.');
    }
}
