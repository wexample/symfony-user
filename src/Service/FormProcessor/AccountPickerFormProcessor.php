<?php

namespace Wexample\SymfonyUser\Service\FormProcessor;

use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\InvalidCsrfTokenException;
use Wexample\SymfonyForms\Service\FormProcessor\AbstractFormProcessor;
use Wexample\SymfonyHelpers\Helper\RoleHelper;

/**
 * Builds the account picker form and turns a refused sign-in into its
 * error. The submission itself is handled by AccountPickerAuthenticator.
 */
class AccountPickerFormProcessor extends AbstractFormProcessor
{
    public const string ERROR_NOT_ALLOWED = '@form::error.not_allowed';
    public const string ERROR_INVALID_CSRF = '@form::error.invalid_csrf';

    public function getRequiredRoles(): array
    {
        return [RoleHelper::PUBLIC_ACCESS];
    }

    public function addAuthenticationError(FormInterface $form, AuthenticationException $exception): void
    {
        $form->addError(new FormError(
            $exception instanceof InvalidCsrfTokenException ? self::ERROR_INVALID_CSRF : self::ERROR_NOT_ALLOWED
        ));
    }
}
