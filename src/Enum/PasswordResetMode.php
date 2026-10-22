<?php

namespace Wexample\SymfonyUser\Enum;

/**
 * Chosen by the application in `wexample_symfony_user.password_reset`.
 */
enum PasswordResetMode: string
{
    /**
     * A signed link opens a form asking for the new password.
     */
    case TOKEN = 'token';

    /**
     * A magic link signs the user in, then lets them set a password without
     * the current one for a while.
     */
    case MAGIC_LINK = 'magic_link';
}
