<?php

namespace Wexample\SymfonyUser\Enum;

/**
 * Why a change to the members of a group was refused: the stable code a form
 * turns into its message, and the journal records.
 */
enum MembershipAdministrationRefusal: string
{
    /** The actor does not administer the group. */
    case GROUP_NOT_ADMINISTERED = 'group_not_administered';

    /** The member already belongs to the group. */
    case ALREADY_MEMBER = 'already_member';

    /** A membership with no grant, or with one the application did not declare. */
    case INVALID_GRANTS = 'invalid_grants';

    /** The change would leave the group with nobody administering it. */
    case LAST_ADMINISTRATOR = 'last_administrator';
}
