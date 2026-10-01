<?php

namespace Wexample\SymfonyUser\EventSubscriber;

use Scheb\TwoFactorBundle\Security\Authentication\Token\TwoFactorTokenInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;
use Wexample\SymfonyForms\Service\FormProcessor\AbstractFormProcessor;
use Wexample\SymfonyHelpers\Helper\RequestHelper;
use Wexample\SymfonyUser\Controller\Pages\SecurityController;
use Wexample\SymfonyUser\Enum\SecurityEventType;
use Wexample\SymfonyUser\Service\SecurityJournalService;

/**
 * A correct password waits `two_factor.pending_lifetime` seconds for its
 * second factor. Past it, the waiting sign-in is dropped with its session:
 * coming back starts over from the password, never from the code.
 */
class TwoFactorPendingSubscriber implements EventSubscriberInterface
{
    /**
     * After the firewall (8), which puts the token in place, and before the
     * page holding users who must set an app up.
     */
    public const int PRIORITY = 6;

    private const string SESSION_STARTED = 'wexample_user_two_factor_started';

    public function __construct(
        private readonly TokenStorageInterface $tokenStorage,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly SecurityJournalService $journal,
        #[Autowire(param: 'wexample_symfony_user.two_factor.pending_lifetime')]
        private readonly int $lifetime = 600,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            LoginSuccessEvent::class => 'onLoginSuccess',
            KernelEvents::REQUEST => ['onRequest', self::PRIORITY],
        ];
    }

    public function onLoginSuccess(LoginSuccessEvent $event): void
    {
        $session = $event->getRequest()->getSession();

        if ($event->getAuthenticatedToken() instanceof TwoFactorTokenInterface) {
            $session->set(self::SESSION_STARTED, time());
        } else {
            $session->remove(self::SESSION_STARTED);
        }
    }

    public function onRequest(RequestEvent $event): void
    {
        $token = $this->tokenStorage->getToken();

        if (! $event->isMainRequest() || ! $token instanceof TwoFactorTokenInterface) {
            return;
        }

        $request = $event->getRequest();
        $session = $request->getSession();
        $started = $session->get(self::SESSION_STARTED);

        // A waiting sign-in from before this rule: its clock starts now.
        if (! is_int($started)) {
            $session->set(self::SESSION_STARTED, time());

            return;
        }

        if ($started + $this->lifetime >= time()) {
            return;
        }

        $this->journal->record(SecurityEventType::LOGIN_SECOND_FACTOR_EXPIRED, $token->getUser());
        $this->tokenStorage->setToken(null);
        $session->invalidate();

        $url = $this->urlGenerator->generate(SecurityController::ROUTE_LOGIN);

        $event->setResponse(
            RequestHelper::isJsonRequest($request)
                ? new JsonResponse(
                    ['ok' => false, 'action' => ['type' => AbstractFormProcessor::ACTION_REDIRECT, 'url' => $url]],
                    Response::HTTP_UNAUTHORIZED
                )
                : new RedirectResponse($url)
        );
    }
}
