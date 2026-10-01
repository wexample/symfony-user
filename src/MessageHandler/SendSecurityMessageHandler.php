<?php

namespace Wexample\SymfonyUser\MessageHandler;

use Doctrine\ORM\EntityManagerInterface;
use LogicException;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Wexample\SymfonyUser\Entity\AbstractUser;
use Wexample\SymfonyUser\Enum\SecurityMessageType;
use Wexample\SymfonyUser\Message\SendSecurityMessage;
use Wexample\SymfonyUser\Service\MagicLinkService;
use Wexample\SymfonyUser\Service\PasswordResetService;
use Wexample\SymfonyUser\Service\SecurityMessageService;

/**
 * Builds the link when the mail leaves, from the account as it is then: an
 * account disabled, locked — or activated, for an activation — in between
 * gets nothing.
 */
#[AsMessageHandler]
class SendSecurityMessageHandler
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly MagicLinkService $magicLinkService,
        private readonly PasswordResetService $passwordResetService,
        private readonly SecurityMessageService $securityMessageService,
    ) {
    }

    public function __invoke(SendSecurityMessage $message): void
    {
        $user = $this->entityManager->find($message->userClass, $message->userId);

        if (! $user instanceof AbstractUser || ! $user->isEnabled() || $user->isLocked()) {
            return;
        }

        [$url, $expiresAt] = match ($message->type) {
            SecurityMessageType::MAGIC_LINK => $this->magicLinkService->createLinkUrl($user, $message->targetPath),
            SecurityMessageType::PASSWORD_RESET => $this->passwordResetService->createResetLink($user),
            SecurityMessageType::ACCOUNT_ACTIVATION => $user->getPassword() === null
                ? $this->passwordResetService->createActivationLink($user)
                : [null, null],
            SecurityMessageType::TWO_FACTOR_CODE => throw new LogicException('A code is sent at once, never queued.'),
        };

        if ($url !== null) {
            $this->securityMessageService->send($user, $message->type, $url, $expiresAt);
        }
    }
}
