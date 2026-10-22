<?php

namespace Wexample\SymfonyUser\EventSubscriber;

use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\PrePersistEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Doctrine\ORM\Events;
use Wexample\SymfonyUser\Entity\AbstractUser;
use Wexample\SymfonyUser\Service\AccountRulesService;

/**
 * The last line before the database: an account breaking the rules is not
 * written, whichever code changed it. A form checks AccountRulesService
 * first, to show the refusal instead of failing the flush.
 */
#[AsDoctrineListener(event: Events::prePersist)]
#[AsDoctrineListener(event: Events::preUpdate)]
class AccountRulesGuardSubscriber
{
    public function __construct(
        private readonly AccountRulesService $accountRules,
    ) {
    }

    public function prePersist(PrePersistEventArgs $args): void
    {
        $this->check($args->getObject());
    }

    public function preUpdate(PreUpdateEventArgs $args): void
    {
        $this->check($args->getObject());
    }

    private function check(object $entity): void
    {
        if ($entity instanceof AbstractUser) {
            $this->accountRules->assertValid((string) $entity->getEmail(), $entity->getRoles());
        }
    }
}
