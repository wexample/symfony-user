<?php

namespace Wexample\SymfonyUser\Service;

use DateTimeImmutable;
use DateTimeZone;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Wexample\SymfonyHelpers\Entity\AbstractEntity;
use Wexample\SymfonySecurity\Helper\RequestIdHelper;
use Wexample\SymfonyUser\Enum\SecurityEventType;
use Wexample\SymfonyUser\Event\SecurityEvent;

/**
 * Turns a security fact into a SecurityEvent, with what the request tells of
 * it, and dispatches it.
 */
class SecurityJournalService
{
    public const string REQUEST_ID_HEADER = RequestIdHelper::HEADER;

    public function __construct(
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly RequestStack $requestStack,
        private readonly Security $security,
        #[Autowire(param: 'kernel.secret')]
        private readonly string $secret,
    ) {
    }

    /**
     * @param array<string, scalar|null> $extra
     */
    public function record(
        SecurityEventType $type,
        ?UserInterface $user = null,
        ?string $cause = null,
        ?string $method = null,
        array $extra = [],
    ): void {
        $request = $this->requestStack->getMainRequest();

        $this->eventDispatcher->dispatch(new SecurityEvent(
            type: $type,
            occurredAt: new DateTimeImmutable('now', new DateTimeZone('UTC')),
            userId: $this->getUserId($user),
            cause: $cause,
            method: $method,
            firewall: $request ? $this->security->getFirewallConfig($request)?->getName() : null,
            ip: $request?->getClientIp(),
            userAgent: $request?->headers->get('User-Agent'),
            requestId: RequestIdHelper::resolve($request),
            extra: $extra,
        ));
    }

    /**
     * What a typed identifier becomes in the journal when no account holds it:
     * enough to group the attempts on one address, never the address — nor
     * the password users sometimes type in its place.
     */
    public function fingerprint(string $identifier): string
    {
        return substr(hash_hmac('sha256', mb_strtolower(trim($identifier)), $this->secret . 'wexample_user_journal'), 0, 16);
    }

    private function getUserId(?UserInterface $user): ?string
    {
        if ($user instanceof AbstractEntity) {
            return (string) $user->getId();
        }

        return $user?->getUserIdentifier();
    }
}
