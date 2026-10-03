<?php

namespace Wexample\SymfonyUser\Service;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use LogicException;
use Wexample\SymfonyHelpers\Helper\RoleHelper;
use Wexample\SymfonyUser\Entity\AbstractUser;
use Wexample\SymfonyUser\Repository\AbstractUserRepository;

/**
 * The active accounts, read to be chosen from: by batches, filtered by a
 * rule, searched by email, username or name, described for a list.
 * ImpersonationService and AccountPickerService read through it.
 */
class AccountDirectoryService
{
    public const int SEARCH_MIN_LENGTH = 2;

    /** Accounts read per query while filtering. */
    private const int BATCH = 200;

    private ?string $userClass = null;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * The application's user class: the one mapped entity extending
     * AbstractUser.
     *
     * @return class-string<AbstractUser>
     */
    public function getUserClass(): string
    {
        if ($this->userClass) {
            return $this->userClass;
        }

        $classes = [];
        foreach ($this->entityManager->getMetadataFactory()->getAllMetadata() as $metadata) {
            if (is_subclass_of($metadata->getName(), AbstractUser::class) && ! $metadata->getReflectionClass()->isAbstract()) {
                $classes[] = $metadata->getName();
            }
        }

        if (count($classes) !== 1) {
            throw new LogicException(sprintf('Expected one entity extending %s, found %d.', AbstractUser::class, count($classes)));
        }

        return $this->userClass = $classes[0];
    }

    /**
     * Enabled and unlocked accounts, $except left out, by email.
     */
    public function createActiveQuery(?AbstractUser $except = null): QueryBuilder
    {
        /** @var AbstractUserRepository $repository */
        $repository = $this->entityManager->getRepository($except ? $except::class : $this->getUserClass());

        $builder = $repository->createQueryBuilder('user')
            ->andWhere('user.enabled = true')
            ->andWhere('user.locked = false')
            ->orderBy('user.email');

        if ($except) {
            $builder->andWhere('user.id != :except')->setParameter('except', $except->getId(), 'uuid');
        }

        return $builder;
    }

    /**
     * The enabled, unlocked account $identifier names, or null.
     */
    public function findActive(string $identifier): ?AbstractUser
    {
        /** @var AbstractUserRepository $repository */
        $repository = $this->entityManager->getRepository($this->getUserClass());
        $user = $repository->findOneByUserIdentifier($identifier);

        return $user && $user->isEnabled() && ! $user->isLocked() ? $user : null;
    }

    /**
     * Narrows $builder to the accounts whose email, username or name holds
     * $query; false when $query is shorter than SEARCH_MIN_LENGTH.
     */
    public function applySearch(QueryBuilder $builder, string $query): bool
    {
        $query = mb_strtolower(trim($query));

        if (mb_strlen($query) < self::SEARCH_MIN_LENGTH) {
            return false;
        }

        $alias = $builder->getRootAliases()[0];
        $metadata = $this->entityManager->getClassMetadata($builder->getRootEntities()[0]);
        $fields = array_filter(['email', 'username', 'firstName', 'lastName'], $metadata->hasField(...));

        $builder
            ->andWhere($builder->expr()->orX(...array_map(
                static fn (string $field) => sprintf("LOWER(%s.%s) LIKE :query ESCAPE '!'", $alias, $field),
                $fields
            )))
            ->setParameter('query', '%' . str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $query) . '%');

        return true;
    }

    /**
     * Reads $builder by batches, keeping the accounts $accept takes, up to
     * $limit of them or $scan read.
     *
     * @param callable(AbstractUser): bool|null $accept
     *
     * @return list<AbstractUser>
     */
    public function collect(QueryBuilder $builder, ?callable $accept, int $limit, int $scan = PHP_INT_MAX): array
    {
        $kept = [];

        for ($offset = 0; $offset < $scan; $offset += self::BATCH) {
            $batch = (clone $builder)->setFirstResult($offset)->setMaxResults(self::BATCH)->getQuery()->getResult();

            foreach ($batch as $user) {
                if ($accept === null || $accept($user)) {
                    $kept[] = $user;

                    if (count($kept) >= $limit) {
                        return $kept;
                    }
                }
            }

            if (count($batch) < self::BATCH) {
                break;
            }
        }

        return $kept;
    }

    /**
     * What tells an account apart in a list: its name when it has one, its
     * email, its roles.
     *
     * @return array{identifier: string, label: string, email: string, roles: list<string>}
     */
    public function describe(AbstractUser $user): array
    {
        $email = (string) $user->getEmail();
        $name = method_exists($user, 'getDisplayName') ? trim((string) $user->getDisplayName()) : '';

        return [
            'identifier' => $user->getUserIdentifier(),
            'label' => $name !== '' && $name !== $email ? $name . ' — ' . $email : $email,
            'email' => $email,
            'roles' => array_values(array_diff($user->getRoles(), [RoleHelper::ROLE_USER])),
        ];
    }

    /**
     * Label => identifier, the choices of a select of accounts.
     *
     * @param iterable<AbstractUser> $users
     *
     * @return array<string, string>
     */
    public function toChoices(iterable $users): array
    {
        $choices = [];

        foreach ($users as $user) {
            $description = $this->describe($user);
            $choices[$description['label'] . ($description['roles'] ? ' (' . implode(', ', $description['roles']) . ')' : '')] = $description['identifier'];
        }

        return $choices;
    }
}
