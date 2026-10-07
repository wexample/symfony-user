<?php

namespace Wexample\SymfonyUser\EventSubscriber;

use Scheb\TwoFactorBundle\Security\Authentication\Token\TwoFactorTokenInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Wexample\SymfonyForms\Service\FormProcessor\AbstractFormProcessor;
use Wexample\SymfonyHelpers\Helper\RequestHelper;
use Wexample\SymfonyUser\Entity\AbstractUser;
use Wexample\SymfonyUser\Enum\SecurityEventType;
use Wexample\SymfonyUser\Routing\UserRoute;
use Wexample\SymfonyUser\Service\SecurityJournalService;

/**
 * Signs out a session nobody has asked anything of for
 * `session.idle_lifetime` seconds — the screen left open in a workshop, the
 * tab forgotten on a shared machine. Off at 0, which is the default.
 *
 * What counts as being asked something is a page: the requests under `/_`,
 * which a script makes and not a reader, are held to the delay without
 * pushing it back, so a page polling in a corner keeps nothing alive. The
 * delay is the session's own, not the cookie's: Symfony's
 * `framework.session.cookie_lifetime` and `gc_maxlifetime` say how long the
 * session may live at all, this says how long it may live unused.
 */
class SessionIdleSubscriber implements EventSubscriberInterface
{
    /**
     * After the firewall (8), which puts the token in place, and before the
     * account gates (4): an expired session is signed out rather than held
     * on a gate's page.
     */
    public const int PRIORITY = 6;

    public const string SESSION_LAST_SEEN = 'wexample_user_last_seen';

    public function __construct(
        private readonly TokenStorageInterface $tokenStorage,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly Security $security,
        private readonly SecurityJournalService $journal,
        #[Autowire(param: 'wexample_symfony_user.session.idle_lifetime')]
        private readonly int $idleLifetime = 0,
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

        // A password still waiting for its second factor is not signed in:
        // TwoFactorPendingSubscriber times that one out, on its own delay.
        // Leaving is left to the firewall, which is doing the same thing.
        if ($this->idleLifetime === 0
            || ! $event->isMainRequest()
            || ! $user instanceof AbstractUser
            || $token instanceof TwoFactorTokenInterface
            || $event->getRequest()->attributes->get('_route') === UserRoute::LOGOUT) {
            return;
        }

        $request = $event->getRequest();
        $session = $request->getSession();
        $lastSeen = $session->get(self::SESSION_LAST_SEEN);

        // A session opened before this setting, or before the stamp was
        // written: its clock starts now.
        if (! is_int($lastSeen)) {
            $session->set(self::SESSION_LAST_SEEN, time());

            return;
        }

        if ($lastSeen + $this->idleLifetime >= time()) {
            if (! $this->isProgramRequest($request)) {
                $session->set(self::SESSION_LAST_SEEN, time());
            }

            return;
        }

        $this->journal->record(SecurityEventType::SESSION_EXPIRED, $user, extra: ['idle' => time() - $lastSeen]);

        // Through the firewall rather than by emptying the token storage: it
        // is what clears the remember-me cookie, which would otherwise sign
        // the account straight back in.
        $this->security->logout(validateCsrfToken: false);
        $session->invalidate();

        $url = $this->urlGenerator->generate(
            UserRoute::LOGIN,
            [UserRoute::PARAMETER_SESSION => UserRoute::SESSION_EXPIRED]
        );

        $event->setResponse(
            RequestHelper::isJsonRequest($request)
                ? new JsonResponse(
                    ['ok' => false, 'action' => ['type' => AbstractFormProcessor::ACTION_REDIRECT, 'url' => $url]],
                    Response::HTTP_UNAUTHORIZED
                )
                : new RedirectResponse($url)
        );
    }

    /**
     * Reached by a program, not by a reader: a component rendered on demand,
     * the state an interface remembers, a live subscription. The convention
     * is the leading underscore of the path.
     */
    private function isProgramRequest(Request $request): bool
    {
        return str_starts_with($request->getPathInfo(), '/_');
    }
}
