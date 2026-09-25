<?php

namespace Wexample\SymfonyUser\Interface;

use Symfony\Component\Security\Http\LoginLink\LoginLinkDetails;
use Wexample\SymfonyUser\Entity\AbstractUser;

/**
 * Delivers a magic link to its user. The package sends it by mail; an
 * application aliases this interface to deliver it another way.
 */
interface MagicLinkSenderInterface
{
    public function send(AbstractUser $user, LoginLinkDetails $link): void;
}
