<?php

namespace Wexample\SymfonyUser\Entity;

use DateTimeImmutable;
use DateTimeZone;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;
use Wexample\SymfonyHelpers\Entity\AbstractEntity;
use Wexample\SymfonyUser\Repository\TermsAcceptanceRepository;

/**
 * The proof a user accepted a version of the terms: one row per version
 * accepted, never changed nor removed (TermsAcceptanceGuardSubscriber).
 *
 * The user is kept by id and identifier, not by relation: it works with any
 * user class, and the proof outlives the account.
 */
#[ORM\Entity(repositoryClass: TermsAcceptanceRepository::class)]
#[ORM\Table(name: 'user_terms_acceptance')]
#[ORM\UniqueConstraint(name: 'user_terms_acceptance_user_version', columns: ['user_id', 'version'])]
class TermsAcceptance extends AbstractEntity
{
    #[ORM\Column(type: UuidType::NAME)]
    private Uuid $userId;

    #[ORM\Column(type: Types::STRING, length: 180)]
    private string $userIdentifier;

    #[ORM\Column(type: Types::STRING, length: 64)]
    private string $version;

    /**
     * UTC.
     */
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $acceptedAt;

    #[ORM\Column(type: Types::STRING, length: 45, nullable: true)]
    private ?string $ip;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $userAgent;

    public function __construct(
        AbstractUser $user,
        string $version,
        ?string $ip = null,
        ?string $userAgent = null,
    ) {
        parent::__construct();

        $this->userId = $user->getId();
        $this->userIdentifier = $user->getUserIdentifier();
        $this->version = $version;
        $this->acceptedAt = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $this->ip = $ip;
        $this->userAgent = $userAgent;
    }

    public function getUserId(): Uuid
    {
        return $this->userId;
    }

    public function getUserIdentifier(): string
    {
        return $this->userIdentifier;
    }

    public function getVersion(): string
    {
        return $this->version;
    }

    public function getAcceptedAt(): DateTimeImmutable
    {
        return $this->acceptedAt;
    }

    public function getIp(): ?string
    {
        return $this->ip;
    }

    public function getUserAgent(): ?string
    {
        return $this->userAgent;
    }
}
