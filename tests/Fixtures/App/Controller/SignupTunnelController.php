<?php

namespace Wexample\SymfonyUser\Tests\Fixtures\App\Controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Wexample\SymfonyTunnels\Attribute\TunnelRoute;
use Wexample\SymfonyTunnels\Controller\AbstractTunnelController;
use Wexample\SymfonyUser\Tests\Fixtures\Tunnel\SignupTunnelManagerService;

#[Route(path: 'signup/', name: 'signup_')]
class SignupTunnelController extends AbstractTunnelController
{
    public static function getTunnelManagerClass(): string
    {
        return SignupTunnelManagerService::class;
    }

    #[TunnelRoute(cursorPlaceholder: '{step}')]
    public function index(Request $request): Response
    {
        return $this->handleTunnelRequest($request);
    }
}
