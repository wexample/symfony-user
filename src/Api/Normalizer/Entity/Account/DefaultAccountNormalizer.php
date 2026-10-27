<?php

namespace Wexample\SymfonyUser\Api\Normalizer\Entity\Account;

use ArrayObject;
use Wexample\SymfonyHelpers\Entity\AbstractEntity;
use Wexample\SymfonyHelpers\Interface\NormalizableDataInterface;
use Wexample\SymfonyHelpers\Normalizer\AbstractEntityNormalizer;
use Wexample\SymfonyUser\Api\Dto\AccountDto;
use Wexample\SymfonyUser\Entity\Account;

class DefaultAccountNormalizer extends AbstractEntityNormalizer
{
    public static function getEntityClassName(): string
    {
        return Account::class;
    }

    public function normalizeEntity(
        Account|AbstractEntity $entity,
        ?string $format = null,
        array $context = []
    ): array|string|int|float|bool|ArrayObject|NormalizableDataInterface|null {
        return AccountDto::fromEntity($entity);
    }
}
