<?php

namespace Wexample\SymfonyUser\Tests\Fixtures\App\Security;

use Wexample\SymfonyUser\Entity\AbstractUser;
use Wexample\SymfonyUser\Interface\AccountAdministrationGuardInterface;

/**
 * An application scoping administration: the "outsider-" accounts belong to
 * another organization.
 */
class OutsiderAdministrationGuard implements AccountAdministrationGuardInterface
{
    public function allows(AbstractUser $actor, AbstractUser $target): bool
    {
        return ! str_starts_with((string) $target->getUsername(), 'outsider-');
    }
}
