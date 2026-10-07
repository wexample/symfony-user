<?php

namespace Wexample\SymfonyUser\Service;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\Tools\Pagination\Paginator;
use LogicException;
use Symfony\Component\Uid\Uuid;
use Wexample\SymfonyHelpers\Helper\RoleHelper;
use Wexample\SymfonyUser\Entity\AbstractUser;
use Wexample\SymfonyUser\Repository\AbstractUserRepository;

/**
 * The accounts, read to be chosen from or to be administered: by batches,
 * filtered by a rule, searched by email, username or name, described for a
 * list. ImpersonationService and AccountPickerService read through it, and so
 * do the administration pages.
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
     * Every account, by email: what an administration list reads, where a
     * disabled or locked one has to be found to be put back.
     */
    public function createQuery(): QueryBuilder
    {
        /** @var AbstractUserRepository $repository */
        $repository = $this->entityManager->getRepository($this->getUserClass());

        return $repository->createQueryBuilder('user')->orderBy('user.email');
    }

    /**
     * Enabled and unlocked accounts, $except left out, by email.
     */
    public function createActiveQuery(?AbstractUser $except = null): QueryBuilder
    {
        $builder = $this->createQuery()
            ->andWhere('user.enabled = true')
            ->andWhere('user.locked = false');

        if ($except) {
            $builder->andWhere('user.id != :except')->setParameter('except', $except->getId(), 'uuid');
        }

        return $builder;
    }

    /**
     * The account an id names, or null — a string that is no id included:
     * what a page reads its `{id}` with, to answer 404 rather than break on
     * a hand-typed URL.
     */
    public function find(string $id): ?AbstractUser
    {
        return Uuid::isValid($id)
            ? $this->entityManager->getRepository($this->getUserClass())->find($id)
            : null;
    }

    /**
     * The account $identifier names — an email or a username —, whatever its
     * state: what an administration screen looks an address up with before
     * opening a second account on it.
     */
    public function findByIdentifier(string $identifier): ?AbstractUser
    {
        /** @var AbstractUserRepository $repository */
        $repository = $this->entityManager->getRepository($this->getUserClass());

        return $repository->findOneByUserIdentifier($identifier);
    }

    /**
     * The enabled, unlocked account $identifier names, or null.
     */
    public function findActive(string $identifier): ?AbstractUser
    {
        $user = $this->findByIdentifier($identifier);

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
     * One page of a query, and how many pages it has: what a list shows,
     * without the page it is drawn on knowing Doctrine.
     *
     * @return array{accounts: list<AbstractUser>, count: int, pages_count: int}
     */
    public function paginate(QueryBuilder $builder, int $page, int $perPage): array
    {
        $paginator = new Paginator(
            $builder->getQuery()
                ->setFirstResult(max(0, $page - 1) * $perPage)
                ->setMaxResults($perPage)
        );
        $count = count($paginator);

        return [
            'accounts' => iterator_to_array($paginator, false),
            'count' => $count,
            'pages_count' => (int) ceil($count / $perPage),
        ];
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
