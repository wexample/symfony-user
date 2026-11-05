<?php

namespace Wexample\SymfonyUser\Tests\Fixtures\App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Wexample\SymfonyHelpers\Entity\AbstractEntity;
use Wexample\SymfonyUser\Attribute\AccessScope;

/**
 * Hangs from nothing: the overseeing role alone reaches it.
 */
#[ORM\Entity]
#[ORM\Table(name: 'secret')]
#[AccessScope]
class Secret extends AbstractEntity
{
}
