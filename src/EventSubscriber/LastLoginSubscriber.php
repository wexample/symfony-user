<?php

namespace Wexample\SymfonyUser\EventSubscriber;

use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
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

        if (! $user instanceof AbstractUser) {
            return;
        }

        $user->setDateLastLogin(new DateTimeImmutable());
        $this->entityManager->flush();
    }
}
