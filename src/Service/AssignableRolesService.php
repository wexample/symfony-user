<?php

namespace Wexample\SymfonyUser\Service;

use Symfony\Component\Security\Core\Role\RoleHierarchyInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * The roles an editor may grant: only those they hold, directly or through
 * the hierarchy. An administrator cannot make someone a super administrator.
 */
class AssignableRolesService
{
    public function __construct(
        private readonly RoleHierarchyInterface $roleHierarchy
    ) {
    }

    /**
     * @return list<string>
     */
    public function getAssignableRoles(UserInterface $editor): array
    {
        return array_values(array_unique(
            $this->roleHierarchy->getReachableRoleNames($editor->getRoles())
        ));
    }

    /**
     * @param list<string> $roles
     */
    public function canAssign(UserInterface $editor, array $roles): bool
    {
        return ! array_diff($roles, $this->getAssignableRoles($editor));
    }
}
