<?php

namespace Wexample\SymfonyUser\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Wexample\Helpers\Helper\ClassHelper;
use Wexample\SymfonyForms\Service\FormProcessor\AbstractFormProcessor;
use Wexample\SymfonyHelpers\Helper\RequestHelper;
use Wexample\SymfonyUser\Controller\Pages\SecurityController;
use Wexample\SymfonyUser\Controller\Pages\TotpController;
use Wexample\SymfonyUser\Entity\AbstractUser;
use Wexample\SymfonyUser\Form\TotpEnableForm;
use Wexample\SymfonyUser\Service\TwoFactorPolicyService;

/**
 * A user whose role must use an authenticator app, signed in by email code
 * while they have none, reaches nothing but its setup page until it is set up.
 */
class TotpSetupHoldSubscriber implements EventSubscriberInterface
{
    /**
     * After the firewall (8), which puts the token in place.
     */
    public const int PRIORITY = 4;

    public function __construct(
        private readonly TokenStorageInterface $tokenStorage,
        private readonly TwoFactorPolicyService $policy,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::REQUEST => ['onRequest', self::PRIORITY]];
    }

    public function onRequest(RequestEvent $event): void
    {
        if (! $event->isMainRequest()) {
            return;
        }

        $user = $this->tokenStorage->getToken()?->getUser();

        if (! $user instanceof AbstractUser || ! $this->policy->mustSetAppUp($user)) {
            return;
        }

        $request = $event->getRequest();
        $route = (string) $request->attributes->get('_route');

        if ($this->isAllowed($route, (string) $request->attributes->get('name'))) {
            return;
        }

        $url = $this->urlGenerator->generate(TotpController::ROUTE_INDEX);

        $event->setResponse(
            RequestHelper::isJsonRequest($request)
                ? new JsonResponse(
                    ['ok' => false, 'action' => ['type' => AbstractFormProcessor::ACTION_REDIRECT, 'url' => $url]],
                    Response::HTTP_FORBIDDEN
                )
                : new RedirectResponse($url)
        );
    }

    /**
     * The setup itself, leaving, and the toolbar of the debug mode.
     */
    private function isAllowed(string $route, string $formName): bool
    {
        return str_starts_with($route, 'user_totp_')
            || $route === SecurityController::ROUTE_LOGOUT
            || str_starts_with($route, '_')
            || ($route === AbstractFormProcessor::FORM_SUBMIT_ROUTE
                && $formName === ClassHelper::longTableized(TotpEnableForm::class));
    }
}
