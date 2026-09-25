<?php

namespace Wexample\SymfonyUser\EventSubscriber;

use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\PostLoadEventArgs;
use Doctrine\ORM\Events;
use Wexample\SymfonyUser\Entity\AbstractUser;
use Wexample\SymfonyUser\Service\TotpSecretCipherService;

/**
 * Gives a loaded user its authenticator secret back, in clear, in memory only.
 */
#[AsDoctrineListener(event: Events::postLoad)]
class TotpSecretSubscriber
{
    public function __construct(
        private readonly TotpSecretCipherService $cipher
    ) {
    }

    public function postLoad(PostLoadEventArgs $args): void
    {
        $user = $args->getObject();

        if (! $user instanceof AbstractUser || ! $encrypted = $user->getTotpSecretEncrypted()) {
            return;
        }

        $user->setTotpSecret($this->cipher->decrypt($encrypted), $encrypted);
    }
}
