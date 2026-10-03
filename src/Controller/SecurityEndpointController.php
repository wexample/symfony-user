<?php

namespace Wexample\SymfonyUser\Controller;

use LogicException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Attribute\Route;
use Wexample\SymfonyUser\Routing\UserRoute;

/**
 * The routes the firewall intercepts: they must exist, they never run.
 */
final class SecurityEndpointController extends AbstractController
{
    #[Route(path: '/login/link', name: UserRoute::LOGIN_LINK)]
    public function loginLink(): never
    {
        throw new LogicException('Intercepted by the login_link key of the firewall.');
    }

    #[Route(path: '/logout', name: UserRoute::LOGOUT)]
    public function logout(): never
    {
        throw new LogicException('Intercepted by the logout key of the firewall.');
    }
}
