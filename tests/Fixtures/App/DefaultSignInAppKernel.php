<?php

namespace Wexample\SymfonyUser\Tests\Fixtures\App;

/**
 * The fixture application with the package's defaults on the sign-in forms.
 */
class DefaultSignInAppKernel extends AppKernel
{
    protected function getConfigFiles(): array
    {
        return [
            ...parent::getConfigFiles(),
            __DIR__ . '/config/default_sign_in.yaml',
        ];
    }
}
