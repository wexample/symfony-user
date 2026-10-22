<?php

namespace Wexample\SymfonyUser\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Security\Core\Role\RoleHierarchyInterface;
use Wexample\SymfonyUser\Entity\AbstractUser;

/**
 * What the application demands of the second factor, from
 * `wexample_symfony_user.two_factor`.
 */
class TwoFactorPolicyService
{
    /**
     * @param list<string> $appRequiredRoles
     */
    public function __construct(
        private readonly RoleHierarchyInterface $roleHierarchy,
        #[Autowire(param: 'wexample_symfony_user.two_factor.required')]
        private readonly bool $required = false,
        #[Autowire(param: 'wexample_symfony_user.two_factor.app_required_roles')]
        private readonly array $appRequiredRoles = [],
    ) {
    }

    /**
     * Whether $user is asked a second factor on this sign-in. A password
     * login always asks it of an account not exempt; under a required policy,
     * every sign-in does, of every account.
     */
    public function asksSecondFactor(AbstractUser $user, bool $passwordLogin): bool
    {
        if ($this->required || $this->requiresApp($user)) {
            return true;
        }

        return $passwordLogin && $user->isEmailTwoFactorEnabled();
    }

    /**
     * Whether $user holds a role, directly or through the hierarchy, that
     * must use an authenticator app.
     */
    public function requiresApp(AbstractUser $user): bool
    {
        if (! $this->appRequiredRoles) {
            return false;
        }

        return (bool) array_intersect(
            $this->appRequiredRoles,
            $this->roleHierarchy->getReachableRoleNames($user->getRoles())
        );
    }

    /**
     * Signed in, but held on the setup page until the app is there.
     */
    public function mustSetAppUp(AbstractUser $user): bool
    {
        return $this->requiresApp($user) && ! $user->isTotpAuthenticationEnabled();
    }
}
