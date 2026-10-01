<?php

namespace Wexample\SymfonyUser\Log;

use Monolog\Attribute\AsMonologProcessor;
use Wexample\SymfonySecurity\Log\AbstractSecretRedactionProcessor;

/**
 * Masks the signature of the links the package mails — magic links, reset
 * links — wherever a log record holds their URL: the router logs every
 * request URI, and that signature is enough to sign in. The walk of the
 * record, exceptions included, belongs to the parent.
 */
#[AsMonologProcessor]
class SecretRedactionProcessor extends AbstractSecretRedactionProcessor
{
    /**
     * Query parameters whose value is a secret.
     */
    private const array SECRET_QUERY_PARAMETERS = ['hash'];

    protected function mask(string $value): string
    {
        return $this->maskQueryParameters($value, self::SECRET_QUERY_PARAMETERS);
    }
}
