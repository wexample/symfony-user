<?php

namespace Wexample\SymfonyUser\Service\Tunnel\TotpSetup;

use Wexample\SymfonyTunnels\Interface\TunnelSessionStorageInterface;
use Wexample\SymfonyTunnels\Service\AbstractTunnelManagerService;
use Wexample\SymfonyTunnels\Service\Step\AbstractTunnelStep;

/**
 * Setting the authenticator app up, in three steps the visitor sees ahead of
 * them: scan the code, type the one the app shows, keep the backup codes.
 */
class TotpSetupTunnelManagerService extends AbstractTunnelManagerService
{
    public function __construct(
        TunnelSessionStorageInterface $sessionStorage,
        private readonly TotpScanStep $scanStep,
    ) {
        parent::__construct($sessionStorage);
    }

    public static function getName(): string
    {
        return 'totp_setup';
    }

    public function getEntrypointStep(): AbstractTunnelStep
    {
        return $this->scanStep;
    }
}
