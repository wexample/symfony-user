<?php

namespace Wexample\SymfonyUser\Tests\Fixtures\App;

/**
 * The fixture application asking for the package's own User explicitly.
 */
class PackageUserAppKernel extends AppKernel
{
    protected function getConfigFiles(): array
    {
        return [
            ...parent::getConfigFiles(),
            __DIR__ . '/config/package_user.yaml',
        ];
    }
}
