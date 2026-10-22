<?php

namespace Wexample\SymfonyUser\Tests\Fixtures\App;

/**
 * The fixture application where administration follows
 * `administration.manages`, not the hierarchy, and an application guard
 * keeps the "outsider-" accounts out of reach.
 */
class ManagesAppKernel extends AppKernel
{
    protected function getConfigFiles(): array
    {
        return [
            ...parent::getConfigFiles(),
            __DIR__ . '/config/manages.yaml',
        ];
    }
}
