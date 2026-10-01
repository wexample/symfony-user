<?php

namespace Wexample\SymfonyUser\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Security\Core\Role\RoleHierarchyInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * The roles an editor may grant, remove, and administer the holders of.
 *
 * By default, those they hold, directly or through the hierarchy: an
 * administrator cannot make someone a super administrator. With
 * `administration.manages`, those their roles manage instead — a relation
 * apart from the hierarchy, granting no security right: an administrator
 * may manage the accounts of a role whose pages they must not open.
 */
class AssignableRolesService
{
    /**
     * @param array<string, list<string>> $manages
     */
    public function __construct(
        private readonly RoleHierarchyInterface $roleHierarchy,
        #[Autowire(param: 'wexample_symfony_user.administration.manages')]
        private readonly array $manages = [],
    ) {
    }

    /**
     * @return list<string>
     */
    public function getAssignableRoles(UserInterface $editor): array
    {
        $held = $this->roleHierarchy->getReachableRoleNames($editor->getRoles());

        if (! $this->manages) {
            return array_values(array_unique($held));
        }

        $managed = [];
        foreach ($held as $role) {
            array_push($managed, ...($this->manages[$role] ?? []));
        }

        return array_values(array_unique($managed));
    }

    /**
     * @param list<string> $roles
     */
    public function canAssign(UserInterface $editor, array $roles): bool
    {
        return ! array_diff($roles, $this->getAssignableRoles($editor));
    }
}
