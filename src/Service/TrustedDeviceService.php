<?php

namespace Wexample\SymfonyUser\Service;

use Doctrine\ORM\EntityManagerInterface;
use Wexample\SymfonyUser\Entity\AbstractUser;
use Wexample\SymfonyUser\Enum\SecurityEventType;

class TrustedDeviceService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly SecurityJournalService $journal,
    ) {
    }

    /**
     * Every device trusted so far asks for a code again at its next login.
     */
    public function revokeAll(AbstractUser $user): void
    {
        $user->revokeTrustedDevices();
        $this->entityManager->flush();

        $this->journal->record(SecurityEventType::ACCOUNT_TRUSTED_DEVICES_REVOKED, $user);
    }
}
