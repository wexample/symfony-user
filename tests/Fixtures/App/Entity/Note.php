<?php

namespace Wexample\SymfonyUser\Tests\Fixtures\App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Wexample\SymfonyHelpers\Entity\AbstractEntity;

/**
 * A record of a team, read by its members only.
 */
#[ORM\Entity]
#[ORM\Table(name: 'note')]
class Note extends AbstractEntity
{
    public function __construct(
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(nullable: false)]
        private Team $team,
        #[ORM\Column]
        private string $text,
    ) {
        parent::__construct();
    }

    public function getTeam(): Team
    {
        return $this->team;
    }

    public function getText(): string
    {
        return $this->text;
    }
}
