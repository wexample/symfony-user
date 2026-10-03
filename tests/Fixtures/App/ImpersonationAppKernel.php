<?php

namespace Wexample\SymfonyUser\Tests\Fixtures\App;

/**
 * The fixture application where managers impersonate the accounts they
 * administer, listed up to 3; the "outsider-" accounts are out of scope.
 */
class ImpersonationAppKernel extends AppKernel
{
    protected function getConfigFiles(): array
    {
        return [
            ...parent::getConfigFiles(),
            __DIR__ . '/config/impersonation.yaml',
        ];
    }
}
