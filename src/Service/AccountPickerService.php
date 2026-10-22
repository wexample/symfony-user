<?php

namespace Wexample\SymfonyUser\Service;

use Psr\Cache\CacheItemPoolInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\CacheStorage;
use Wexample\SymfonyUser\Entity\AbstractUser;
use Wexample\SymfonyUser\Security\Authenticator\AccountPickerAuthenticator;

/**
 * Signing in by choosing an account, without a password: for an application
 * whose users trust each other, or a demonstration. It exists where the
 * firewall lists AccountPickerAuthenticator — the one switch, in the
 * security configuration; elsewhere the page answers 404 and nothing signs
 * in.
 */
class AccountPickerService
{
    public const int SEARCH_LIMIT = 20;
    public const int SEARCHES_PER_MINUTE = 30;
    private const int SEARCH_SCAN = 1000;

    private readonly RateLimiterFactory $searchLimiter;

    public function __construct(
        private readonly Security $security,
        private readonly RequestStack $requestStack,
        private readonly AccountDirectoryService $directory,
        #[Autowire(service: 'cache.app')]
        CacheItemPoolInterface $cache,
        #[Autowire(param: 'wexample_symfony_user.impersonation.list_threshold')]
        private readonly int $listThreshold = 50,
    ) {
        $this->searchLimiter = new RateLimiterFactory(
            ['id' => 'wexample_user_account_picker_search', 'policy' => 'sliding_window', 'limit' => self::SEARCHES_PER_MINUTE, 'interval' => '1 minute'],
            new CacheStorage($cache)
        );
    }

    public function isEnabled(): bool
    {
        $request = $this->requestStack->getMainRequest();

        return $request !== null
            && in_array(AccountPickerAuthenticator::class, $this->security->getFirewallConfig($request)?->getAuthenticators() ?? [], true);
    }

    /**
     * Every active account when there are no more than `list_threshold`;
     * null above, the page searches instead.
     *
     * @return list<AbstractUser>|null
     */
    public function listAccounts(): ?array
    {
        $accounts = $this->directory->collect($this->directory->createActiveQuery(), null, $this->listThreshold + 1);

        return count($accounts) > $this->listThreshold ? null : $accounts;
    }

    /**
     * The active accounts matching $query; limited per IP.
     *
     * @return list<AbstractUser>
     */
    public function searchAccounts(string $query): array
    {
        $builder = $this->directory->createActiveQuery();

        if (! $this->directory->applySearch($builder, $query)
            || ! $this->searchLimiter->create((string) $this->requestStack->getMainRequest()?->getClientIp())->consume()->isAccepted()) {
            return [];
        }

        return $this->directory->collect($builder, null, self::SEARCH_LIMIT, self::SEARCH_SCAN);
    }

    /**
     * The active account $identifier names, or null.
     */
    public function findAccount(string $identifier): ?AbstractUser
    {
        return $this->directory->findActive($identifier);
    }
}
