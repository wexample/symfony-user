<?php

namespace Wexample\SymfonyUser\Security\Token;

use Symfony\Component\Security\Http\Authenticator\Token\PostAuthenticationToken;

/**
 * A session opened from the account picker. A class of its own, so that
 * scheb, whose `security_tokens` lists the classes it asks a second factor
 * for, asks none: nobody typed a password either.
 */
class AccountPickerToken extends PostAuthenticationToken
{
}
