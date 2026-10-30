<?php

namespace Wexample\SymfonyUser\Tests\Fixtures\App;

use Wexample\SymfonySearch\WexampleSymfonySearchBundle;

/**
 * The fixture application with teams: administrator ⊃ contributor ⊃ watcher
 * and client ⊃ watcher, roles given by the administrator and watcher grants,
 * the accounts of a team administered by its administrators. Search finds
 * the account a member is added from.
 */
class MembershipAppKernel extends AppKernel
{
    protected function getExtraBundles(): iterable
    {
        return [
            ...parent::getExtraBundles(),
            new WexampleSymfonySearchBundle(),
        ];
    }

    protected function getConfigFiles(): array
    {
        return [
            ...parent::getConfigFiles(),
            __DIR__ . '/config/memberships.yaml',
        ];
    }
}
