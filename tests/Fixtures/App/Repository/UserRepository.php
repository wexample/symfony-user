<?php

namespace Wexample\SymfonyUser\Tests\Fixtures\App\Repository;

use Wexample\SymfonyUser\Repository\AbstractUserRepository;
use Wexample\SymfonyUser\Tests\Fixtures\App\Entity\User;

class UserRepository extends AbstractUserRepository
{
    public static function getEntityClassName(): string
    {
        return User::class;
    }
}
