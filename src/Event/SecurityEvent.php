<?php

namespace Wexample\SymfonyUser\Event;

use DateTimeImmutable;
use Symfony\Contracts\EventDispatcher\Event;
use Wexample\SymfonyUser\Enum\SecurityEventType;

/**
 * A security fact, dispatched by SecurityJournalService for whoever records
 * it: the package's own log subscriber, an audit package.
 *
 * It never holds a secret — password, code, backup code, TOTP secret, link,
 * token, session id. An identifier typed for an unknown account is kept as a
 * fingerprint only, in `extra.identifier_fingerprint`.
 */
class SecurityEvent extends Event
{
    /**
     * @param array<string, scalar|null> $extra
     */
    public function __construct(
        public readonly SecurityEventType $type,
        public readonly DateTimeImmutable $occurredAt,
        public readonly ?string $userId = null,
        public readonly ?string $cause = null,
        public readonly ?string $method = null,
        public readonly ?string $firewall = null,
        public readonly ?string $ip = null,
        public readonly ?string $userAgent = null,
        public readonly ?string $requestId = null,
        public readonly array $extra = [],
    ) {
    }

    /**
     * @return array<string, scalar|array|null>
     */
    public function toArray(): array
    {
        return [
            'type' => $this->type->value,
            'outcome' => $this->type->isFailure() ? 'failure' : 'success',
            'cause' => $this->cause,
            'user_id' => $this->userId,
            'method' => $this->method,
            'firewall' => $this->firewall,
            'ip' => $this->ip,
            'user_agent' => $this->userAgent,
            'request_id' => $this->requestId,
            'occurred_at' => $this->occurredAt->format(DATE_ATOM),
            'extra' => $this->extra,
        ];
    }
}
