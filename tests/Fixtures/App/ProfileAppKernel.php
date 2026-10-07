<?php

namespace Wexample\SymfonyUser\Tests\Fixtures\App;

/**
 * The fixture application in two languages, so the profile form has one to
 * choose.
 */
class ProfileAppKernel extends AppKernel
{
    protected function getConfigFiles(): array
    {
        return [
            ...parent::getConfigFiles(),
            __DIR__ . '/config/profile.yaml',
        ];
    }
}
