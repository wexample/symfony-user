<?php

namespace Wexample\SymfonyUser\Security\TwoFactor;

use Scheb\TwoFactorBundle\Security\TwoFactor\Backup\BackupCodeManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\AsDecorator;
use Symfony\Component\DependencyInjection\Attribute\AutowireDecorated;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Wexample\SymfonyUser\Entity\AbstractUser;
use Wexample\SymfonyUser\Enum\SecurityEventType;
use Wexample\SymfonyUser\Service\SecurityJournalService;

/**
 * Records a backup code spent, and how many are left: scheb checks them
 * before any provider, with no event of its own.
 */
#[AsDecorator('scheb_two_factor.backup_code_manager', onInvalid: ContainerInterface::IGNORE_ON_INVALID_REFERENCE)]
class JournaledBackupCodeManager implements BackupCodeManagerInterface
{
    public function __construct(
        #[AutowireDecorated]
        private readonly BackupCodeManagerInterface $inner,
        private readonly SecurityJournalService $journal,
    ) {
    }

    public function isBackupCode(object $user, string $code): bool
    {
        return $this->inner->isBackupCode($user, $code);
    }

    public function invalidateBackupCode(object $user, string $code): void
    {
        $this->inner->invalidateBackupCode($user, $code);

        if ($user instanceof AbstractUser) {
            $this->journal->record(
                SecurityEventType::SECOND_FACTOR_BACKUP_CODE_USED,
                $user,
                extra: ['remaining' => $user->countBackupCodes()]
            );
        }
    }
}
