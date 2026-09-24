<?php

namespace Wexample\SymfonyUser\Entity\Traits;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * First and last name for an AbstractUser, and the name shown for it.
 */
trait UserWithNameTrait
{
    #[ORM\Column(type: Types::STRING, length: 100, nullable: true)]
    #[Assert\Length(max: 100)]
    protected ?string $firstName = null;

    #[ORM\Column(type: Types::STRING, length: 100, nullable: true)]
    #[Assert\Length(max: 100)]
    protected ?string $lastName = null;

    public function getFirstName(): ?string
    {
        return $this->firstName;
    }

    public function setFirstName(?string $firstName): static
    {
        $this->firstName = $firstName;

        return $this;
    }

    public function getLastName(): ?string
    {
        return $this->lastName;
    }

    public function setLastName(?string $lastName): static
    {
        $this->lastName = $lastName;

        return $this;
    }

    public function getDisplayName(bool $short = false): string
    {
        if ($this->firstName) {
            return $short || ! $this->lastName
                ? $this->firstName
                : $this->firstName . ' ' . $this->lastName;
        }

        return $this->getUsername() ?? $this->getUserIdentifier();
    }
}
