<?php

namespace Wexample\SymfonyUser\Tests\Fixtures\Tunnel;

use Wexample\SymfonyTunnels\Class\TunnelCursor;
use Wexample\SymfonyTunnels\Service\Step\AbstractTunnelStep;

class SignupDoneStep extends AbstractTunnelStep
{
    public static function getName(): string
    {
        return 'done';
    }

    /**
     * Built lazily: the user-mail step needs this one to exist first.
     */
    public function buildViewParams(TunnelCursor $cursor): array
    {
        return ['identifier' => $cursor->manager->getVariableValue(SignupUserMailStep::VARIABLE_USER)];
    }
}
