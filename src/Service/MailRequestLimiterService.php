<?php

namespace Wexample\SymfonyUser\Service;

use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\CacheStorage;

/**
 * The requests that mail a link to an address typed by anyone — password
 * reset, magic link —, limited per address and per IP, so a mailbox cannot
 * be flooded. Whether the address has an account or not, it counts the same:
 * the limit tells nothing about which accounts exist.
 */
class MailRequestLimiterService
{
    private readonly RateLimiterFactory $identifierLimiter;

    private readonly RateLimiterFactory $ipLimiter;

    public function __construct(
        private readonly RequestStack $requestStack,
        private readonly SecurityJournalService $journal,
        #[Autowire(service: 'cache.app')]
        CacheItemPoolInterface $cache,
        #[Autowire(param: 'wexample_symfony_user.request_limit.per_identifier')]
        int $perIdentifier = 3,
        #[Autowire(param: 'wexample_symfony_user.request_limit.per_ip')]
        int $perIp = 20,
    ) {
        $storage = new CacheStorage($cache);

        $this->identifierLimiter = new RateLimiterFactory(
            ['id' => 'wexample_user_mail_request_identifier', 'policy' => 'sliding_window', 'limit' => $perIdentifier, 'interval' => '1 hour'],
            $storage
        );
        $this->ipLimiter = new RateLimiterFactory(
            ['id' => 'wexample_user_mail_request_ip', 'policy' => 'sliding_window', 'limit' => $perIp, 'interval' => '1 hour'],
            $storage
        );
    }

    /**
     * Counts one request for $identifier, and says whether it may be sent.
     */
    public function consume(string $identifier): bool
    {
        $ip = (string) $this->requestStack->getMainRequest()?->getClientIp();

        // Both are counted, so a refused request still weighs on the other.
        $identifierAccepted = $this->identifierLimiter->create($this->journal->fingerprint($identifier))->consume()->isAccepted();
        $ipAccepted = $this->ipLimiter->create($ip)->consume()->isAccepted();

        return $identifierAccepted && $ipAccepted;
    }
}
