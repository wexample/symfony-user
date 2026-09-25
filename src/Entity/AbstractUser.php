<?php

namespace Wexample\SymfonyUser\Entity;

use DateTimeImmutable;
use DateTimeInterface;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Scheb\TwoFactorBundle\Model\TrustedDeviceInterface;
use Symfony\Component\Security\Core\User\EquatableInterface;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Validator\Constraints as Assert;
use Wexample\SymfonyHelpers\Entity\AbstractEntity;
use Wexample\SymfonyHelpers\Entity\Interfaces\UserEntityInterface;
use Wexample\SymfonyHelpers\Entity\Traits\HasDateCreatedTrait;
use Wexample\SymfonyHelpers\Helper\RoleHelper;

/**
 * The security part of a user account. Business fields (name, profile,
 * memberships…) belong to the application's own User class extending this one.
 *
 * Not a mapped superclass, like AbstractEntity above it: Doctrine maps these
 * columns straight into the concrete entity.
 */
abstract class AbstractUser extends AbstractEntity implements
    UserEntityInterface,
    UserInterface,
    PasswordAuthenticatedUserInterface,
    EquatableInterface,
    TrustedDeviceInterface
{
    use HasDateCreatedTrait;

    public const string USERNAME_PATTERN = '/^[a-z0-9][a-z0-9_-]{2,28}[a-z0-9]$/';

    #[ORM\Column(type: Types::STRING, length: 180, unique: true)]
    #[Assert\NotBlank]
    #[Assert\Email]
    #[Assert\Length(max: 180)]
    protected ?string $email = null;

    #[ORM\Column(type: Types::STRING, length: 30, unique: true, nullable: true)]
    #[Assert\Regex(pattern: self::USERNAME_PATTERN)]
    protected ?string $username = null;

    /**
     * Null for an account that only signs in through magic links.
     */
    #[ORM\Column(type: Types::STRING, nullable: true)]
    protected ?string $password = null;

    /**
     * @var list<string>
     */
    #[ORM\Column(type: Types::JSON)]
    protected array $roles = [];

    /**
     * False until the account is activated.
     */
    #[ORM\Column(type: Types::BOOLEAN)]
    protected bool $enabled = false;

    /**
     * Set by an administrator to ban the account.
     */
    #[ORM\Column(type: Types::BOOLEAN)]
    protected bool $locked = false;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    protected ?DateTimeImmutable $dateLastLogin = null;

    /**
     * Asks a code sent by email after the password, on a device not trusted yet.
     */
    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => true])]
    protected bool $emailTwoFactorEnabled = true;

    /**
     * Part of the signature of every trusted device cookie: raising it
     * revokes them all.
     */
    #[ORM\Column(type: Types::INTEGER, options: ['default' => 0])]
    protected int $trustedTokenVersion = 0;

    public function __construct()
    {
        parent::__construct();

        $this->setDateCreatedNow();
    }

    public function getUserIdentifier(): string
    {
        return (string) $this->email;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = mb_strtolower(trim($email));

        return $this;
    }

    public function getUsername(): ?string
    {
        return $this->username;
    }

    public function setUsername(?string $username): static
    {
        $this->username = $username === null ? null : mb_strtolower(trim($username));

        return $this;
    }

    public function getPassword(): ?string
    {
        return $this->password;
    }

    /**
     * Takes a hash, never a plain password.
     */
    public function setPassword(?string $password): static
    {
        $this->password = $password;

        return $this;
    }

    /**
     * @return list<string>
     */
    public function getRoles(): array
    {
        return array_values(array_unique([...$this->roles, RoleHelper::ROLE_USER]));
    }

    /**
     * @param list<string> $roles
     */
    public function setRoles(array $roles): static
    {
        // ROLE_USER is implied by getRoles(), storing it would only add noise.
        $this->roles = array_values(array_unique(array_diff($roles, [RoleHelper::ROLE_USER])));

        return $this;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function setEnabled(bool $enabled): static
    {
        $this->enabled = $enabled;

        return $this;
    }

    public function isLocked(): bool
    {
        return $this->locked;
    }

    public function setLocked(bool $locked): static
    {
        $this->locked = $locked;

        return $this;
    }

    public function getDateLastLogin(): ?DateTimeImmutable
    {
        return $this->dateLastLogin;
    }

    public function setDateLastLogin(?DateTimeInterface $dateLastLogin): static
    {
        $this->dateLastLogin = $dateLastLogin === null
            ? null
            : DateTimeImmutable::createFromInterface($dateLastLogin);

        return $this;
    }

    public function isEmailTwoFactorEnabled(): bool
    {
        return $this->emailTwoFactorEnabled;
    }

    public function setEmailTwoFactorEnabled(bool $emailTwoFactorEnabled): static
    {
        $this->emailTwoFactorEnabled = $emailTwoFactorEnabled;

        return $this;
    }

    public function getTrustedTokenVersion(): int
    {
        return $this->trustedTokenVersion;
    }

    /**
     * Every device trusted so far asks for a code again at its next login.
     */
    public function revokeTrustedDevices(): static
    {
        ++$this->trustedTokenVersion;

        return $this;
    }

    /**
     * Decides whether the user stored in session is still the one in database.
     * Roles are left out on purpose: changing a role must not log the user out.
     */
    public function isEqualTo(UserInterface $user): bool
    {
        return $user instanceof self
            && $this->getId()->equals($user->getId())
            && $this->password === $user->getPassword()
            && $this->enabled === $user->isEnabled()
            && $this->locked === $user->isLocked();
    }

    #[\Deprecated]
    public function eraseCredentials(): void
    {
    }
}
