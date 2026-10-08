<?php

namespace Wexample\SymfonyUser\Service;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Security\Core\Role\RoleHierarchyInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Wexample\SymfonyUser\Entity\AbstractUser;
use Wexample\SymfonyUser\Enum\MembershipAdministrationRefusal;
use Wexample\SymfonyUser\Enum\SecurityEventType;
use Wexample\SymfonyUser\Exception\MembershipAdministrationException;
use Wexample\SymfonyUser\Interface\MemberInterface;
use Wexample\SymfonyUser\Interface\MembershipGroupInterface;
use Wexample\SymfonyUser\Interface\MembershipInterface;

/**
 * Members added to a group, their grants changed, members removed — through
 * here rather than the entities, so that the rules hold and the journal
 * records each change.
 *
 * Who administers a group: an account holding `memberships.administer_grant`
 * in it, or one holding `administration.page_role`, which administers every
 * account already. A group administered by someone always keeps someone
 * administering it.
 */
class MembershipAdministrationService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly MembershipService $membershipService,
        private readonly GrantHierarchyService $grantHierarchy,
        private readonly MembershipManagerRegistry $managers,
        private readonly RoleHierarchyInterface $roleHierarchy,
        private readonly SecurityJournalService $journal,
        #[Autowire(param: 'wexample_symfony_user.memberships.administer_grant')]
        private readonly ?string $administerGrant = null,
        #[Autowire(param: 'wexample_symfony_user.administration.page_role')]
        private readonly ?string $pageRole = null,
    ) {
    }

    public function mayAdminister(AbstractUser $actor, MembershipGroupInterface $group): bool
    {
        if ($this->administerGrant !== null
            && $actor instanceof MemberInterface
            && $this->membershipService->holds($actor, $this->administerGrant, $group)) {
            return true;
        }

        return $this->pageRole !== null
            && in_array($this->pageRole, $this->roleHierarchy->getReachableRoleNames($actor->getRoles()), true);
    }

    /**
     * @param list<string> $grants
     *
     * @throws MembershipAdministrationException
     */
    public function add(AbstractUser $actor, MembershipGroupInterface $group, MemberInterface $member, array $grants): MembershipInterface
    {
        $this->assert($actor, $group, $member, SecurityEventType::MEMBERSHIP_ADDED, [], $grants, function () use ($group, $member, $grants) {
            $this->assertGrants($grants);

            if ($this->membershipService->getMembership($member, $group)) {
                throw new MembershipAdministrationException(MembershipAdministrationRefusal::ALREADY_MEMBER);
            }
        });

        $membership = $this->managers->getForGroup($group)->createMembership($group, $member, $grants);
        $this->entityManager->persist($membership);
        $this->entityManager->flush();

        $this->record(SecurityEventType::MEMBERSHIP_ADDED, $actor, $group, $member, [], $membership->getGrants());

        return $membership;
    }

    /**
     * @param list<string> $grants
     *
     * @throws MembershipAdministrationException
     */
    public function changeGrants(AbstractUser $actor, MembershipInterface $membership, array $grants): void
    {
        $group = $membership->getGroup();
        $before = $membership->getGrants();

        $this->assert($actor, $group, $membership->getMember(), SecurityEventType::MEMBERSHIP_GRANTS_CHANGED, $before, $grants, function () use ($group, $membership, $grants) {
            $this->assertGrants($grants);
            $this->assertAdministered($group, $membership, $grants);
        });

        $membership->setGrants($grants);
        $this->entityManager->flush();

        $this->record(SecurityEventType::MEMBERSHIP_GRANTS_CHANGED, $actor, $group, $membership->getMember(), $before, $membership->getGrants());
    }

    /**
     * @throws MembershipAdministrationException
     */
    public function remove(AbstractUser $actor, MembershipInterface $membership): void
    {
        $group = $membership->getGroup();
        $member = $membership->getMember();
        $before = $membership->getGrants();

        $this->assert($actor, $group, $member, SecurityEventType::MEMBERSHIP_REMOVED, $before, [], function () use ($group, $membership) {
            $this->assertAdministered($group, $membership, []);
        });

        $this->managers->getForGroup($group)->removeMembership($membership);
        $this->entityManager->remove($membership);
        $this->entityManager->flush();

        $this->record(SecurityEventType::MEMBERSHIP_REMOVED, $actor, $group, $member, $before, []);
    }

    /**
     * Checks who acts, then $rules; a refusal is journaled before it is
     * thrown.
     *
     * @param list<string> $before
     * @param list<string> $after
     *
     * @throws MembershipAdministrationException
     */
    private function assert(
        AbstractUser $actor,
        MembershipGroupInterface $group,
        MemberInterface $member,
        SecurityEventType $type,
        array $before,
        array $after,
        callable $rules,
    ): void {
        try {
            if (! $this->mayAdminister($actor, $group)) {
                throw new MembershipAdministrationException(MembershipAdministrationRefusal::GROUP_NOT_ADMINISTERED);
            }

            $rules();
        } catch (MembershipAdministrationException $exception) {
            $this->record(SecurityEventType::MEMBERSHIP_CHANGE_REFUSED, $actor, $group, $member, $before, $after, $exception->refusal->value, ['action' => $type->value]);

            throw $exception;
        }
    }

    /**
     * @param list<string> $grants
     *
     * @throws MembershipAdministrationException
     */
    private function assertGrants(array $grants): void
    {
        if (! $grants || array_filter($grants, fn (string $grant) => ! $this->grantHierarchy->isGrant($grant))) {
            throw new MembershipAdministrationException(MembershipAdministrationRefusal::INVALID_GRANTS);
        }
    }

    /**
     * A group someone administers keeps someone administering it, with
     * $membership holding $grants — none, removed.
     *
     * @param list<string> $grants
     *
     * @throws MembershipAdministrationException
     */
    private function assertAdministered(MembershipGroupInterface $group, MembershipInterface $membership, array $grants): void
    {
        if ($this->administerGrant === null || $this->grantHierarchy->includes($grants, $this->administerGrant)) {
            return;
        }

        foreach ($group->getMemberships() as $other) {
            if ($other !== $membership && $this->grantHierarchy->includes($other->getGrants(), $this->administerGrant)) {
                return;
            }
        }

        if ($this->grantHierarchy->includes($membership->getGrants(), $this->administerGrant)) {
            throw new MembershipAdministrationException(MembershipAdministrationRefusal::LAST_ADMINISTRATOR);
        }
    }

    /**
     * On the account the change is about; with no account — an organization
     * added —, on nobody, the member named in the extra.
     *
     * @param list<string> $before
     * @param list<string> $after
     * @param array<string, string> $extra
     */
    private function record(
        SecurityEventType $type,
        AbstractUser $actor,
        MembershipGroupInterface $group,
        MemberInterface $member,
        array $before,
        array $after,
        ?string $cause = null,
        array $extra = [],
    ): void {
        $this->journal->record($type, $member instanceof UserInterface ? $member : null, $cause, extra: $extra + [
            'actor_id' => (string) $actor->getId(),
            'group' => $group::class . '#' . $group->getId(),
            'group_label' => $group->getMembershipLabel(),
            'member' => $member::class . '#' . $member->getId(),
            'grants_before' => implode(',', $before),
            'grants_after' => implode(',', $after),
        ]);
    }
}
