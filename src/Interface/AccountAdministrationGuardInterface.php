<?php

namespace Wexample\SymfonyUser\Interface;

use Wexample\SymfonyUser\Entity\AbstractUser;

/**
 * An application's own say on who administers whom — an establishment
 * administrator, the accounts of their establishment only. Every guard is
 * asked before any change AccountAdministrationService makes; one refusing
 * stops it as `target_out_of_scope`. The tag follows the class wherever it is.
 */
interface AccountAdministrationGuardInterface
{
    public const string TAG = 'wexample_user.account_administration_guard';

    public function allows(AbstractUser $actor, AbstractUser $target): bool;
}
