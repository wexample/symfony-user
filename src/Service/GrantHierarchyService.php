<?php

namespace Wexample\SymfonyUser\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * The grants the application declared under `memberships.grants`, and what
 * each one includes — the way the role hierarchy does for roles.
 */
class GrantHierarchyService
{
    /**
     * @param array<string, list<string>> $grants
     */
    public function __construct(
        #[Autowire(param: 'wexample_symfony_user.memberships.grants')]
        private readonly array $grants = [],
    ) {
    }

    /**
     * In the order they were declared, which the screens follow.
     *
     * @return list<string>
     */
    public function getGrants(): array
    {
        return array_keys($this->grants);
    }

    public function isGrant(string $grant): bool
    {
        return isset($this->grants[$grant]);
    }

    /**
     * The grants given and every one they include, undeclared ones dropped.
     *
     * @param list<string> $grants
     *
     * @return list<string>
     */
    public function getReachableGrants(array $grants): array
    {
        $reached = [];
        $pending = array_values(array_filter($grants, $this->isGrant(...)));

        while ($pending) {
            $grant = array_pop($pending);

            if (! isset($reached[$grant])) {
                $reached[$grant] = true;
                array_push($pending, ...$this->grants[$grant]);
            }
        }

        return array_keys($reached);
    }

    /**
     * @param list<string> $held
     */
    public function includes(array $held, string $grant): bool
    {
        return in_array($grant, $this->getReachableGrants($held), true);
    }
}
