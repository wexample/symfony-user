<?php

namespace Wexample\SymfonyUser\Security;

use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Wexample\SymfonyUser\Entity\AbstractUser;

/**
 * Refuses locked and not yet activated accounts. The check runs after the
 * credentials, so the account state is only revealed to its owner — and only
 * when `reveal_account_status` is on.
 *
 * It runs twice: AccountStatusSubscriber calls it right after the credentials,
 * before a second factor is asked; Symfony calls checkPostAuth() once the
 * authentication succeeds, which scheb/2fa-bundle postpones to the code.
 */
class UserChecker implements UserCheckerInterface
{
    public const string ERROR_ACCOUNT_LOCKED = 'error.account_locked';
    public const string ERROR_ACCOUNT_DISABLED = 'error.account_disabled';

    public function checkPreAuth(UserInterface $user): void
    {
    }

    public function checkPostAuth(UserInterface $user, ?TokenInterface $token = null): void
    {
        $this->checkAccountStatus($user);
    }

    public function checkAccountStatus(UserInterface $user): void
    {
        if (! $user instanceof AbstractUser) {
            return;
        }

        if ($user->isLocked()) {
            throw new CustomUserMessageAccountStatusException(self::ERROR_ACCOUNT_LOCKED);
        }

        if (! $user->isEnabled()) {
            throw new CustomUserMessageAccountStatusException(self::ERROR_ACCOUNT_DISABLED);
        }
    }
}
