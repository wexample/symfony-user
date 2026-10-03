<?php

namespace Wexample\SymfonyUser\Service;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\CacheStorage;
use Wexample\SymfonyHelpers\Helper\RoleHelper;
use Wexample\SymfonyUser\Entity\AbstractUser;
use Wexample\SymfonyUser\Interface\AccountAdministrationGuardInterface;
use Wexample\SymfonyUser\Repository\AbstractUserRepository;

/**
 * Who the signed-in user may impersonate, under the `switch_user` key of the
 * firewall — which decides who may impersonate at all.
 *
 * One rule for the list, the search and the switch itself
 * (ImpersonationGuardSubscriber): an account missing from the list cannot
 * be switched to by its URL either.
 */
class ImpersonationService
{
    public const string TARGETS_ADMINISTERED = 'administered';
    public const string TARGETS_ANY = 'any';

    public const string REFUSAL_SELF = 'self';
    public const string REFUSAL_INACTIVE = 'inactive';
    public const string REFUSAL_NOT_ADMINISTERED = 'not_administered';
    public const string REFUSAL_OUT_OF_SCOPE = 'out_of_scope';

    public const int SEARCH_MIN_LENGTH = 2;
    public const int SEARCH_LIMIT = 20;
    public const int SEARCHES_PER_MINUTE = 30;

    /** Accounts read per query while filtering. */
    private const int BATCH = 200;

    /** At most this many accounts read for one search. */
    private const int SEARCH_SCAN = 1000;

    private const string SESSION_INTENT = 'wexample_user_impersonation_intent';
    private const int INTENT_LIFETIME = 60;

    private readonly RateLimiterFactory $searchLimiter;

    /**
     * @param iterable<AccountAdministrationGuardInterface> $guards
     */
    public function __construct(
        private readonly Security $security,
        private readonly RequestStack $requestStack,
        private readonly EntityManagerInterface $entityManager,
        private readonly AssignableRolesService $assignableRoles,
        #[Autowire(service: 'cache.app')]
        CacheItemPoolInterface $cache,
        #[AutowireIterator(AccountAdministrationGuardInterface::TAG)]
        private readonly iterable $guards = [],
        #[Autowire(param: 'wexample_symfony_user.impersonation.targets')]
        private readonly string $targets = self::TARGETS_ADMINISTERED,
        #[Autowire(param: 'wexample_symfony_user.impersonation.list_threshold')]
        private readonly int $listThreshold = 50,
        #[Autowire(param: 'wexample_symfony_user.impersonation.require_intent')]
        private readonly bool $requireIntent = true,
    ) {
        $this->searchLimiter = new RateLimiterFactory(
            ['id' => 'wexample_user_impersonation_search', 'policy' => 'sliding_window', 'limit' => self::SEARCHES_PER_MINUTE, 'interval' => '1 minute'],
            new CacheStorage($cache)
        );
    }

    /**
     * The `switch_user` key of the firewall of the request, or null: nobody
     * impersonates there.
     *
     * @return array{parameter: string, role: string}|null
     */
    public function getSwitchUserConfig(): ?array
    {
        $request = $this->requestStack->getMainRequest();

        return $request ? $this->security->getFirewallConfig($request)?->getSwitchUser() : null;
    }

    /**
     * The signed-in user holds the role the firewall asks to impersonate.
     */
    public function canImpersonate(): bool
    {
        $config = $this->getSwitchUserConfig();

        return $config !== null
            && $this->security->getUser() instanceof AbstractUser
            && $this->security->isGranted($config['role']);
    }

    /**
     * Why $actor may not impersonate $target, or null.
     */
    public function getRefusal(AbstractUser $actor, AbstractUser $target): ?string
    {
        if ($actor->getId()->equals($target->getId())) {
            return self::REFUSAL_SELF;
        }

        if (! $target->isEnabled() || $target->isLocked()) {
            return self::REFUSAL_INACTIVE;
        }

        if ($this->targets === self::TARGETS_ADMINISTERED
            && ! $this->assignableRoles->canAssign($actor, array_values(array_diff($target->getRoles(), [RoleHelper::ROLE_USER])))) {
            return self::REFUSAL_NOT_ADMINISTERED;
        }

        foreach ($this->guards as $guard) {
            if (! $guard->allows($actor, $target)) {
                return self::REFUSAL_OUT_OF_SCOPE;
            }
        }

        return null;
    }

