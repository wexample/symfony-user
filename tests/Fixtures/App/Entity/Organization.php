<?php

namespace Wexample\SymfonyUser\Tests\Fixtures\App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Wexample\SymfonyHelpers\Entity\AbstractEntity;
use Wexample\SymfonyUser\Interface\MemberInterface;

/**
 * A member that is no account: its people hold its grants.
 */
#[ORM\Entity]
#[ORM\Table(name: 'organization')]
class Organization extends AbstractEntity implements MemberInterface
{
    /**
     * @var Collection<int, Membership>
     */
    #[ORM\OneToMany(targetEntity: Membership::class, mappedBy: 'organization')]
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

    public function removeMembership(Membership $membership): void
    {
        $this->memberships->removeElement($membership);
    }
}
