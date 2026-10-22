<?php

namespace Wexample\SymfonyUser\Repository;

use Doctrine\ORM\QueryBuilder;
use Symfony\Bridge\Doctrine\Security\User\UserLoaderInterface;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;
use Wexample\SymfonyHelpers\Helper\RoleHelper;
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
     * The users holding one of $roles. A role only matches itself: ROLE_ADMIN
     * does not match ROLE_SUPER_ADMIN, so pass
     * ReversedRoleHierarchyService::getParentRoles() to include the roles above.
     *
     * @param list<string> $roles
     */
    public function queryByRoles(
        array $roles,
        ?QueryBuilder $builder = null
    ): QueryBuilder {
        $builder ??= $this->createQueryBuilder('user');
        $alias = $builder->getRootAliases()[0];

        // Never stored, always held.
        if (in_array(RoleHelper::ROLE_USER, $roles, true)) {
            return $builder;
        }

        $conditions = $builder->expr()->orX();

        foreach (array_values($roles) as $index => $role) {
            // Matched with its JSON quotes, so the whole element and nothing
            // else. CONCAT turns the JSON column into text for every platform.
            $conditions->add(sprintf("CONCAT(%s.roles, '') LIKE :role_%d ESCAPE '!'", $alias, $index));
            $builder->setParameter(
                'role_' . $index,
                '%' . str_replace(['!', '%', '_'], ['!!', '!%', '!_'], json_encode($role)) . '%'
            );
        }

        return $builder->andWhere($conditions->count() ? $conditions : '1 = 0');
    }

    /**
     * @param list<string> $roles
     *
     * @return list<AbstractUser>
     */
    public function findByRoles(array $roles): array
    {
        return $this->queryByRoles($roles)->getQuery()->getResult();
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
