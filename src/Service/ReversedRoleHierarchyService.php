<?php

namespace Wexample\SymfonyUser\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Security\Core\Role\RoleHierarchy;

/**
 * The role hierarchy read upward: which roles include a given one. Asking for
 * the users of ROLE_ADMIN is asking for those of every role above it too.
 */
class ReversedRoleHierarchyService extends RoleHierarchy
{
    /**
     * @param array<string, list<string>> $hierarchy
     */
    public function __construct(
        #[Autowire(param: 'security.role_hierarchy.roles')]
        array $hierarchy
    ) {
        $reversed = [];
        foreach ($hierarchy as $role => $children) {
            foreach ($children as $child) {
                $reversed[$child][] = $role;
            }
        }

        parent::__construct($reversed);
    }

    /**
     * The role itself and every role including it.
     *
     * @return list<string>
     */
    public function getParentRoles(string $role): array
    {
        return array_values(array_unique($this->getReachableRoleNames([$role])));
    }
}
