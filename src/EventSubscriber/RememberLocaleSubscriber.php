<?php

namespace Wexample\SymfonyUser\EventSubscriber;

use Doctrine\ORM\EntityManagerInterface;
use Scheb\TwoFactorBundle\Security\Authentication\Token\TwoFactorTokenInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Wexample\SymfonyTranslations\Service\LocaleService;
use Wexample\SymfonyUser\Entity\AbstractUser;

/**
 * With wexample_symfony_user.remember_locale, an account reading a page whose
 * url names a language keeps that language, for what reaches it outside its
 * own requests: its mails.
 *
 * Only a language the url names is taken: one the browser merely asks for,
 * or the default one, would overwrite a language the account chose.
 *
 * And the other way round: a browser that does not hold the account's language
 * in its cookie — a new one, or one someone else used — is handed it, so the
 * pages after signing in are read in the account's language too.
 */
class RememberLocaleSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly TokenStorageInterface $tokenStorage,
        private readonly EntityManagerInterface $entityManager,
        private readonly LocaleService $localeService,
        #[Autowire('%wexample_symfony_user.remember_locale%')]
        private readonly bool $enabled,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        // After the firewall (8), which gives the request its token.
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 0],
            KernelEvents::RESPONSE => 'onKernelResponse',
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (! $this->enabled || ! $event->isMainRequest()) {
            return;
        }

        $locale = $event->getRequest()->attributes->get(LocaleService::LOCALE_ATTRIBUTE);
        $token = $this->tokenStorage->getToken();
        $user = $token?->getUser();

        // A password still waiting for its second factor is no one yet.
        if (! $user instanceof AbstractUser
            || $token instanceof TwoFactorTokenInterface
            || ! $this->localeService->hasLocale($locale)
            || $locale === $user->getLocale()) {
            return;
        }

        $user->setLocale($locale);
        $this->entityManager->flush();
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        if (! $this->enabled || ! $event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $token = $this->tokenStorage->getToken();
        $user = $token?->getUser();

        // A language chosen on this very request is the cookie already.
        if (! $user instanceof AbstractUser
            || $token instanceof TwoFactorTokenInterface
            || $request->attributes->has(LocaleService::LOCALE_ATTRIBUTE)
            || ! $this->localeService->hasLocale($user->getLocale())
            || $user->getLocale() === $request->cookies->get(LocaleService::LOCALE_COOKIE)) {
            return;
        }

        $event->getResponse()->headers->setCookie(
            Cookie::create(LocaleService::LOCALE_COOKIE, $user->getLocale(), new \DateTimeImmutable('+1 year'))
        );
    }
}
