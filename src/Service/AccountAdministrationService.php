<?php

namespace Wexample\SymfonyUser\Service;

use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Symfony\Component\Security\Core\Role\RoleHierarchyInterface;
use Throwable;
use Wexample\SymfonyHelpers\Helper\RoleHelper;
use Wexample\SymfonyUser\Entity\AbstractUser;
use Wexample\SymfonyUser\Enum\AccountAdministrationRefusal;
use Wexample\SymfonyUser\Enum\SecurityEventType;
use Wexample\SymfonyUser\Exception\AccountAdministrationException;
use Wexample\SymfonyUser\Interface\AccountAdministrationGuardInterface;
use Wexample\SymfonyUser\Repository\AbstractUserRepository;

/**
 * The way an administrator changes an account: deactivate, reactivate, lock,
 * unlock, change its roles. Every call checks the rules, applies and flushes,
 * and journals the change — or the refusal, before throwing it.
 *
 * Who may administer at all is the application's access control; here, the
 * actor only touches the accounts and roles AssignableRolesService gives
 * them, and the pairs every AccountAdministrationGuardInterface allows; never
 * deactivates, locks or demotes themselves; never leaves a protected role
 * without an active holder.
 *
 * The entity setters stay for fixtures and migrations.
 */
class AccountAdministrationService
{
    /**
     * @param iterable<AccountAdministrationGuardInterface> $guards
     * @param list<string> $protectedRoles
     */
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly RoleHierarchyInterface $roleHierarchy,
        private readonly ReversedRoleHierarchyService $reversedRoleHierarchy,
        private readonly AssignableRolesService $assignableRoles,
        private readonly AccountRulesService $accountRules,
        private readonly SecurityJournalService $journal,
        #[AutowireIterator(AccountAdministrationGuardInterface::TAG)]
        private readonly iterable $guards = [],
        #[Autowire(param: 'wexample_symfony_user.administration.protected_roles')]
        private readonly array $protectedRoles = [],
    ) {
    }

    /**
     * @throws AccountAdministrationException
     */
    public function deactivate(AbstractUser $actor, AbstractUser $target): void
    {
        $this->change($actor, $target, SecurityEventType::ACCOUNT_DEACTIVATED, enabled: false);
    }

    /**
     * @throws AccountAdministrationException
     */
    public function reactivate(AbstractUser $actor, AbstractUser $target): void
    {
        $this->change($actor, $target, SecurityEventType::ACCOUNT_REACTIVATED, enabled: true);
    }

    /**
     * @throws AccountAdministrationException
     */
    public function lock(AbstractUser $actor, AbstractUser $target): void
    {
        $this->change($actor, $target, SecurityEventType::ACCOUNT_LOCKED, locked: true);
    }

    /**
     * @throws AccountAdministrationException
     */
    public function unlock(AbstractUser $actor, AbstractUser $target): void
    {
        $this->change($actor, $target, SecurityEventType::ACCOUNT_UNLOCKED, locked: false);
    }

    /**
     * @param list<string> $roles the whole set the target holds afterwards
     *
     * @throws AccountAdministrationException
     */
    public function changeRoles(AbstractUser $actor, AbstractUser $target, array $roles): void
    {
        $this->change($actor, $target, SecurityEventType::ACCOUNT_ROLES_CHANGED, roles: $roles);
    }

    /**
     * @param list<string>|null $roles
     */
    private function change(
        AbstractUser $actor,
        AbstractUser $target,
        SecurityEventType $type,
        ?array $roles = null,
        ?bool $enabled = null,
        ?bool $locked = null,
    ): void {
        $rolesBefore = $this->getStoredRoles($target->getRoles());
        $rolesAfter = $roles === null ? $rolesBefore : $this->getStoredRoles($roles);
        $enabled ??= $target->isEnabled();
        $locked ??= $target->isLocked();

        if ($rolesAfter === $rolesBefore && $enabled === $target->isEnabled() && $locked === $target->isLocked()) {
            return;
        }

        $connection = $this->entityManager->getConnection();
        $connection->beginTransaction();

        try {
            $this->assertAllowed($actor, $target, $rolesBefore, $rolesAfter, $enabled && ! $locked);

            $target
                ->setRoles($rolesAfter)
                ->setEnabled($enabled)
                ->setLocked($locked);
            $this->entityManager->flush();

            $connection->commit();
        } catch (AccountAdministrationException $exception) {
            $connection->rollBack();

            $this->journal->record(
                SecurityEventType::ACCOUNT_CHANGE_REFUSED,
                $target,
                $exception->refusal->value,
                extra: [
                    'action' => $type->value,
                    'actor_id' => (string) $actor->getId(),
                    'roles' => implode(',', $exception->roles),
                ]
            );

            throw $exception;
        } catch (Throwable $exception) {
            $connection->rollBack();

            throw $exception;
        }

        $this->journal->record($type, $target, extra: [
            'actor_id' => (string) $actor->getId(),
            ...($type === SecurityEventType::ACCOUNT_ROLES_CHANGED ? [
                'roles_before' => implode(',', $rolesBefore),
                'roles_after' => implode(',', $rolesAfter),
            ] : []),
        ]);
    }

    /**
     * @param list<string> $rolesBefore
     * @param list<string> $rolesAfter
     *
     * @throws AccountAdministrationException
     */
    private function assertAllowed(
        AbstractUser $actor,
        AbstractUser $target,
        array $rolesBefore,
        array $rolesAfter,
        bool $activeAfter,
    ): void {
        $assignable = $this->assignableRoles->getAssignableRoles($actor);
        $removed = array_values(array_diff($rolesBefore, $rolesAfter));
        $added = array_values(array_diff($rolesAfter, $rolesBefore));

        if ($actor->getId()->equals($target->getId())) {
            if (! $activeAfter) {
                throw new AccountAdministrationException(AccountAdministrationRefusal::SELF_DEACTIVATION);
            }

            if ($removed) {
                throw new AccountAdministrationException(AccountAdministrationRefusal::SELF_DEMOTION, $removed);
            }
        }

        // Neither an account holding a role the actor does not administer,
        // nor such a role given.
        if ($outOfReach = array_values(array_diff([...$rolesBefore, ...$added], $assignable))) {
            throw new AccountAdministrationException(AccountAdministrationRefusal::ROLE_NOT_ASSIGNABLE, $outOfReach);
        }

        foreach ($this->guards as $guard) {
            if (! $guard->allows($actor, $target)) {
                throw new AccountAdministrationException(AccountAdministrationRefusal::TARGET_OUT_OF_SCOPE);
            }
        }

        $heldBefore = $this->isActive($target) ? $this->roleHierarchy->getReachableRoleNames($rolesBefore) : [];
        $heldAfter = $activeAfter ? $this->roleHierarchy->getReachableRoleNames($rolesAfter) : [];

        foreach (array_diff($this->protectedRoles, $heldAfter) as $role) {
            if (in_array($role, $heldBefore, true) && ! $this->hasOtherActiveHolder($target, $role)) {
                throw new AccountAdministrationException(AccountAdministrationRefusal::LAST_PROTECTED_ROLE_HOLDER, [$role]);
            }
        }

        $this->accountRules->assertValid((string) $target->getEmail(), $rolesAfter);
    }

    /**
     * Counted under a lock on every active holder, the target included: of
     * two administrators deactivating each other at once, the second waits
     * for the first, then finds no other holder left.
     */
    private function hasOtherActiveHolder(AbstractUser $target, string $role): bool
    {
        /** @var AbstractUserRepository $repository */
        $repository = $this->entityManager->getRepository($target::class);

        $holders = $repository
            ->queryByRoles($this->reversedRoleHierarchy->getParentRoles($role))
            ->andWhere('user.enabled = true')
            ->andWhere('user.locked = false')
            ->orderBy('user.id')
            ->getQuery()
            ->setLockMode(LockMode::PESSIMISTIC_WRITE)
            ->getResult();

        foreach ($holders as $holder) {
            if (! $holder->getId()->equals($target->getId())) {
                return true;
            }
        }

        return false;
    }

    private function isActive(AbstractUser $user): bool
    {
        return $user->isEnabled() && ! $user->isLocked();
    }

    /**
     * @param list<string> $roles
     *
     * @return list<string>
     */
    private function getStoredRoles(array $roles): array
    {
        $roles = array_values(array_unique(array_diff($roles, [RoleHelper::ROLE_USER])));
        sort($roles);

        return $roles;
    }
}
