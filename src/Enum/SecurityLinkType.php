<?php

namespace Wexample\SymfonyUser\Enum;

/**
 * The links the package mails to a user: each has its own template, under
 * `assets/mails/<value>.html.twig`.
 */
enum SecurityLinkType: string
{
    case MAGIC_LINK = 'magic_link';

    case PASSWORD_RESET = 'password_reset';
}
