<?php

namespace Wexample\SymfonyUser\Tests\Fixtures\App;

/**
 * The fixture application sending its link mails from a worker.
 */
class AsyncMailAppKernel extends AppKernel
{
    protected function getConfigFiles(): array
    {
        return [
            ...parent::getConfigFiles(),
            __DIR__ . '/config/async.yaml',
        ];
    }
}
