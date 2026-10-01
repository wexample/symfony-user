<?php

namespace Wexample\SymfonyUser\EventSubscriber;

use Scheb\TwoFactorBundle\Security\Authentication\Exception\InvalidTwoFactorCodeException;
use Scheb\TwoFactorBundle\Security\Authentication\Token\TwoFactorTokenInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\SwitchUserToken;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;
use Symfony\Component\Security\Core\Exception\InvalidCsrfTokenException;
use Symfony\Component\Security\Core\Exception\TooManyLoginAttemptsAuthenticationException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Event\LoginFailureEvent;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;
use Symfony\Component\Security\Http\Event\LogoutEvent;
use Symfony\Component\Security\Http\Event\SwitchUserEvent;
use Symfony\Component\Security\Http\LoginLink\Exception\InvalidLoginLinkAuthenticationException;
use Throwable;
use Wexample\Helpers\Helper\ClassHelper;
use Wexample\SymfonyForms\Service\FormProcessor\AbstractFormProcessor;
use Wexample\SymfonyUser\Controller\Pages\SecurityController;
use Wexample\SymfonyUser\Enum\SecurityEventType;
use Wexample\SymfonyUser\Form\LoginForm;
use Wexample\SymfonyUser\Form\SetPasswordForm;
use Wexample\SymfonyUser\Form\TwoFactorCodeForm;
use Wexample\SymfonyUser\Security\TwoFactor\EmailCodeTwoFactorProvider;
use Wexample\SymfonyUser\Security\UserChecker;
use Wexample\SymfonyUser\Service\SecurityJournalService;
use Wexample\SymfonyUser\Service\TwoFactorCodeService;

/**
 * Records the sign-ins, their failures with the real cause, the logouts and
 * the impersonations, from the events of Symfony security. Reads nothing of
 * the request but its route: never a submitted value.
 */
class SecurityJournalSubscriber implements EventSubscriberInterface
{
    public const string METHOD_PASSWORD = 'password';
    public const string METHOD_MAGIC_LINK = 'magic_link';
    public const string METHOD_PASSWORD_RESET = 'password_reset';
    public const string METHOD_SECOND_FACTOR = 'second_factor';
    public const string METHOD_OTHER = 'other';

    public function __construct(
        private readonly SecurityJournalService $journal,
        private readonly TokenStorageInterface $tokenStorage,
        private readonly TwoFactorCodeService $codeService,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            LoginSuccessEvent::class => 'onLoginSuccess',
            LoginFailureEvent::class => 'onLoginFailure',
            LogoutEvent::class => 'onLogout',
            SwitchUserEvent::class => 'onSwitchUser',
        ];
    }

    public function onLoginSuccess(LoginSuccessEvent $event): void
    {
        $method = $this->getMethod($event->getRequest());
        $user = $event->getUser();

        if ($event->getAuthenticatedToken() instanceof TwoFactorTokenInterface) {
            $this->journal->record(SecurityEventType::LOGIN_SECOND_FACTOR_REQUIRED, $user, method: $method);

            return;
        }

        if ($method === self::METHOD_SECOND_FACTOR) {
            $this->journal->record(SecurityEventType::SECOND_FACTOR_SUCCEEDED, $user);
        }

        $this->journal->record(SecurityEventType::LOGIN_SUCCEEDED, $user, method: $method);
    }

    public function onLoginFailure(LoginFailureEvent $event): void
    {
        $method = $this->getMethod($event->getRequest());
        $cause = $this->getCause($event->getException());

        if ($method === self::METHOD_SECOND_FACTOR) {
            $token = $this->tokenStorage->getToken();
            $provider = $token instanceof TwoFactorTokenInterface ? $token->getCurrentTwoFactorProvider() : null;

            if ($cause === 'invalid_code' && $provider === EmailCodeTwoFactorProvider::NAME) {
                $cause = $this->codeService->getLastFailure()->value;
            }

            $this->journal->record(
                SecurityEventType::SECOND_FACTOR_FAILED,
                $token?->getUser(),
                $cause,
                extra: ['provider' => $provider]
            );

            return;
        }

        $user = null;
        $extra = [];

        try {
            $user = $event->getPassport()?->getUser();
        } catch (Throwable) {
            // Unknown account: the address typed is kept as a fingerprint only.
            $badge = $event->getPassport()?->getBadge(UserBadge::class);
            if ($badge instanceof UserBadge && $badge->getUserIdentifier() !== '') {
                $extra['identifier_fingerprint'] = $this->journal->fingerprint($badge->getUserIdentifier());
            }
        }

        $this->journal->record(SecurityEventType::LOGIN_FAILED, $user, $cause, $method, $extra);
    }

    public function onLogout(LogoutEvent $event): void
    {
        $this->journal->record(SecurityEventType::LOGOUT, $event->getToken()?->getUser());
    }

    public function onSwitchUser(SwitchUserEvent $event): void
    {
        $token = $event->getToken();

        if ($token instanceof SwitchUserToken) {
            $this->journal->record(
                SecurityEventType::ACCOUNT_IMPERSONATION_STARTED,
                $event->getTargetUser(),
                extra: ['impersonator' => $token->getOriginalToken()->getUserIdentifier()]
            );

            return;
        }

        $this->journal->record(SecurityEventType::ACCOUNT_IMPERSONATION_ENDED, $event->getTargetUser());
    }

    private function getMethod(Request $request): string
    {
        $route = $request->attributes->get('_route');

        if ($route === SecurityController::ROUTE_LOGIN_LINK) {
            return self::METHOD_MAGIC_LINK;
        }

        if ($route !== AbstractFormProcessor::FORM_SUBMIT_ROUTE) {
            return self::METHOD_OTHER;
        }

        return match ($request->attributes->get('name')) {
            ClassHelper::longTableized(LoginForm::class) => self::METHOD_PASSWORD,
            ClassHelper::longTableized(TwoFactorCodeForm::class) => self::METHOD_SECOND_FACTOR,
            ClassHelper::longTableized(SetPasswordForm::class) => self::METHOD_PASSWORD_RESET,
            default => self::METHOD_OTHER,
        };
    }

    /**
     * The real cause, behind what the firewall made generic.
     */
    private function getCause(Throwable $exception): string
    {
        for ($current = $exception; $current; $current = $current->getPrevious()) {
            $cause = match (true) {
                $current instanceof UserNotFoundException => 'unknown_user',
                $current instanceof TooManyLoginAttemptsAuthenticationException => 'throttled',
                $current instanceof InvalidCsrfTokenException => 'invalid_csrf',
                $current instanceof InvalidLoginLinkAuthenticationException => 'invalid_login_link',
                $current instanceof InvalidTwoFactorCodeException => 'invalid_code',
                $current instanceof CustomUserMessageAccountStatusException => match ($current->getMessageKey()) {
                    UserChecker::ERROR_ACCOUNT_LOCKED => 'locked',
                    UserChecker::ERROR_ACCOUNT_DISABLED => 'disabled',
                    default => 'account_status',
                },
                default => null,
            };

            if ($cause) {
                return $cause;
            }
        }

        return $exception instanceof BadCredentialsException ? 'bad_password' : 'other';
    }
}
