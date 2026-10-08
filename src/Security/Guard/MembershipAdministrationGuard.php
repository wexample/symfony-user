<?php

namespace Wexample\SymfonyUser\Security\Guard;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Wexample\SymfonyUser\Entity\AbstractUser;
use Wexample\SymfonyUser\Interface\AccountAdministrationGuardInterface;
use Wexample\SymfonyUser\Interface\MemberInterface;
use Wexample\SymfonyUser\Service\MembershipService;

/**
 * An account administering groups — holding `memberships.administer_grant`
 * in them — reaches the accounts all of whose groups it administers. One
 * group it does not would be cut off by a deactivation decided elsewhere.
 * An account with no group is out of reach too, but for one being created:
 * its memberships come once it exists.
 *
 * An actor holding the grant nowhere — a platform administrator — is left to
 * the rules of `administration`.
 */
class MembershipAdministrationGuard implements AccountAdministrationGuardInterface
{
    public function __construct(
        private readonly MembershipService $membershipService,
        private readonly EntityManagerInterface $entityManager,
        #[Autowire(param: 'wexample_symfony_user.memberships.administer_grant')]
        private readonly ?string $administerGrant = null,
    ) {
    }

    public function allows(AbstractUser $actor, AbstractUser $target): bool
    {
        if ($this->administerGrant === null || ! $actor instanceof MemberInterface) {
            return true;
        }

        $administered = $this->membershipService->getGroups($actor, $this->administerGrant);

        if (! $administered) {
            return true;
        }

        if (! $this->entityManager->contains($target)) {
            return true;
        }

        $groups = $target instanceof MemberInterface ? $this->membershipService->getGroups($target) : [];

        if (! $groups) {
            return false;
        }

        foreach ($groups as $group) {
            if (! $this->membershipService->holds($actor, $this->administerGrant, $group)) {
                return false;
            }
        }

        return true;
    }
}
