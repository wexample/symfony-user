<?php

namespace Wexample\SymfonyUser\Service;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Wexample\SymfonyUser\Entity\AbstractUser;

class PasswordUpdaterService
{
    public function __construct(
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly EntityManagerInterface $entityManager,
        private readonly TokenStorageInterface $tokenStorage,
        private readonly RequestStack $requestStack,
    ) {
    }

    /**
     * Every other session of the account ends at its next request, and every
     * magic link sent before dies: both are signed with the old hash.
     */
    public function update(AbstractUser $user, string $plainPassword): void
    {
        $user->setPassword($this->passwordHasher->hashPassword($user, $plainPassword));
        $this->entityManager->flush();

        // The session that changed its own password stays open, under a new id.
        if ($this->tokenStorage->getToken()?->getUser() === $user) {
            $this->requestStack->getSession()->migrate(true);
        }
    }
}
