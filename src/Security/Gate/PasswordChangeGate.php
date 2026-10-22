<?php

namespace Wexample\SymfonyUser\Security\Gate;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authentication\Token\SwitchUserToken;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Wexample\Helpers\Helper\ClassHelper;
use Wexample\SymfonyForms\Service\FormProcessor\AbstractFormProcessor;
use Wexample\SymfonyUser\Entity\AbstractUser;
use Wexample\SymfonyUser\Form\SetPasswordForm;
use Wexample\SymfonyUser\Interface\AccountGateInterface;
use Wexample\SymfonyUser\Routing\UserRoute;

/**
 * A password its holder did not choose, replaced before anything else — the
 * first of the gates: until it is done, the account signs in with a secret
 * someone else knows.
 *
 * It holds them on the page a reset link leads to, with the same form:
 * PasswordResetService::getProofUser() gives them to it without a proof, and
 * PasswordUpdaterService clears the flag as it writes the new password.
 */
class PasswordChangeGate implements AccountGateInterface
{
    public static function getPriority(): int
    {
        return 30;
    }

    /**
     * An administrator impersonating a user is not held: they would be
     * choosing the password of the account they are visiting.
     */
    public function isBlocking(AbstractUser $user, TokenInterface $token): bool
    {
        return ! $token instanceof SwitchUserToken && $user->isPasswordChangeRequired();
    }

    public function allowsRequest(Request $request): bool
    {
        $route = (string) $request->attributes->get('_route');

        return $route === UserRoute::PASSWORD_NEW
            || ($route === AbstractFormProcessor::FORM_SUBMIT_ROUTE
                && $request->attributes->get('name') === ClassHelper::longTableized(SetPasswordForm::class));
    }

    public function getRoute(): string
    {
        return UserRoute::PASSWORD_NEW;
    }
}
