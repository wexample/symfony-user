<?php

namespace Wexample\SymfonyUser\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Security\Core\Role\RoleHierarchyInterface;
use Wexample\SymfonyUser\Enum\AccountAdministrationRefusal;
use Wexample\SymfonyUser\Exception\AccountAdministrationException;

/**
 * What an account must always be, whoever changes it — an administrator, the
 * user on their profile, a fixture:
 *
 * - a role restricted to some email domains is held, directly or through the
 *   hierarchy, by an address of one of them;
 * - exclusive roles are never given together. Only the roles given count: an
 *   administrator reaching a role through the hierarchy, to administer its
 *   holders, does not conflict with it.
 */
class AccountRulesService
{
    /**
     * @param array<string, list<string>> $roleEmailDomains
     * @param array<string, list<string>> $exclusiveRoles
     */
    public function __construct(
        private readonly RoleHierarchyInterface $roleHierarchy,
        #[Autowire(param: 'wexample_symfony_user.administration.role_email_domains')]
        private readonly array $roleEmailDomains = [],
        #[Autowire(param: 'wexample_symfony_user.administration.exclusive_roles')]
        private readonly array $exclusiveRoles = [],
    ) {
    }

    /**
     * @param list<string> $roles
     *
     * @throws AccountAdministrationException
     */
    public function assertValid(string $email, array $roles): void
    {
        $held = $this->roleHierarchy->getReachableRoleNames($roles);

        foreach ($this->roleEmailDomains as $role => $domains) {
            if (in_array($role, $held, true) && ! $this->isEmailInDomains($email, $domains)) {
                throw new AccountAdministrationException(AccountAdministrationRefusal::EMAIL_DOMAIN_NOT_ALLOWED, [$role]);
            }
        }

        foreach ($this->exclusiveRoles as $role => $excluded) {
            if (! in_array($role, $roles, true)) {
                continue;
            }

            if ($conflicts = array_values(array_intersect(array_diff($excluded, [$role]), $roles))) {
                throw new AccountAdministrationException(AccountAdministrationRefusal::EXCLUSIVE_ROLES, [$role, ...$conflicts]);
            }
        }
    }

    /**
     * The whole domain, exactly: a subdomain, or an address hiding another
     * "@" before it, is outside.
     *
     * @param list<string> $domains
     */
    public function isEmailInDomains(string $email, array $domains): bool
    {
        $parts = explode('@', mb_strtolower(trim($email)));

        return count($parts) === 2
            && $parts[0] !== ''
            && in_array($parts[1], array_map(mb_strtolower(...), $domains), true);
    }
}