    /**
     * Every account $actor may impersonate when there are no more than
     * `list_threshold`; null above, the page searches instead.
     *
     * @return list<AbstractUser>|null
     */
    public function listTargets(AbstractUser $actor): ?array
    {
        $targets = $this->collect($actor, $this->createQuery($actor), $this->listThreshold + 1, PHP_INT_MAX);

        return count($targets) > $this->listThreshold ? null : $targets;
    }

    /**
     * The accounts $actor may impersonate matching $query, by email, username
     * or name. Empty under SEARCH_MIN_LENGTH characters, or past
     * SEARCHES_PER_MINUTE searches.
     *
     * @return list<AbstractUser>
     */
    public function searchTargets(AbstractUser $actor, string $query): array
    {
        $query = mb_strtolower(trim($query));

        if (mb_strlen($query) < self::SEARCH_MIN_LENGTH
            || ! $this->searchLimiter->create($actor->getUserIdentifier())->consume()->isAccepted()) {
            return [];
        }

        $builder = $this->createQuery($actor);
        $alias = $builder->getRootAliases()[0];
        $fields = array_filter(
            ['email', 'username', 'firstName', 'lastName'],
            fn (string $field) => $this->entityManager->getClassMetadata($actor::class)->hasField($field)
        );

        $builder
            ->andWhere($builder->expr()->orX(...array_map(
                static fn (string $field) => sprintf("LOWER(%s.%s) LIKE :query ESCAPE '!'", $alias, $field),
                $fields
            )))
            ->setParameter('query', '%' . str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $query) . '%');

        return $this->collect($actor, $builder, self::SEARCH_LIMIT, self::SEARCH_SCAN);
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

    public function findTarget(AbstractUser $actor, string $identifier): ?AbstractUser
    {
        /** @var AbstractUserRepository $repository */
        $repository = $this->entityManager->getRepository($actor::class);
        $target = $repository->findOneByUserIdentifier($identifier);

        return $target && $this->getRefusal($actor, $target) === null ? $target : null;
    }

    /**
     * Remembers that the impersonation form chose $target: the switch that
     * follows needs it (`require_intent`).
     */
    public function grantIntent(AbstractUser $target): void
    {
        $this->requestStack->getSession()->set(self::SESSION_INTENT, [
            'identifier' => $target->getUserIdentifier(),
            'until' => time() + self::INTENT_LIFETIME,
        ]);
    }

    /**
     * Whether the switch to $target was chosen through the form; the intent
     * serves once.
     */
    public function consumeIntent(AbstractUser $target): bool
    {
        if (! $this->requireIntent) {
            return true;
        }

        $session = $this->requestStack->getSession();
        $intent = $session->get(self::SESSION_INTENT);
        $session->remove(self::SESSION_INTENT);

        return is_array($intent)
            && $intent['identifier'] === $target->getUserIdentifier()
            && $intent['until'] >= time();
    }

    private function createQuery(AbstractUser $actor): QueryBuilder
    {
        /** @var AbstractUserRepository $repository */
        $repository = $this->entityManager->getRepository($actor::class);

        return $repository->createQueryBuilder('user')
            ->andWhere('user.enabled = true')
            ->andWhere('user.locked = false')
            ->andWhere('user.id != :actor')
            ->setParameter('actor', $actor->getId(), 'uuid')
            ->orderBy('user.email');
    }

    /**
     * Reads the query by batches, keeping the accounts the rules allow, up to
     * $limit of them or $scan read.
     *
     * @return list<AbstractUser>
     */
    private function collect(AbstractUser $actor, QueryBuilder $builder, int $limit, int $scan): array
    {
        $kept = [];

        for ($offset = 0; $offset < $scan; $offset += self::BATCH) {
            $batch = (clone $builder)->setFirstResult($offset)->setMaxResults(self::BATCH)->getQuery()->getResult();

            foreach ($batch as $user) {
                if ($this->getRefusal($actor, $user) === null) {
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
}
