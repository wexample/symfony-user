<?php

namespace Wexample\SymfonyUser\Repository;

use Wexample\SymfonyHelpers\Repository\AbstractRepository;
use Wexample\SymfonyUser\Entity\AbstractUser;
use Wexample\SymfonyUser\Entity\TermsAcceptance;

/**
 * @method TermsAcceptance[] findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class TermsAcceptanceRepository extends AbstractRepository
{
    public static function getEntityClassName(): string
    {
        return TermsAcceptance::class;
    }

    public function hasAccepted(AbstractUser $user, string $version): bool
    {
        return (bool) $this->findOneBy(['userId' => $user->getId(), 'version' => $version]);
    }

    /**
     * Every version $user accepted, the latest last.
     *
     * @return list<TermsAcceptance>
     */
    public function findHistory(AbstractUser $user): array
    {
        return $this->findBy(['userId' => $user->getId()], ['acceptedAt' => 'ASC']);
    }
}
