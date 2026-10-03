<?php

namespace Wexample\SymfonyUser\Security\Gate;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Wexample\Helpers\Helper\ClassHelper;
use Wexample\SymfonyForms\Service\FormProcessor\AbstractFormProcessor;
use Wexample\SymfonyUser\Entity\AbstractUser;
use Wexample\SymfonyUser\Form\TotpEnableForm;
use Wexample\SymfonyUser\Interface\AccountGateInterface;
use Wexample\SymfonyUser\Routing\UserRoute;
use Wexample\SymfonyUser\Service\TwoFactorPolicyService;

/**
 * A role that must use an authenticator app, signed in by email code while it
 * has none: the app first, before anything else.
 */
class TotpSetupGate implements AccountGateInterface
{
    public function __construct(
        private readonly TwoFactorPolicyService $policy
    ) {
    }

    public static function getPriority(): int
    {
        return 20;
    }

    public function isBlocking(AbstractUser $user, TokenInterface $token): bool
    {
        return $this->policy->mustSetAppUp($user);
    }

    public function allowsRequest(Request $request): bool
    {
        $route = (string) $request->attributes->get('_route');

        return str_starts_with($route, 'user_totp_')
            || ($route === AbstractFormProcessor::FORM_SUBMIT_ROUTE
                && $request->attributes->get('name') === ClassHelper::longTableized(TotpEnableForm::class));
    }

    public function getRoute(): string
    {
        return UserRoute::TOTP;
    }
}
