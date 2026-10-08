<?php

namespace Wexample\SymfonyUser\Exception;

use RuntimeException;
use Wexample\SymfonyUser\Enum\MembershipAdministrationRefusal;

/**
 * A change to the members of a group the rules refuse. The message is for
 * developers; a form shows the refusal, by its code.
 */
class MembershipAdministrationException extends RuntimeException
{
    public function __construct(
        public readonly MembershipAdministrationRefusal $refusal,
    ) {
        parent::__construct(sprintf('Membership change refused: %s.', $refusal->value));
    }
}
