<?php

namespace Wexample\SymfonyUser\EventSubscriber;

use Psr\Cache\CacheItemPoolInterface;
use Scheb\TwoFactorBundle\Security\Authentication\Token\TwoFactorTokenInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Authentication\AuthenticationTrustResolverInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Wexample\SymfonyUser\Enum\SecurityEventType;
use Wexample\SymfonyUser\Service\SecurityJournalService;

/**
 * Journals every access refused to a fully signed-in user — `access_control`,
 * `#[IsGranted]`, a voter, `denyAccessUnlessGranted()` — as `access.denied`:
 * route, path without its query, HTTP method, roles, the attributes refused,
 * and the reasons the voters gave (`Vote::addReason()`), which is where an
 * application puts the scope it refused. The response stays the
 * application's.
 *
 * A visitor not signed in, or remembered only, is sent to the login page:
 * not a denial. The same denial repeated is journaled once a minute, the
 * next entry counting those held back.
 */
class AccessDeniedJournalSubscriber implements EventSubscriberInterface
{
    public const int BURST_WINDOW = 60;

    public function __construct(
        private readonly TokenStorageInterface $tokenStorage,
        #[Autowire(service: 'security.authentication.trust_resolver')]
        private readonly AuthenticationTrustResolverInterface $trustResolver,
        private readonly SecurityJournalService $journal,
        #[Autowire(service: 'cache.app')]
        private readonly CacheItemPoolInterface $cache,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        // Before the firewall's listener (1), which turns the exception into
        // a 403 or a redirect to the login page.
        return [KernelEvents::EXCEPTION => ['onKernelException', 2]];
    }

    public function onKernelException(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();

        while ($exception && ! $exception instanceof AccessDeniedException) {
            $exception = $exception->getPrevious();
        }

        $token = $this->tokenStorage->getToken();

        if (! $exception
            || ! $this->trustResolver->isFullFledged($token)
            || $token instanceof TwoFactorTokenInterface) {
            return;
        }

        $request = $event->getRequest();
        $route = (string) $request->attributes->get('_route');
        $attributes = implode(',', array_map(
            static fn (mixed $attribute): string => is_scalar($attribute) ? (string) $attribute : get_debug_type($attribute),
            $exception->getAttributes()
        ));

        $suppressed = $this->countBurst($token->getUserIdentifier() . '|' . $route . '|' . $attributes);

        if ($suppressed === null) {
            return;
        }

        $this->journal->record(
            SecurityEventType::ACCESS_DENIED,
            $token->getUser(),
            extra: [
                'route' => $route,
                'path' => $request->getPathInfo(),
                'http_method' => $request->getMethod(),
                'roles' => implode(',', $token->getRoleNames()),
                'attributes' => $attributes,
                'reasons' => $this->getReasons($exception),
                'suppressed' => $suppressed,
            ]
        );
    }

    /**
     * Null while the window of the last entry lasts, counting the denial;
     * otherwise the count held back since, and a new window.
     */
    private function countBurst(string $key): ?int
    {
        $item = $this->cache->getItem('wexample_user_access_denied_' . hash('xxh128', $key));
        $burst = $item->isHit() ? $item->get() : null;

        if ($burst && $burst['until'] > time()) {
            ++$burst['suppressed'];
            $this->cache->save($item->set($burst)->expiresAfter(self::BURST_WINDOW * 60));

            return null;
        }

        $until = time() + self::BURST_WINDOW;
        $this->cache->save($item->set(['until' => $until, 'suppressed' => 0])->expiresAfter(self::BURST_WINDOW * 60));

        return $burst['suppressed'] ?? 0;
    }

    private function getReasons(AccessDeniedException $exception): string
    {
        $reasons = [];

        foreach ($exception->getAccessDecision()?->votes ?? [] as $vote) {
            if ($vote->result === VoterInterface::ACCESS_DENIED) {
                array_push($reasons, ...$vote->reasons);
            }
        }

        return implode(' ', $reasons);
    }
}
