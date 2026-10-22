<?php

namespace Wexample\SymfonyUser\Exception;

use RuntimeException;
use Wexample\SymfonyUser\Enum\AccountAdministrationRefusal;

/**
 * A change to an account the rules refuse. The message is for developers;
 * a form shows the refusal, by its code.
 */
class AccountAdministrationException extends RuntimeException
{
    /**
     * @param list<string> $roles the roles the refusal is about
     */
    public function __construct(
        public readonly AccountAdministrationRefusal $refusal,
        public readonly array $roles = [],
    ) {
        parent::__construct(sprintf(
            'Account change refused: %s%s.',
            $refusal->value,
            $roles ? ' (' . implode(', ', $roles) . ')' : ''
        ));
    }
}
