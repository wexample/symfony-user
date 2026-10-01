<?php

namespace Wexample\SymfonyUser\Enum;

/**
 * Why a change to an account was refused: the stable code an administration
 * form turns into its message, and the journal records.
 */
enum AccountAdministrationRefusal: string
{
    /** The actor would deactivate or lock their own account. */
    case SELF_DEACTIVATION = 'self_deactivation';

    /** The actor would remove one of their own roles. */
    case SELF_DEMOTION = 'self_demotion';

    /** The change would leave a protected role with no active holder. */
    case LAST_PROTECTED_ROLE_HOLDER = 'last_protected_role_holder';

    /** A role restricted to some email domains, on an address outside them. */
    case EMAIL_DOMAIN_NOT_ALLOWED = 'email_domain_not_allowed';

    /** Two roles that are never held together. */
    case EXCLUSIVE_ROLES = 'exclusive_roles';

    /** A role the actor does not administer, granted, removed, or held by the target. */
    case ROLE_NOT_ASSIGNABLE = 'role_not_assignable';

    /** An AccountAdministrationGuardInterface of the application refused the pair. */
    case TARGET_OUT_OF_SCOPE = 'target_out_of_scope';
}
