<?php

namespace Wexample\SymfonyUser\Interface;

/**
 * An account acting for other members — the organizations it belongs to,
 * where a group counts organizations among its members: it holds their grants
 * beside its own.
 */
interface RepresentativeInterface extends MemberInterface
{
    /**
     * @return iterable<MemberInterface>
     */
    public function getRepresentedMembers(): iterable;
}
