<?php

namespace Wexample\SymfonyUser\Entity;

use DateTimeImmutable;
use Wexample\Pseudocode\Attribute\PseudocodeExport;
use Wexample\SymfonyHelpers\Entity\AbstractEntity;
use Wexample\SymfonyHelpers\Helper\RoleHelper;

/**
 * An account as an administration list reads it, whatever the application's
 * user class: never stored, built from the user it shares its id with.
 */
#[PseudocodeExport(inherited: true)]
class Account extends AbstractEntity
{
    public const string STATE_ACTIVE = 'active';
    public const string STATE_DISABLED = 'disabled';
    public const string STATE_LOCKED = 'locked';

    /** Open, its holder has not chosen a password yet. */
    public const string STATE_WAITING = 'waiting';

    public readonly string $email;

    /** The holder's name, or the address when there is none. */
    public readonly string $label;

    /** @var list<string> */
    public readonly array $roles;

    /** active, disabled, locked, or waiting for its holder's first password. */
    public readonly string $state;

    public readonly ?DateTimeImmutable $dateLastLogin;

    /**
     * @param list<string> $roles
     */
    public function __construct(
        AbstractUser $user,
        string $email,
        string $label,
        array $roles,
        string $state,
        ?DateTimeImmutable $dateLastLogin,
    ) {
        $this->id = $user->getId();
        $this->email = $email;
        $this->label = $label;
        $this->roles = $roles;
        $this->state = $state;
        $this->dateLastLogin = $dateLastLogin;
    }

    public static function fromUser(AbstractUser $user): self
    {
        $email = (string) $user->getEmail();
        $name = method_exists($user, 'getDisplayName') ? trim((string) $user->getDisplayName()) : '';

        return new self(
            $user,
            email: $email,
            label: $name !== '' && $name !== $email ? $name : $email,
            roles: array_values(array_diff($user->getRoles(), [RoleHelper::ROLE_USER])),
            state: match (true) {
                $user->isLocked() => self::STATE_LOCKED,
                ! $user->isEnabled() => self::STATE_DISABLED,
                $user->getPassword() === null => self::STATE_WAITING,
                default => self::STATE_ACTIVE,
            },
            dateLastLogin: $user->getDateLastLogin(),
        );
    }
}
