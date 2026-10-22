<?php

namespace Wexample\SymfonyUser\Security\TwoFactor;

use LogicException;
use Scheb\TwoFactorBundle\Security\TwoFactor\AuthenticationContextInterface;
use Scheb\TwoFactorBundle\Security\TwoFactor\Provider\TwoFactorFormRendererInterface;
use Scheb\TwoFactorBundle\Security\TwoFactor\Provider\TwoFactorProviderInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Wexample\SymfonyUser\Entity\AbstractUser;
use Wexample\SymfonyUser\Security\Authenticator\LoginFormAuthenticator;
use Wexample\SymfonyUser\Service\TwoFactorCodeService;
use Wexample\SymfonyUser\Service\TwoFactorPolicyService;

/**
 * The email code second factor, plugged into scheb/2fa-bundle under the
 * `email_code` name.
 */
class EmailCodeTwoFactorProvider implements TwoFactorProviderInterface, TwoFactorFormRendererInterface
{
    public const string NAME = 'email_code';

    public function __construct(
        private readonly TwoFactorCodeService $codeService,
        private readonly TwoFactorPolicyService $policy,
    ) {
    }

    /**
     * By default only a password login asks for it: a magic link, or the
     * login that follows a password reset, proved the mailbox already. Under
     * `two_factor.required`, every sign-in does — see TwoFactorPolicyService.
     */
    public function beginAuthentication(AuthenticationContextInterface $context): bool
    {
        $user = $context->getUser();

        // An authenticator app, once set up, replaces the email code; until
        // then it is how a user who must set one up signs in.
        return $user instanceof AbstractUser
            && ! $user->isTotpAuthenticationEnabled()
            && $this->policy->asksSecondFactor($user, LoginFormAuthenticator::isLoginRequest($context->getRequest()));
    }

    public function prepareAuthentication(object $user): void
    {
        if ($user instanceof AbstractUser) {
            $this->codeService->send($user);
        }
    }

    public function validateAuthenticationCode(object $user, string $authenticationCode): bool
    {
        return $user instanceof AbstractUser
            && $this->codeService->validate($user, $authenticationCode);
    }

    public function getFormRenderer(): TwoFactorFormRendererInterface
    {
        return $this;
    }

    /**
     * The form lives on TwoFactorController, the `auth_form_path` of the
     * firewall: scheb never renders it itself.
     */
    public function renderForm(Request $request, array $templateVars): Response
    {
        throw new LogicException('Point the auth_form_path of the firewall to user_security_two_factor.');
    }
}
