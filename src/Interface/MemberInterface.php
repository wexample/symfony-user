<?php

namespace Wexample\SymfonyUser\Interface;

/**
 * What holds grants in groups: an account most of the time, an organization
 * where the application has its members act together.
 */
interface MemberInterface
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
