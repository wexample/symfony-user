<?php

namespace Wexample\SymfonyUser\Tests\Fixtures\App;

/**
 * The fixture application with terms of use in force, version `v2`.
 */
class TermsAppKernel extends AppKernel
{
    protected function getConfigFiles(): array
    {
        return [
            ...parent::getConfigFiles(),
            __DIR__ . '/config/terms.yaml',
        ];
    }
}
