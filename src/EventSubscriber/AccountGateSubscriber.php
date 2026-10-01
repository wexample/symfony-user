<?php

namespace Wexample\SymfonyUser\EventSubscriber;

use Scheb\TwoFactorBundle\Security\Authentication\Token\TwoFactorTokenInterface;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Wexample\SymfonyForms\Service\FormProcessor\AbstractFormProcessor;
use Wexample\SymfonyHelpers\Helper\RequestHelper;
use Wexample\SymfonyUser\Controller\Pages\SecurityController;
use Wexample\SymfonyUser\Entity\AbstractUser;
use Wexample\SymfonyUser\Interface\AccountGateInterface;

/**
 * Holds a signed-in user on the page of the first gate still blocking them,
 * whatever the way they signed in. A page gets redirected, an ajax call a
 * `403` naming where to go.
 */
class AccountGateSubscriber implements EventSubscriberInterface
{
    /**
     * After the firewall (8), which puts the token in place.
     */
    public const int PRIORITY = 4;

    /**
     * @param iterable<AccountGateInterface> $gates
     */
    public function __construct(
        private readonly TokenStorageInterface $tokenStorage,
        private readonly UrlGeneratorInterface $urlGenerator,
        #[AutowireIterator(AccountGateInterface::TAG, defaultPriorityMethod: 'getPriority')]
        private readonly iterable $gates,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::REQUEST => ['onRequest', self::PRIORITY]];
    }

    public function onRequest(RequestEvent $event): void
    {
        $token = $this->tokenStorage->getToken();
        $user = $token?->getUser();
        $request = $event->getRequest();

        // A password still waiting for its second factor is not signed in: the
        // firewall holds it, the gates come after.
        if (! $event->isMainRequest()
            || ! $user instanceof AbstractUser
            || $token instanceof TwoFactorTokenInterface
            || $this->isAlwaysAllowed((string) $request->attributes->get('_route'))) {
            return;
        }

        foreach ($this->gates as $gate) {
            if (! $gate->isBlocking($user, $token)) {
                continue;
            }

            if ($gate->allowsRequest($request)) {
                return;
            }

            $url = $this->urlGenerator->generate($gate->getRoute());

            $event->setResponse(
                RequestHelper::isJsonRequest($request)
                    ? new JsonResponse(
                        ['ok' => false, 'action' => ['type' => AbstractFormProcessor::ACTION_REDIRECT, 'url' => $url]],
                        Response::HTTP_FORBIDDEN
                    )
                    : new RedirectResponse($url)
            );

            return;
        }
    }

    /**
     * Leaving, and the toolbar of the debug mode.
     */
    private function isAlwaysAllowed(string $route): bool
    {
        return $route === SecurityController::ROUTE_LOGOUT || str_starts_with($route, '_');
    }
}
