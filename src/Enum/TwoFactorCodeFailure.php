<?php

namespace Wexample\SymfonyUser\Enum;

/**
 * Why a code of the second factor was refused, which the form tells.
 */
enum TwoFactorCodeFailure: string
{
    case INVALID = 'invalid';

    case EXPIRED = 'expired';

    case TOO_MANY_ATTEMPTS = 'too_many_attempts';
}
