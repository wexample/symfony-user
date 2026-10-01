<?php

namespace Wexample\SymfonyUser\Enum;

/**
 * Every security fact the package records: the stable codes an audit or a
 * support reads. Failures carry their real cause, in SecurityEvent::$cause.
 */
enum SecurityEventType: string
{
    case LOGIN_SUCCEEDED = 'login.succeeded';
    case LOGIN_FAILED = 'login.failed';
    case LOGIN_SECOND_FACTOR_REQUIRED = 'login.second_factor_required';
    case LOGIN_SECOND_FACTOR_EXPIRED = 'login.second_factor_expired';

    case SECOND_FACTOR_CODE_SENT = 'second_factor.code_sent';
    case SECOND_FACTOR_CODE_RESENT = 'second_factor.code_resent';
    case SECOND_FACTOR_SUCCEEDED = 'second_factor.succeeded';
    case SECOND_FACTOR_FAILED = 'second_factor.failed';
    case SECOND_FACTOR_BACKUP_CODE_USED = 'second_factor.backup_code_used';

    case PASSWORD_RESET_REQUESTED = 'password.reset_requested';
    case PASSWORD_RESET = 'password.reset';
    case PASSWORD_CHANGED = 'password.changed';
    case PASSWORD_SET_BY_ADMIN = 'password.set_by_admin';

    case MAGIC_LINK_REQUESTED = 'magic_link.requested';

    case ACCOUNT_TOTP_ENABLED = 'account.totp_enabled';
    case ACCOUNT_TOTP_DISABLED = 'account.totp_disabled';
    case ACCOUNT_BACKUP_CODES_REGENERATED = 'account.backup_codes_regenerated';
    case ACCOUNT_TRUSTED_DEVICES_REVOKED = 'account.trusted_devices_revoked';
    case ACCOUNT_IMPERSONATION_STARTED = 'account.impersonation_started';
    case ACCOUNT_IMPERSONATION_ENDED = 'account.impersonation_ended';

    case TERMS_ACCEPTED = 'terms.accepted';

    case LOGOUT = 'logout';

    public function isFailure(): bool
    {
        return in_array($this, [self::LOGIN_FAILED, self::SECOND_FACTOR_FAILED], true);
    }
}
