<?php

namespace Wexample\SymfonyUser\Tests\Fixtures\App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Wexample\SymfonyHelpers\Entity\AbstractEntity;
use Wexample\SymfonyUser\Attribute\AccessScope;

/**
 * A comment on a note: changed by its author, otherwise as its note is.
 */
#[ORM\Entity]
#[ORM\Table(name: 'note_comment')]
#[AccessScope(owner: 'author', parents: ['note'])]
class NoteComment extends AbstractEntity
{
    public function __construct(
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(nullable: false)]
        private Note $note,
        #[ORM\ManyToOne]
        private ?User $author = null,
    ) {
        parent::__construct();
    }

    public function getNote(): Note
    {
        return $this->note;
    }

    public function getAuthor(): ?User
    {
        return $this->author;
    }
}
