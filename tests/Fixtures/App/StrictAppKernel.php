<?php

namespace Wexample\SymfonyUser\Tests\Fixtures\App;

/**
 * The fixture application under the strictest policy: a second factor on
 * every sign-in, an authenticator app for administrators, no magic link login.
 */
class StrictAppKernel extends AppKernel
{
    protected function getConfigFiles(): array
    {
        return [
            ...parent::getConfigFiles(),
            __DIR__ . '/config/strict.yaml',
        ];
    }
}
