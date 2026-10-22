<?php

namespace Wexample\SymfonyUser\EventSubscriber;

use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\PreRemoveEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Doctrine\ORM\Events;
use LogicException;
use Wexample\SymfonyUser\Entity\TermsAcceptance;

/**
 * An acceptance is a proof: once written, nothing in the application changes
 * or removes it — a disabled account keeps its history.
 */
#[AsDoctrineListener(event: Events::preUpdate)]
#[AsDoctrineListener(event: Events::preRemove)]
class TermsAcceptanceGuardSubscriber
{
    public function preUpdate(PreUpdateEventArgs $args): void
    {
        $this->refuse($args->getObject());
    }

    public function preRemove(PreRemoveEventArgs $args): void
    {
        $this->refuse($args->getObject());
    }

    private function refuse(object $entity): void
    {
        if ($entity instanceof TermsAcceptance) {
            throw new LogicException('A terms acceptance is a proof: it is never changed nor removed.');
        }
    }
}
