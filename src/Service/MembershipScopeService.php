<?php

namespace Wexample\SymfonyUser\Service;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Types\Type;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Wexample\SymfonyUser\Interface\MemberInterface;
use Wexample\SymfonyUser\Interface\MembershipGroupInterface;

/**
 * Records belonging to groups, kept to the groups of the signed-in member.
 *
 * Written at each query rather than as a Doctrine filter: a global filter is
 * invisible where the query is read, and one `disable()` forgotten opens every
 * group to everyone.
 */
class MembershipScopeService
{
    public function __construct(
        private readonly MembershipService $membershipService,
        private readonly Security $security,
    ) {
    }

    /**
     * Keeps the rows whose group, at $groupPath (`note.team`), is one of
     * $groups, all of the class the path leads to. No group matches nothing —
     * never everything.
     *
     * @param list<MembershipGroupInterface> $groups
     */
    public function restrict(QueryBuilder $queryBuilder, string $groupPath, array $groups): QueryBuilder
    {
        if (! $groups) {
            return $queryBuilder->andWhere('1 = 0');
        }

        $parameter = 'membership_scope_' . count($queryBuilder->getParameters());
        [$ids, $type] = $this->getDatabaseIds($queryBuilder->getEntityManager(), $groups);

        return $queryBuilder
            ->andWhere(sprintf('%s IN (:%s)', $groupPath, $parameter))
            ->setParameter($parameter, $ids, $type);
    }

    /**
     * Keeps the rows of the groups where the signed-in member holds $grant —
     * every group of theirs with no grant asked. Nobody signed in, or someone
     * who is no member, sees nothing.
     */
    public function restrictToCurrentMember(QueryBuilder $queryBuilder, string $groupPath, ?string $grant = null): QueryBuilder
    {
        return $this->restrict($queryBuilder, $groupPath, $this->getCurrentGroups($grant));
    }

    /**
     * @return list<MembershipGroupInterface>
     */
    public function getCurrentGroups(?string $grant = null): array
    {
        $member = $this->security->getUser();

        return $member instanceof MemberInterface
            ? $this->membershipService->getGroups($member, $grant)
            : [];
    }

    /**
     * Fails with a 404 where the signed-in member does not hold $grant in
     * $group: a 403 would tell an outsider that the record exists.
     *
     * @throws NotFoundHttpException
     */
    public function assertInScope(?MembershipGroupInterface $group, ?string $grant = null): void
    {
        $member = $this->security->getUser();

        if (! $group || ! $member instanceof MemberInterface || ! $this->membershipService->isMember($member, $group, $grant)) {
            throw new NotFoundHttpException();
        }
    }

    /**
     * The ids as the database stores them: Doctrine binds a list of entities
     * by their id objects, which a binary uuid column never matches.
     *
     * @param list<MembershipGroupInterface> $groups
     *
     * @return array{list<mixed>, ArrayParameterType}
     */
    private function getDatabaseIds(EntityManagerInterface $entityManager, array $groups): array
    {
        $metadata = $entityManager->getClassMetadata($groups[0]::class);
        $type = Type::getType($metadata->getTypeOfField($metadata->getSingleIdentifierFieldName()));
        $platform = $entityManager->getConnection()->getDatabasePlatform();

        return [
            array_map(static fn (MembershipGroupInterface $group) => $type->convertToDatabaseValue($group->getId(), $platform), $groups),
            match ($type->getBindingType()) {
                ParameterType::BINARY, ParameterType::LARGE_OBJECT => ArrayParameterType::BINARY,
                ParameterType::INTEGER => ArrayParameterType::INTEGER,
                default => ArrayParameterType::STRING,
            },
        ];
    }
}
