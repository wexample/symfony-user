<?php

namespace Wexample\SymfonyUser\EventSubscriber;

use Doctrine\ORM\EntityManagerInterface;
use Scheb\TwoFactorBundle\Security\Authentication\Token\TwoFactorTokenInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
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
        return [KernelEvents::REQUEST => ['onKernelRequest', 0]];
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
}
