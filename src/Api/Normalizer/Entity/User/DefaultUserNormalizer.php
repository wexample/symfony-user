<?php

namespace Wexample\SymfonyUser\Api\Normalizer\Entity\User;

use ArrayObject;
use Wexample\SymfonyHelpers\Entity\AbstractEntity;
use Wexample\SymfonyHelpers\Interface\NormalizableDataInterface;
use Wexample\SymfonyHelpers\Normalizer\AbstractEntityNormalizer;
use Wexample\SymfonyUser\Api\Dto\UserDto;
use Wexample\SymfonyUser\Entity\AbstractUser;

/**
 * Any user, whatever the class the application gave it, read as the one
 * `user` type the front knows.
 */
class DefaultUserNormalizer extends AbstractEntityNormalizer
{
    public const string TYPE = 'user';

    public static function getEntityClassName(): string
    {
        return AbstractUser::class;
    }

    public function normalize(
        mixed $data,
        ?string $format = null,
        array $context = []
    ): array|string|int|float|bool|ArrayObject|null {
        return ['type' => self::TYPE] + parent::normalize($data, $format, $context);
    }

    public function normalizeEntity(
        AbstractUser|AbstractEntity $entity,
        ?string $format = null,
        array $context = []
    ): array|string|int|float|bool|ArrayObject|NormalizableDataInterface|null {
        return UserDto::fromEntity($entity);
    }
}
