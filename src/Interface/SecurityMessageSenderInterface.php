<?php

namespace Wexample\SymfonyUser\Interface;

use DateTimeImmutable;
use Wexample\SymfonyUser\Entity\AbstractUser;
use Wexample\SymfonyUser\Enum\SecurityMessageType;

/**
 * Delivers a security message (a link, or a code) to its user. The package
 * sends it by mail; an application aliases this interface to deliver it
 * another way. The value is a secret: it must not be kept anywhere, a mail
 * archive included.
 */
interface SecurityMessageSenderInterface
{
    public function send(
        AbstractUser $user,
        SecurityMessageType $type,
        string $value,
        DateTimeImmutable $expiresAt
    ): void;
}
