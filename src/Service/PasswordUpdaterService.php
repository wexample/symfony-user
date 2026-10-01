<?php

namespace Wexample\SymfonyUser\Service;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Wexample\SymfonyUser\Entity\AbstractUser;
use Wexample\SymfonyUser\Enum\SecurityEventType;

class PasswordUpdaterService
{
    public function __construct(
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly EntityManagerInterface $entityManager,
        private readonly TokenStorageInterface $tokenStorage,
        private readonly RequestStack $requestStack,
        private readonly SecurityJournalService $journal,
    ) {
    }

    /**
     * Every other session of the account ends at its next request, and every
     * magic link sent before dies: both are signed with the old hash.
     *
     * Recorded as a reset when $reset, as set by an administrator when the
     * signed-in user is someone else, as changed otherwise.
     */
    public function update(AbstractUser $user, string $plainPassword, bool $reset = false): void
    {
        $user->setPassword($this->passwordHasher->hashPassword($user, $plainPassword));
        $this->entityManager->flush();

        $actor = $this->tokenStorage->getToken()?->getUser();

        if ($reset) {
            $this->journal->record(SecurityEventType::PASSWORD_RESET, $user);
        } elseif ($actor && $actor !== $user) {
            $this->journal->record(SecurityEventType::PASSWORD_SET_BY_ADMIN, $user, extra: ['actor' => $actor->getUserIdentifier()]);
        } else {
            $this->journal->record(SecurityEventType::PASSWORD_CHANGED, $user);
        }

        // The session that changed its own password stays open, under a new id.
        if ($this->tokenStorage->getToken()?->getUser() === $user) {
            $this->requestStack->getSession()->migrate(true);
        }
    }
}
