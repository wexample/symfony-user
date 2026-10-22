<?php

namespace Wexample\SymfonyUser\Tests\Fixtures\App;

/**
 * The fixture application with administration rules: ROLE_MANAGER protected,
 * held only by an example.com address, never given with ROLE_CUSTOMER — which
 * it reaches, to administer the customers.
 */
class AdministrationAppKernel extends AppKernel
{
    protected function getConfigFiles(): array
    {
        return [
            ...parent::getConfigFiles(),
            __DIR__ . '/config/administration.yaml',
        ];
    }
}
