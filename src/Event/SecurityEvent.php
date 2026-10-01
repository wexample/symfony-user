<?php

namespace Wexample\SymfonyUser\Event;

use Wexample\SymfonySecurity\Event\AbstractSecurityEvent;
use Wexample\SymfonyUser\Enum\SecurityEventType;

/**
 * A security fact, dispatched by SecurityJournalService for whoever records
 * it: the package's own log subscriber, an audit package. Its fields are
 * those of AbstractSecurityEvent.
 *
 * It never holds a secret — password, code, backup code, TOTP secret, link,
 * token, session id. An identifier typed for an unknown account is kept as a
 * fingerprint only, in `extra.identifier_fingerprint`.
 *
 * @property-read SecurityEventType $type
 */
class SecurityEvent extends AbstractSecurityEvent
{
    public function __construct(SecurityEventType $type, mixed ...$arguments)
    {
        parent::__construct($type, ...$arguments);
    }
}
