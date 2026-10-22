<?php

namespace Wexample\SymfonyUser\Interface;

/**
 * An entity of the application others are members of: a project, a team, an
 * establishment, a shared document. The package knows nothing else of it.
 */
interface MembershipGroupInterface
{
    public function getId(): mixed;

    /**
     * What the screens call it.
     */
    public function getMembershipLabel(): string;

    /**
     * @return iterable<MembershipInterface>
     */
    public function getMemberships(): iterable;
}
