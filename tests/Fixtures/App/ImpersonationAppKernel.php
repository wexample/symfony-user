<?php

namespace Wexample\SymfonyUser\Tests\Fixtures\App;

/**
 * The fixture application where managers hold the switch role, the accounts
 * listed up to 3; the "outsider-" accounts are out of the scope of account
 * administration, which impersonation does not ask.
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
