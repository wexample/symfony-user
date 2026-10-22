<?php

namespace Wexample\SymfonyUser\Tests\Fixtures\Tunnel;

use Wexample\SymfonyTunnels\Interface\TunnelSessionStorageInterface;
use Wexample\SymfonyTunnels\Service\AbstractTunnelManagerService;
use Wexample\SymfonyTunnels\Service\Step\AbstractTunnelStep;

class SignupTunnelManagerService extends AbstractTunnelManagerService
{
    public function __construct(
        TunnelSessionStorageInterface $sessionStorage,
        private readonly SignupUserMailStep $userMailStep,
    ) {
        parent::__construct($sessionStorage);
    }

    public static function getName(): string
    {
        return 'signup';
    }

    public function getEntrypointStep(): AbstractTunnelStep
    {
        return $this->userMailStep;
    }
}
