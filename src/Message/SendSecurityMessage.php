<?php

namespace Wexample\SymfonyUser\Message;

use Wexample\SymfonyUser\Enum\SecurityMessageType;

/**
 * A link mail to send. It names the account and the kind of link, never the
 * link: SendSecurityMessageHandler builds it when it sends, so no transport,
 * retry or failed queue ever stores one.
 */
final class SendSecurityMessage
{
    /**
     * @param class-string $userClass
     * @param string|null $targetPath where a magic link lands once signed in
     */
    public function __construct(
        public readonly string $userClass,
        public readonly string $userId,
        public readonly SecurityMessageType $type,
        public readonly ?string $targetPath = null,
    ) {
    }
}
