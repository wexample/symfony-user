<?php

namespace Wexample\SymfonyUser\Tests\Fixtures\App;

/**
 * The fixture application signing out a session left alone for a quarter of
 * an hour.
 */
class IdleSessionAppKernel extends AppKernel
{
    protected function getConfigFiles(): array
    {
        return [
            ...parent::getConfigFiles(),
            __DIR__ . '/config/idle_session.yaml',
        ];
    }
}
