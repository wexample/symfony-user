<?php

namespace Wexample\SymfonyUser\Tests\Fixtures\App;

/**
 * The fixture application with `password.refuse_leaked` on, and the API it
 * asks replaced by a mock: the suite reaches no network.
 */
class LeakedPasswordAppKernel extends AppKernel
{
    protected function getConfigFiles(): array
    {
        return [
            ...parent::getConfigFiles(),
            __DIR__ . '/config/leaked_password.yaml',
        ];
    }
}
