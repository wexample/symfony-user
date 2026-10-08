<?php

namespace Wexample\SymfonyUser\Interface;

/**
 * What the screens need of one kind of group to manage its members: which
 * classes they are, and how a membership is made and unmade — the entities
 * being the application's. One per kind of group; the tag follows the class.
 */
interface MembershipManagerInterface
{
    public const string TAG = 'wexample_user.membership_manager';

    /**
     * The kind of group in urls: `team` in `/memberships/team/{id}`.
     */
    public static function getName(): string;

    /**
     * @return class-string<MembershipGroupInterface>
     */
    public function getGroupClass(): string;

    /**
     * What may be added as a member, searched for by its class: the entity
     * carries symfony-search's `#[Searchable]`.
     *
     * @return class-string<MemberInterface>
     */
    public function getMemberClass(): string;

    /**
     * A new membership, linked on both sides, not persisted yet.
     *
     * @param list<string> $grants
     */
    public function createMembership(MembershipGroupInterface $group, MemberInterface $member, array $grants): MembershipInterface;

    /**
     * Unlinks $membership from both sides; it is removed and flushed after.
     */
    public function removeMembership(MembershipInterface $membership): void;
}
