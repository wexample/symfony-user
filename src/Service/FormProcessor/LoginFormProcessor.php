<?php

namespace Wexample\SymfonyUser\Service\FormProcessor;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Exception\AccountStatusException;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;
use Symfony\Component\Security\Core\Exception\InvalidCsrfTokenException;
use Symfony\Component\Security\Core\Exception\TooManyLoginAttemptsAuthenticationException;
use Symfony\Component\Security\Http\LoginLink\Exception\InvalidLoginLinkAuthenticationException;
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
    public const string ERROR_INVALID_LOGIN_LINK = 'error.invalid_login_link';

    public function __construct(
        FormFactoryInterface $formFactory,
        RequestStack $requestStack,
        UrlGeneratorInterface $urlGenerator,
        #[Autowire(param: 'wexample_symfony_user.reveal_account_status')]
        private readonly bool $revealAccountStatus = false,
    ) {
        parent::__construct($formFactory, $requestStack, $urlGenerator);
    }

    public function getRequiredRoles(): array
    {
        return [RoleHelper::PUBLIC_ACCESS];
    }

    /**
     * Unknown user, wrong password, and — unless `reveal_account_status` is on —
     * disabled or locked account read the same: the form never tells which
     * accounts exist, nor in what state. Too many attempts stays distinct, its
     * counter running for unknown addresses as well.
     */
    public function addAuthenticationError(
        FormInterface $form,
        AuthenticationException $exception
    ): void {
        $key = match (true) {
            $exception instanceof TooManyLoginAttemptsAuthenticationException => self::ERROR_TOO_MANY_ATTEMPTS,
            $exception instanceof InvalidCsrfTokenException => self::ERROR_INVALID_CSRF,
            $exception instanceof InvalidLoginLinkAuthenticationException => self::ERROR_INVALID_LOGIN_LINK,
            // Raised by UserChecker once the password is known to be right.
            ! $this->revealAccountStatus && $exception instanceof AccountStatusException => self::ERROR_INVALID_CREDENTIALS,
            $exception instanceof CustomUserMessageAccountStatusException => $exception->getMessageKey(),
            $exception instanceof AccountStatusException => self::ERROR_ACCOUNT_STATUS,
            default => self::ERROR_INVALID_CREDENTIALS,
        };

        $form->addError(new FormError('@form::' . $key));
    }
}
