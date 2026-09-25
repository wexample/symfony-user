<?php

namespace Wexample\SymfonyUser\Enum;

/**
 * The security messages the package sends to a user: each has its own mail
 * template, under `assets/mails/<value>.html.twig`.
 */
enum SecurityMessageType: string
{
    /**
     * Carries a link that signs the user in.
     */
    case MAGIC_LINK = 'magic_link';

    /**
     * Carries a link to the reset form.
     */
    case PASSWORD_RESET = 'password_reset';

    /**
     * Carries the code of the second factor.
     */
    case TWO_FACTOR_CODE = 'two_factor_code';

    public function carriesLink(): bool
    {
        return $this !== self::TWO_FACTOR_CODE;
    }
}
