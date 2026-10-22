<?php

namespace Wexample\SymfonyUser\Tests\Fixtures\App;

/**
 * The fixture application sending managers to /manager, members to /public,
 * anyone else to /protected; a voter keeps users to organization 1.
 */
class PostLoginAppKernel extends AppKernel
{
    protected function getConfigFiles(): array
    {
        return [
            ...parent::getConfigFiles(),
            __DIR__ . '/config/post_login.yaml',
        ];
    }
}
