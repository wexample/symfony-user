<?php

namespace Wexample\SymfonyUser\Log;

use Monolog\Attribute\AsMonologProcessor;
use Monolog\LogRecord;

/**
 * Masks the signature of the links the package mails — magic links, reset
 * links — wherever a log record holds their URL: the router logs every
 * request URI, and that signature is enough to sign in.
 */
#[AsMonologProcessor]
class SecretRedactionProcessor
{
    public const string REDACTED = '[redacted]';

    /**
     * Query parameters whose value is a secret.
     */
    private const array SECRET_QUERY_PARAMETERS = ['hash'];

    public function __invoke(LogRecord $record): LogRecord
    {
        return $record->with(
            message: $this->redact($record->message),
            context: $this->redact($record->context),
            extra: $this->redact($record->extra),
        );
    }

    private function redact(mixed $value): mixed
    {
        if (is_string($value)) {
            return preg_replace(
                '/([?&](?:' . implode('|', self::SECRET_QUERY_PARAMETERS) . ')=)[^&\s"\'#]+/',
                '$1' . self::REDACTED,
                $value
            );
        }

        if (is_array($value)) {
            return array_map($this->redact(...), $value);
        }

        return $value;
    }
}
