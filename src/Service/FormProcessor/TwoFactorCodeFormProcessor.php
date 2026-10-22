<?php

namespace Wexample\SymfonyUser\Service\FormProcessor;

use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormInterface;
use Wexample\SymfonyForms\Service\FormProcessor\AbstractFormProcessor;
use Wexample\SymfonyHelpers\Helper\RoleHelper;
use Wexample\SymfonyUser\Enum\TwoFactorCodeFailure;

/**
 * Builds the code form and turns a refused code into a form error. The
 * submission itself is checked by scheb/2fa-bundle.
 */
class TwoFactorCodeFormProcessor extends AbstractFormProcessor
{
    public function getRequiredRoles(): array
    {
        return [RoleHelper::PUBLIC_ACCESS];
    }

    public function addFailure(FormInterface $form, TwoFactorCodeFailure $failure): void
    {
        $form->addError(new FormError('@form::error.' . $failure->value));
    }
}
