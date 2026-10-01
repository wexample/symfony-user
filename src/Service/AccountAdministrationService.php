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
 * The way an administrator changes an account: create it, send it a password
 * mail, deactivate, reactivate, lock, unlock, change its roles. Every call
 * checks the rules, applies and flushes, and journals the change — or the
 * refusal, before throwing it.
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
        private readonly PasswordResetService $passwordResetService,
        #[AutowireIterator(AccountAdministrationGuardInterface::TAG)]
        private readonly iterable $guards = [],
        #[Autowire(param: 'wexample_symfony_user.administration.protected_roles')]
        private readonly array $protectedRoles = [],
    ) {
    }

    /**
     * Writes $account — built by the application, with its email and roles —
     * as an account waiting for its holder: enabled, with no password, so
     * that nobody signs in with it until they chose one through the
     * activation mail it is sent.
     *
     * @throws AccountAdministrationException
     */
    public function createAccount(AbstractUser $actor, AbstractUser $account): void
    {
        $roles = $this->getStoredRoles($account->getRoles());

        $this->guard($actor, $account, SecurityEventType::ACCOUNT_CREATED, [], $roles, true, function () use ($account) {
            $account
                ->setPassword(null)
                ->setEnabled(true)
                ->setLocked(false);
            $this->entityManager->persist($account);
        });
        $this->journal->record(SecurityEventType::ACCOUNT_CREATED, $account, extra: [
            'actor_id' => (string) $actor->getId(),
            'roles_after' => implode(',', $roles),
        ]);

        // Once written: a worker may handle the mail before this request ends.
        $this->passwordResetService->sendActivationLink($account);
        $this->journal->record(SecurityEventType::ACCOUNT_ACTIVATION_SENT, $account, extra: ['actor_id' => (string) $actor->getId()]);
    }

    /**
     * Sends again the activation mail of an account waiting for its first
     * password, or a mail to choose a new one to an activated account.
     *
     * @throws AccountAdministrationException
     */
    public function sendPasswordMail(AbstractUser $actor, AbstractUser $target): void
    {
        $roles = $this->getStoredRoles($target->getRoles());
        $pending = $target->getPassword() === null;
        $type = $pending ? SecurityEventType::ACCOUNT_ACTIVATION_SENT : SecurityEventType::ACCOUNT_PASSWORD_MAIL_SENT;

        $this->guard($actor, $target, $type, $roles, $roles, $this->isActive($target), function () use ($target) {
            if (! $this->isActive($target)) {
                throw new AccountAdministrationException(AccountAdministrationRefusal::ACCOUNT_INACTIVE);
            }
        });

        $pending
            ? $this->passwordResetService->sendActivationLink($target)
            : $this->passwordResetService->sendResetLink($target);
        $this->journal->record($type, $target, extra: ['actor_id' => (string) $actor->getId()]);
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

        $this->guard($actor, $target, $type, $rolesBefore, $rolesAfter, $enabled && ! $locked, function () use ($target, $rolesAfter, $enabled, $locked) {
            $target
                ->setRoles($rolesAfter)
                ->setEnabled($enabled)
                ->setLocked($locked);
        });

        $this->journal->record($type, $target, extra: [
            'actor_id' => (string) $actor->getId(),
            ...($type === SecurityEventType::ACCOUNT_ROLES_CHANGED ? [
                'roles_before' => implode(',', $rolesBefore),
                'roles_after' => implode(',', $rolesAfter),
            ] : []),
        ]);
    }

    /**
     * Checks the rules, then applies and flushes, in one transaction; a
     * refusal is journaled before it is thrown. $type names the action.
     *
     * @param list<string> $rolesBefore
     * @param list<string> $rolesAfter
     *
     * @throws AccountAdministrationException
     */
    private function guard(
        AbstractUser $actor,
        AbstractUser $target,
        SecurityEventType $type,
        array $rolesBefore,
        array $rolesAfter,
        bool $activeAfter,
        callable $apply,
    ): void {
        $connection = $this->entityManager->getConnection();
        $connection->beginTransaction();

        try {
            $this->assertAllowed($actor, $target, $rolesBefore, $rolesAfter, $activeAfter);
            $apply();
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
