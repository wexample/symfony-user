<?php

namespace Wexample\SymfonyUser\Security\Gate;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authentication\Token\SwitchUserToken;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Wexample\Helpers\Helper\ClassHelper;
use Wexample\SymfonyForms\Service\FormProcessor\AbstractFormProcessor;
use Wexample\SymfonyUser\Entity\AbstractUser;
use Wexample\SymfonyUser\Form\TermsAcceptForm;
use Wexample\SymfonyUser\Interface\AccountGateInterface;
use Wexample\SymfonyUser\Routing\UserRoute;
use Wexample\SymfonyUser\Service\TermsService;

/**
 * The terms in force, accepted before anything else — after the app setup.
 */
class TermsGate implements AccountGateInterface
{
    public function __construct(
        private readonly TermsService $termsService
    ) {
    }

    public static function getPriority(): int
    {
        return 10;
    }

    /**
     * An administrator impersonating a user is not held: they may act, and
     * they can never accept in the user's name (TermsAcceptFormProcessor).
     */
    public function isBlocking(AbstractUser $user, TokenInterface $token): bool
    {
        return ! $token instanceof SwitchUserToken && $this->termsService->mustAccept($user);
    }

    public function allowsRequest(Request $request): bool
    {
        $route = (string) $request->attributes->get('_route');

        return str_starts_with($route, 'user_terms_')
            || ($route !== '' && $route === $this->termsService->getTextRoute())
            || ($route === AbstractFormProcessor::FORM_SUBMIT_ROUTE
                && $request->attributes->get('name') === ClassHelper::longTableized(TermsAcceptForm::class));
    }

    public function getRoute(): string
    {
        return UserRoute::TERMS;
    }
}
