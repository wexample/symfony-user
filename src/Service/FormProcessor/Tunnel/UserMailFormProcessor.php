<?php

namespace Wexample\SymfonyUser\Service\FormProcessor\Tunnel;

use Wexample\SymfonyForms\Service\FormProcessor\AbstractFormProcessor;
use Wexample\SymfonyHelpers\Helper\RoleHelper;

/**
 * The tunnel step handles the submission: the processor only builds the form.
 */
class UserMailFormProcessor extends AbstractFormProcessor
{
    public function getRequiredRoles(): array
    {
        return [RoleHelper::PUBLIC_ACCESS];
    }
}
