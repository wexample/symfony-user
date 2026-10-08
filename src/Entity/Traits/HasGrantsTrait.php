<?php

namespace Wexample\SymfonyUser\Entity\Traits;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * The grants of a membership entity (MembershipInterface), the relations to
 * its member and its group being the application's own.
 */
trait HasGrantsTrait
{
    /**
     * @var list<string>
     */
    #[ORM\Column(type: Types::JSON, options: ['default' => '[]'])]
    protected array $grants = [];

    /**
     * @return list<string>
     */
    public function getGrants(): array
    {
        return $this->grants;
    }

    /**
     * @param list<string> $grants
     */
    public function setGrants(array $grants): static
    {
        $this->grants = array_values(array_unique($grants));

        return $this;
    }
}
