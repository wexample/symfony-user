<?php

namespace Wexample\SymfonyUser\Repository;

use Wexample\SymfonyUser\Entity\User;

class UserRepository extends AbstractUserRepository
{
    public static function getEntityClassName(): string
    {
        return User::class;
    }
}
