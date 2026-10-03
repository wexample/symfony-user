<?php

namespace Wexample\SymfonyUser\Tests\Fixtures\App;

/**
 * As ImpersonationAppKernel, any account a target — a development setting.
 */
class ImpersonationAnyAppKernel extends ImpersonationAppKernel
{
    protected function getConfigFiles(): array
    {
        return [
            ...parent::getConfigFiles(),
            __DIR__ . '/config/impersonation_any.yaml',
        ];
    }
}
