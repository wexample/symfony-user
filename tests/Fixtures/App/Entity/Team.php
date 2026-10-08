<?php

namespace Wexample\SymfonyUser\Tests\Fixtures\App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Wexample\SymfonyHelpers\Entity\AbstractEntity;
use Wexample\SymfonyUser\Interface\MembershipGroupInterface;

#[ORM\Entity]
#[ORM\Table(name: 'team')]
class Team extends AbstractEntity implements MembershipGroupInterface
{
    /**
     * @var Collection<int, Membership>
     */
    #[ORM\OneToMany(targetEntity: Membership::class, mappedBy: 'team')]
    private Collection $memberships;

    public function __construct(
        #[ORM\Column]
        private string $name,
    ) {
        parent::__construct();

        $this->memberships = new ArrayCollection();
    }

    public function getMembershipLabel(): string
    {
        return $this->name;
    }

    public function getMemberships(): iterable
    {
        return $this->memberships;
    }

    public function addMembership(Membership $membership): void
    {
        $this->memberships->add($membership);
    }
}
