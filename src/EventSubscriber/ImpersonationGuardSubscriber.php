<?php

namespace Wexample\SymfonyUser\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Security\Core\Authentication\Token\SwitchUserToken;
use Symfony\Component\Security\Core\Authorization\AccessDecision;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Http\Event\SwitchUserEvent;
use Wexample\SymfonyUser\Entity\AbstractUser;
use Wexample\SymfonyUser\Service\ImpersonationService;

/**
 * The firewall grants a switch to whoever holds its `switch_user` role; this
 * refuses the targets ImpersonationService refuses, and a switch not chosen
 * through the impersonation form. A voter could not: with the default
 * strategy, the role voter's grant wins over any refusal.
 *
 * The refusal reaches the journal as `access.denied`, its reason in
 * `extra.reasons`.
 */
class ImpersonationGuardSubscriber implements EventSubscriberInterface
{
    public const string ATTRIBUTE = 'IMPERSONATE';
    public const string REFUSAL_NO_INTENT = 'no_intent';

    public function __construct(
        private readonly ImpersonationService $impersonation,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        // Before the journal records the impersonation as started.
        return [SwitchUserEvent::class => ['onSwitchUser', 64]];
    }

    public function onSwitchUser(SwitchUserEvent $event): void
    {
        $token = $event->getToken();

        // Leaving an impersonation carries the original token.
        if (! $token instanceof SwitchUserToken) {
            return;
        }

        $actor = $token->getOriginalToken()->getUser();
        $target = $event->getTargetUser();

        if (! $actor instanceof AbstractUser || ! $target instanceof AbstractUser) {
            return;
        }

        $refusal = $this->impersonation->getRefusal($actor, $target)
            ?? ($this->impersonation->consumeIntent($target) ? null : self::REFUSAL_NO_INTENT);

        if ($refusal !== null) {
            throw $this->createRefusal($refusal);
        }
    }

    private function createRefusal(string $reason): AccessDeniedException
    {
        $vote = new Vote();
        $vote->voter = self::class;
        $vote->result = VoterInterface::ACCESS_DENIED;
        $vote->addReason('impersonation=' . $reason);

        $decision = new AccessDecision();
        $decision->isGranted = false;
        $decision->votes = [$vote];

        $exception = new AccessDeniedException('Impersonation refused.');
        $exception->setAttributes(self::ATTRIBUTE);
        $exception->setAccessDecision($decision);

        return $exception;
    }
}
