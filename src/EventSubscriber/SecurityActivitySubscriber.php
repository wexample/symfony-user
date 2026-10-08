<?php

namespace Wexample\SymfonyUser\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Wexample\SymfonyActivity\Class\ActivitySubject;
use Wexample\SymfonyActivity\Enum\ActivityCategory;
use Wexample\SymfonyActivity\Service\ActivityRecorder;
use Wexample\SymfonyUser\Event\SecurityEvent;
use Wexample\SymfonyUser\Service\AccountDirectoryService;

/**
 * Every security fact about an account — a sign-in, a failure, a second
 * factor, a password changed, an administrator's change —, written to its
 * history in the `security` category of symfony-activity: the login history
 * of an account is this category of its history.
 *
 * Registered only when the symfony-activity bundle is enabled, and recording nothing
 * until the application enables `security` there. A fact naming no account —
 * an address typed that none holds — has no history to go into.
 */
class SecurityActivitySubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly ActivityRecorder $recorder,
        private readonly AccountDirectoryService $directory,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [SecurityEvent::class => 'onSecurityEvent'];
    }

    public function onSecurityEvent(SecurityEvent $event): void
    {
        if ($event->userId === null || ! $this->recorder->records(ActivityCategory::SECURITY->value)) {
            return;
        }

        $userClass = $this->directory->getUserClass();
        $actorId = $event->extra['actor_id'] ?? null;

        $this->recorder->record(
            new ActivitySubject($userClass, $event->userId),
            ActivityCategory::SECURITY->value,
            ActivityCategory::SECURITY->value . '.' . $event->type->value,
            array_filter([
                'method' => $event->method,
                'cause' => $event->cause,
                'ip' => $event->ip,
                'user_agent' => $event->userAgent,
                ...array_filter($event->extra, is_scalar(...)),
            ], static fn ($value) => $value !== null),
            is_string($actorId) && $actorId !== $event->userId ? new ActivitySubject($userClass, $actorId) : null,
        );
    }
}
