<?php

namespace Wexample\SymfonyUser\Repository;

use Symfony\Bridge\Doctrine\Security\User\UserLoaderInterface;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;
use Wexample\SymfonyHelpers\Repository\AbstractRepository;
use Wexample\SymfonyUser\Entity\AbstractUser;

abstract class AbstractUserRepository extends AbstractRepository implements
    UserLoaderInterface,
    PasswordUpgraderInterface
{
    public function loadUserByIdentifier(string $identifier): ?AbstractUser
    {
        return $this->findOneByUserIdentifier($identifier);
    }

    /**
     * The identifier typed at login: an email, or a username.
     */
    public function findOneByUserIdentifier(string $identifier): ?AbstractUser
    {
        $identifier = mb_strtolower(trim($identifier));

        if ($identifier === '') {
            return null;
        }

        // A username cannot hold "@", so the two lookups never overlap.
        return $this->findOneBy([
            str_contains($identifier, '@') ? 'email' : 'username' => $identifier,
        ]);
    }

    /**
     * Rehashes the password when the hasher configuration has changed.
     */
    public function upgradePassword(PasswordAuthenticatedUserInterface $user, string $newHashedPassword): void
    {
        if (! $user instanceof AbstractUser) {
            throw new UnsupportedUserException(sprintf('Instances of "%s" are not supported.', $user::class));
        }

        $user->setPassword($newHashedPassword);
        $this->getEntityManager()->flush();
    }
}
