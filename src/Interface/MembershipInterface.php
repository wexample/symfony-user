<?php

namespace Wexample\SymfonyUser\Interface;

/**
 * A member in a group, with the grants it holds there: strings the
 * application declares under `memberships.grants`, any number of them.
 * HasGrantsTrait stores them.
 */
interface MembershipInterface
{
    public function getMember(): MemberInterface;

    public function getGroup(): MembershipGroupInterface;

    /**
     * The grants given, without the ones they include.
     *
     * @return list<string>
     */
    public function getGrants(): array;

    /**
     * @param list<string> $grants
     */
    public function setGrants(array $grants): static;
}
