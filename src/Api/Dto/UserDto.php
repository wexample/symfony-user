<?php

namespace Wexample\SymfonyUser\Api\Dto;

use Wexample\SymfonyApi\Api\Dto\AbstractEntityDto;
use Wexample\SymfonyHelpers\Entity\AbstractEntity;
use Wexample\SymfonyUser\Entity\AbstractUser;

/**
 * An account as its administrators read it, whatever the application's user
 * class: an address, a name, roles, a state. No password, no secret.
 */
class UserDto extends AbstractEntityDto
{
    public const string STATE_ACTIVE = 'active';
    public const string STATE_DISABLED = 'disabled';
    public const string STATE_LOCKED = 'locked';

    /** Open, its holder has not chosen a password yet. */
    public const string STATE_WAITING = 'waiting';

    public string $email;

    public ?string $username;

    /** The holder's name, or the address when there is none. */
    public string $label;

    /** Every role held, ROLE_USER included: what the list names.
     *
     * @var list<string> */
    public array $roles;

    /** active, disabled, locked, or waiting for its holder's first password. */
    public string $state;

    public ?string $dateLastLogin;

    /**
     * @param AbstractUser $entity
     */
    public static function fromEntity(AbstractEntity $entity): self
    {
        $dto = parent::fromEntity($entity);
        $email = (string) $entity->getEmail();
        $name = method_exists($entity, 'getDisplayName') ? trim((string) $entity->getDisplayName()) : '';

        $dto->email = $email;
        $dto->username = $entity->getUsername();
        $dto->label = $name !== '' && $name !== $email ? $name : $email;
        $dto->roles = $entity->getRoles();
        $dto->state = match (true) {
            $entity->isLocked() => self::STATE_LOCKED,
            ! $entity->isEnabled() => self::STATE_DISABLED,
            $entity->getPassword() === null => self::STATE_WAITING,
            default => self::STATE_ACTIVE,
        };
        $dto->dateLastLogin = $entity->getDateLastLogin()?->format(DATE_ATOM);

        return $dto;
    }
}
