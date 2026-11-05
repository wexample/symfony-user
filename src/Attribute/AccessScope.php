<?php

namespace Wexample\SymfonyUser\Attribute;

use Attribute;

/**
 * Who may view, edit and delete a record, read from what it hangs from —
 * voted by AccessScopeVoter on the `VIEW`, `EDIT` and `DELETE` of
 * AbstractEntityVoter, tried in this order:
 *
 * - `owner`: a path to the account owning it, which may always, whatever
 *   the rest says;
 * - `group`: a path to its group (MembershipGroupInterface): viewed by the
 *   members holding `memberships.access_scope.view_grant` there, changed by
 *   those holding `edit_grant`;
 * - `parents`: paths to records it belongs to: the first one set decides,
 *   asked the same attribute;
 * - `catalog`: viewed by every signed-in account;
 * - nothing resolved: `memberships.oversee_role` only.
 *
 * Each path is one property of the record, read through its getter; one
 * unset reads as absent. A subclass carrying no attribute of its own follows
 * its parent's — a Doctrine proxy is one. An attribute naming nothing is the
 * overseeing role alone: secrets, connections.
 */
#[Attribute(Attribute::TARGET_CLASS)]
final readonly class AccessScope
{
    /**
     * @param list<string> $parents
     */
    public function __construct(
        public ?string $owner = null,
        public ?string $group = null,
        public array $parents = [],
        public bool $catalog = false,
    ) {
    }
}
