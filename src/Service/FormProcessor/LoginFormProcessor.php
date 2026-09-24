<?php

namespace Wexample\SymfonyUser\Service\FormProcessor;

use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Security\Core\Exception\AccountStatusException;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;
use Symfony\Component\Security\Core\Exception\InvalidCsrfTokenException;
use Symfony\Component\Security\Core\Exception\TooManyLoginAttemptsAuthenticationException;
use Wexample\SymfonyForms\Service\FormProcessor\AbstractFormProcessor;
use Wexample\SymfonyHelpers\Helper\RoleHelper;

/**
 * Builds the login form and turns a failed login into a form error. The
 * submission itself is handled by LoginFormAuthenticator.
 */
class LoginFormProcessor extends AbstractFormProcessor
{
    public const string ERROR_INVALID_CREDENTIALS = 'error.invalid_credentials';
    public const string ERROR_TOO_MANY_ATTEMPTS = 'error.too_many_attempts';
    public const string ERROR_INVALID_CSRF = 'error.invalid_csrf';
    public const string ERROR_ACCOUNT_STATUS = 'error.account_status';

    public function getRequiredRoles(): array
    {
        return [RoleHelper::PUBLIC_ACCESS];
    }

    /**
     * Unknown user and wrong password read the same, so the form never tells
     * which accounts exist.
     */
    public function addAuthenticationError(
        FormInterface $form,
        AuthenticationException $exception
    ): void {
        $key = match (true) {
            $exception instanceof TooManyLoginAttemptsAuthenticationException => self::ERROR_TOO_MANY_ATTEMPTS,
            $exception instanceof InvalidCsrfTokenException => self::ERROR_INVALID_CSRF,
            // Raised by UserChecker once the password is known to be right.
            $exception instanceof CustomUserMessageAccountStatusException => $exception->getMessageKey(),
            $exception instanceof AccountStatusException => self::ERROR_ACCOUNT_STATUS,
            default => self::ERROR_INVALID_CREDENTIALS,
        };

        $form->addError(new FormError('@form::' . $key));
    }
}
