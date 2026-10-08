<?php

namespace Wexample\SymfonyUser\Tests\Fixtures\App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Wexample\SymfonyUser\Entity\AbstractUser;
use Wexample\SymfonyUser\Entity\Traits\UserWithNameTrait;
use Wexample\SymfonyUser\Interface\RepresentativeInterface;
use Wexample\SymfonyUser\Tests\Fixtures\App\Repository\UserRepository;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: '`user`')]
class User extends AbstractUser implements RepresentativeInterface
{
    use UserWithNameTrait;

    /**
     * @var Collection<int, Membership>
     */
    #[ORM\OneToMany(targetEntity: Membership::class, mappedBy: 'user')]
    private Collection $memberships;

    /**
     * @var Collection<int, Organization>
     */
    #[ORM\ManyToMany(targetEntity: Organization::class)]
    private Collection $organizations;

    public function __construct()
    {
        parent::__construct();

        $this->memberships = new ArrayCollection();
        $this->organizations = new ArrayCollection();
    }

    public function getMembershipLabel(): string
    {
        return (string) $this->getEmail();
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

    public function getRepresentedMembers(): iterable
    {
        return $this->organizations;
    }

    public function addOrganization(Organization $organization): void
    {
        $this->organizations->add($organization);
    }
}
