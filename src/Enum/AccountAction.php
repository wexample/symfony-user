<?php

namespace Wexample\SymfonyUser\Enum;

/**
 * What an administrator does to an account from its page, each a method of
 * AccountAdministrationService. Changing its roles is a form, not one of
 * these: it carries a value, these only carry the intent.
 *
 * The value is what the URL of the action reads, so it is part of the
 * package's addresses: a case is not renamed without the pages that link it.
 */
enum AccountAction: string
{
    case DEACTIVATE = 'deactivate';
    case REACTIVATE = 'reactivate';
    case LOCK = 'lock';
    case UNLOCK = 'unlock';

    /** The mail to choose a password: an activation, or a new one. */
    case PASSWORD_MAIL = 'password-mail';

    /**
     * Whether the action is the one an account in this state is offered: a
     * disabled account is reactivated, not deactivated again.
     */
    public function suits(bool $enabled, bool $locked): bool
    {
        return match ($this) {
            self::DEACTIVATE => $enabled,
            self::REACTIVATE => ! $enabled,
            self::LOCK => ! $locked,
            self::UNLOCK => $locked,
            self::PASSWORD_MAIL => $enabled && ! $locked,
        };
    }
}
