<?php

namespace Wexample\SymfonyUser\Tests\Fixtures\App\Membership;

use Wexample\SymfonyUser\Interface\MemberInterface;
use Wexample\SymfonyUser\Interface\MembershipGroupInterface;
use Wexample\SymfonyUser\Interface\MembershipInterface;
use Wexample\SymfonyUser\Interface\MembershipManagerInterface;
use Wexample\SymfonyUser\Tests\Fixtures\App\Entity\Membership;
use Wexample\SymfonyUser\Tests\Fixtures\App\Entity\Team;
use Wexample\SymfonyUser\Tests\Fixtures\App\Entity\User;

class TeamMembershipManager implements MembershipManagerInterface
{
    public static function getName(): string
    {
        return 'team';
    }

    public function getGroupClass(): string
    {
        return Team::class;
    }

    public function getMemberClass(): string
    {
        return User::class;
    }

    public function createMembership(MembershipGroupInterface $group, MemberInterface $member, array $grants): MembershipInterface
    {
        return new Membership($group, $member, $grants);
    }

    public function removeMembership(MembershipInterface $membership): void
    {
        $membership->getTeam()->removeMembership($membership);
        $membership->getMember()->removeMembership($membership);
    }
}
