<?php

namespace Wexample\SymfonyUser\Tests\Fixtures\App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Wexample\SymfonyUser\Entity\AbstractUser;
use Wexample\SymfonyUser\Entity\Traits\UserWithNameTrait;
use Wexample\SymfonyUser\Tests\Fixtures\App\Repository\UserRepository;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: '`user`')]
class User extends AbstractUser
{
    use UserWithNameTrait;
}
