<?php

namespace Wexample\SymfonyUser\Service;

use Doctrine\Persistence\Proxy;
use Wexample\SymfonyUser\Interface\MemberInterface;
use Wexample\SymfonyUser\Interface\MembershipGroupInterface;
use Wexample\SymfonyUser\Interface\MembershipInterface;
use Wexample\SymfonyUser\Interface\RepresentativeInterface;

/**
 * What a member holds, and where. A representative — an account acting for
 * its organizations — holds the grants of the members it represents beside
 * its own.
 */
class MembershipService
{
    public function __construct(
        private readonly GrantHierarchyService $grantHierarchy,
    ) {
    }

    /**
     * The grants held in $group, the included ones too.
     *
     * @return list<string>
     */
    public function getGrants(MemberInterface $member, MembershipGroupInterface $group): array
    {
        $grants = [];

        foreach ($this->getAllMemberships($member) as $membership) {
            if ($this->isSameGroup($membership->getGroup(), $group)) {
                array_push($grants, ...$membership->getGrants());
            }
        }

        return $this->grantHierarchy->getReachableGrants($grants);
    }

    public function holds(MemberInterface $member, string $grant, MembershipGroupInterface $group): bool
    {
        return in_array($grant, $this->getGrants($member, $group), true);
    }

    /**
     * A member of $group, holding $grant there when one is asked.
     */
    public function isMember(MemberInterface $member, MembershipGroupInterface $group, ?string $grant = null): bool
    {
        if ($grant !== null) {
            return $this->holds($member, $grant, $group);
        }

        foreach ($this->getAllMemberships($member) as $membership) {
            if ($this->isSameGroup($membership->getGroup(), $group)) {
                return true;
            }
        }

        return false;
    }

    /**
     * The groups where $grant is held — or one including it —, every group of
     * the member with no grant asked.
     *
     * @return list<MembershipGroupInterface>
     */
    public function getGroups(MemberInterface $member, ?string $grant = null): array
    {
        $groups = [];

        foreach ($this->getAllMemberships($member) as $membership) {
            $group = $membership->getGroup();

            if ($grant === null || $this->grantHierarchy->includes($membership->getGrants(), $grant)) {
                $groups[$this->getGroupKey($group)] = $group;
            }
        }

        return array_values($groups);
    }

    /**
     * Every grant held somewhere, the included ones too.
     *
     * @return list<string>
     */
    public function getHeldGrants(MemberInterface $member): array
    {
        $grants = [];

        foreach ($this->getAllMemberships($member) as $membership) {
            array_push($grants, ...$membership->getGrants());
        }

        return $this->grantHierarchy->getReachableGrants($grants);
    }

    /**
     * The membership of the member itself in $group, not one it holds through
     * a member it represents.
     */
    public function getMembership(MemberInterface $member, MembershipGroupInterface $group): ?MembershipInterface
    {
        foreach ($member->getMemberships() as $membership) {
            if ($this->isSameGroup($membership->getGroup(), $group)) {
                return $membership;
            }
        }

        return null;
    }

    /**
     * @return iterable<MembershipInterface>
     */
    private function getAllMemberships(MemberInterface $member): iterable
    {
        yield from $member->getMemberships();

        if ($member instanceof RepresentativeInterface) {
            foreach ($member->getRepresentedMembers() as $represented) {
                yield from $represented->getMemberships();
            }
        }
    }

    private function isSameGroup(MembershipGroupInterface $a, MembershipGroupInterface $b): bool
    {
        return $this->getGroupKey($a) === $this->getGroupKey($b);
    }

    /**
     * Two classes may share an id, and a Doctrine proxy is not the class it
     * stands for: the key is the id with the class it was declared on.
     */
    private function getGroupKey(MembershipGroupInterface $group): string
    {
        $class = $group instanceof Proxy
            ? get_parent_class($group)
            : $group::class;

        return $class . '#' . $group->getId();
    }
}
