<?php

namespace Wexample\SymfonyUser\Tests\Fixtures\App;

/**
 * The fixture application with teams: administrator ⊃ contributor ⊃ watcher
 * and client ⊃ watcher, roles given by the administrator and watcher grants,
 * the accounts of a team administered by its administrators.
 */
class MembershipAppKernel extends AppKernel
{
    protected function getConfigFiles(): array
    {
        return [
            ...parent::getConfigFiles(),
            __DIR__ . '/config/memberships.yaml',
        ];
    }
}
