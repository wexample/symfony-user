<?php

namespace Wexample\SymfonyUser\EventSubscriber;

use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Scheb\TwoFactorBundle\Security\Authentication\Token\TwoFactorTokenInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;
use Wexample\SymfonyUser\Entity\AbstractUser;

class LastLoginSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [LoginSuccessEvent::class => 'onLoginSuccess'];
    }

    public function onLoginSuccess(LoginSuccessEvent $event): void
    {
        $user = $event->getUser();

        // A password still waiting for its second factor is no login: the
        // event comes again once the code is checked.
        if (! $user instanceof AbstractUser
            || $event->getAuthenticatedToken() instanceof TwoFactorTokenInterface) {
            return;
        }

        $user->setDateLastLogin(new DateTimeImmutable());
        $this->entityManager->flush();
    }
}
