<?php

namespace Wexample\SymfonyUser\Tests\Fixtures\App\Normalizer;

use ArrayObject;
use LogicException;
use Wexample\SymfonyHelpers\Entity\AbstractEntity;
use Wexample\SymfonyHelpers\Normalizer\AbstractEntityNormalizer;
use Wexample\SymfonyUser\Tests\Fixtures\App\Entity\User;

/**
 * An application's own normalizer for its user class, as Syrtis' sign-in
 * answer has one: meant for one response, and failing for whoever it was not
 * written for. The accounts list never reaches it.
 */
class SignInUserNormalizer extends AbstractEntityNormalizer
{
    public static function getEntityClassName(): string
    {
        return User::class;
    }

    protected function normalizeMetadata(
        AbstractEntity $entity,
        ?string $format = null,
        array $context = []
    ): array|string|int|float|bool|ArrayObject|null {
        throw new LogicException('Only the sign-in answer normalizes a user this way.');
    }
}
