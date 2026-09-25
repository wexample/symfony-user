<?php

namespace Wexample\SymfonyUser\Interface;

use DateTimeImmutable;
use Wexample\SymfonyUser\Entity\AbstractUser;
use Wexample\SymfonyUser\Enum\SecurityLinkType;

/**
 * Delivers a security link (magic link, password reset) to its user. The
 * package sends it by mail; an application aliases this interface to deliver
 * it another way.
 */
interface SecurityLinkSenderInterface
{
    public function send(
        AbstractUser $user,
        SecurityLinkType $type,
        string $url,
        DateTimeImmutable $expiresAt
    ): void;
}
