<?php

namespace Wexample\SymfonyUser\Security\Voter;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Symfony\Component\Security\Core\Role\RoleHierarchyInterface;
use Wexample\SymfonyUser\Interface\MemberInterface;
use Wexample\SymfonyUser\Service\MembershipService;

/**
 * The roles `memberships.roles` gives a grant held in any group, and the
 * roles they reach. A voter rather than roles written into the token: those
 * are read once, at login, and a membership changed would wait for the next
 * one — or sign the account out to apply.
 */
class MembershipRoleVoter extends Voter
{
    /**
     * @var array<string, list<string>> each grant, with the roles it reaches
     */
    private readonly array $grantRoles;

    /**
     * @param array<string, string> $roles
     */
    public function __construct(
        private readonly MembershipService $membershipService,
        RoleHierarchyInterface $roleHierarchy,
        #[Autowire(param: 'wexample_symfony_user.memberships.roles')]
        array $roles = [],
    ) {
        $this->grantRoles = array_map(
            static fn (string $role) => $roleHierarchy->getReachableRoleNames([$role]),
            $roles
        );
    }

    public function supportsAttribute(string $attribute): bool
    {
        foreach ($this->grantRoles as $roles) {
            if (in_array($attribute, $roles, true)) {
                return true;
            }
        }

        return false;
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return $this->supportsAttribute($attribute);
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $member = $token->getUser();

        if (! $member instanceof MemberInterface) {
            return false;
        }

        foreach ($this->membershipService->getHeldGrants($member) as $grant) {
            if (in_array($attribute, $this->grantRoles[$grant] ?? [], true)) {
                return true;
            }
        }

        return false;
    }
}
