<?php

namespace Wexample\SymfonyUser\Entity;

use Doctrine\ORM\Mapping as ORM;
use Wexample\SymfonyUser\Repository\UserRepository;

/**
 * The account as the package ships it, ready to use: an application with
 * nothing to add to AbstractUser names it as its security provider and
 * writes no class. One that adds fields extends AbstractUser instead and
 * names its class in `user_class` (or its security provider): this one is
 * then left out of the Doctrine mapping, so the two never share a table.
 */
#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: '`user`')]
class User extends AbstractUser
{
}
