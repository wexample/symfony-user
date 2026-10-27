<?php

namespace Wexample\SymfonyUser\Api\Dto;

use Wexample\SymfonyApi\Api\Dto\AbstractEntityDto;
use Wexample\SymfonyHelpers\Entity\AbstractEntity;
use Wexample\SymfonyUser\Entity\Account;

/**
 * An account as the administration list reads it. No secret, no hash: an
 * address, a name, roles, a state.
 */
class AccountDto extends AbstractEntityDto
{
    public string $email;

    /** The holder's name, or the address when there is none. */
    public string $label;

    /** @var list<string> */
    public array $roles;

    /** active, disabled, locked, or waiting for its holder's first password. */
    public string $state;

    public ?string $dateLastLogin;

    /**
     * @param Account $entity
     */
    public static function fromEntity(AbstractEntity $entity): self
    {
        $dto = parent::fromEntity($entity);

        $dto->email = $entity->email;
        $dto->label = $entity->label;
        $dto->roles = $entity->roles;
        $dto->state = $entity->state;
        $dto->dateLastLogin = $entity->dateLastLogin?->format(DATE_ATOM);

        return $dto;
    }
}
