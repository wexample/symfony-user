<?php

namespace Wexample\SymfonyUser\Service;

use Doctrine\ORM\EntityManagerInterface;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\CacheStorage;
use Symfony\Component\Security\Core\Authentication\Token\SwitchUserToken;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\AccessDecisionManagerInterface;
use Wexample\SymfonyUser\Entity\AbstractUser;
use Wexample\SymfonyUser\Repository\AbstractUserRepository;

/**
 * Who the signed-in user may impersonate, under the `switch_user` key of the
 * firewall — which decides who may impersonate at all: holding its role is
 * enough to impersonate any account, but oneself, a disabled or a locked one.
 *
 * One rule for the list, the search and the switch itself
 * (ImpersonationGuardSubscriber): an account missing from the list cannot
 * be switched to by its URL either.
 */
class ImpersonationService
{
    public const string REFUSAL_SELF = 'self';
    public const string REFUSAL_INACTIVE = 'inactive';

    public const int SEARCH_MIN_LENGTH = AccountDirectoryService::SEARCH_MIN_LENGTH;
    public const int SEARCH_LIMIT = 20;
    public const int SEARCHES_PER_MINUTE = 30;

    /** At most this many accounts read for one search. */
    private const int SEARCH_SCAN = 1000;

    private const string SESSION_INTENT = 'wexample_user_impersonation_intent';
    private const int INTENT_LIFETIME = 60;

    private readonly RateLimiterFactory $searchLimiter;

    public function __construct(
        private readonly Security $security,
        private readonly AccessDecisionManagerInterface $accessDecisionManager,
        private readonly RequestStack $requestStack,
        private readonly EntityManagerInterface $entityManager,
        private readonly AccountDirectoryService $directory,
        #[Autowire(service: 'cache.app')]
        CacheItemPoolInterface $cache,
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
        $token = $this->getActorToken();

        return $config !== null
            && $token?->getUser() instanceof AbstractUser
            && $this->accessDecisionManager->decide($token, [$config['role']]);
    }

    /**
     * Who impersonates: the signed-in user, or — while impersonating — the
     * one behind. Symfony switches from an impersonation by leaving it first,
     * so the rights are always the original account's.
     */
    public function getActor(): ?AbstractUser
    {
        $user = $this->getActorToken()?->getUser();

        return $user instanceof AbstractUser ? $user : null;
    }

    private function getActorToken(): ?TokenInterface
    {
        $token = $this->security->getToken();

        return $token instanceof SwitchUserToken ? $token->getOriginalToken() : $token;
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
        $targets = $this->directory->collect($this->directory->createActiveQuery($actor), $this->accepts($actor), $this->listThreshold + 1);

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
        $builder = $this->directory->createActiveQuery($actor);

        if (! $this->directory->applySearch($builder, $query)
            || ! $this->searchLimiter->create($actor->getUserIdentifier())->consume()->isAccepted()) {
            return [];
        }

        return $this->directory->collect($builder, $this->accepts($actor), self::SEARCH_LIMIT, self::SEARCH_SCAN);
    }

    /**
     * @return callable(AbstractUser): bool
     */
    private function accepts(AbstractUser $actor): callable
    {
        return fn (AbstractUser $target): bool => $this->getRefusal($actor, $target) === null;
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


}
