<?php

namespace Wexample\SymfonyUser\Tests\Fixtures\App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Wexample\SymfonyHelpers\Entity\AbstractEntity;
use Wexample\SymfonyUser\Entity\Traits\HasGrantsTrait;
use Wexample\SymfonyUser\Interface\MemberInterface;
use Wexample\SymfonyUser\Interface\MembershipGroupInterface;
use Wexample\SymfonyUser\Interface\MembershipInterface;

/**
 * An account or an organization in a team.
 */
#[ORM\Entity]
#[ORM\Table(name: 'membership')]
class Membership extends AbstractEntity implements MembershipInterface
{
    use HasGrantsTrait;

    #[ORM\ManyToOne(inversedBy: 'memberships')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Team $team;

    #[ORM\ManyToOne(inversedBy: 'memberships')]
    #[ORM\JoinColumn(onDelete: 'CASCADE')]
    private ?User $user = null;

    #[ORM\ManyToOne(inversedBy: 'memberships')]
    #[ORM\JoinColumn(onDelete: 'CASCADE')]
    private ?Organization $organization = null;

    /**
     * @param list<string> $grants
     */
    public function __construct(Team $team, User|Organization $member, array $grants)
    {
        parent::__construct();

        $this->team = $team;
        $member instanceof User ? $this->user = $member : $this->organization = $member;
        $this->setGrants($grants);

        $team->addMembership($this);
        $member->addMembership($this);
    }

    public function getMember(): MemberInterface
    {
        return $this->user ?? $this->organization;
    }

    public function getGroup(): MembershipGroupInterface
    {
        return $this->team;
    }

    public function getTeam(): Team
    {
        return $this->team;
    }
}
