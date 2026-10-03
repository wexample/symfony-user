<?php

namespace Wexample\SymfonyUser\Routing;

/**
 * The routes the package sends users to. The endpoints are its own; the
 * pages are symfony-user-ds's, or the application's under these names when
 * it draws its own screens.
 */
final class UserRoute
{
    // Endpoints, served by this package.
    public const string LOGIN_LINK = 'user_security_login_link';
    public const string LOGOUT = 'user_security_logout';
    public const string PASSWORD_RESET = 'user_password_reset';
    public const string PASSWORD_ACTIVATE = 'user_password_activate';
    public const string TWO_FACTOR_RESEND = 'user_security_two_factor_resend';
    public const string TOTP_REGENERATE = 'user_totp_regenerate';
    public const string IMPERSONATE_SEARCH = 'user_impersonate_search';

    // Pages.
    public const string LOGIN = 'user_security_login';
    public const string TWO_FACTOR = 'user_security_two_factor';
    public const string PASSWORD_FORGOT = 'user_password_forgot';
    public const string PASSWORD_NEW = 'user_password_new';
    public const string PASSWORD_ACTIVATION_INVALID = 'user_password_activation_invalid';
    public const string TERMS = 'user_terms_index';
    public const string TOTP = 'user_totp_index';
    public const string TOTP_BACKUP_CODES = 'user_totp_backup_codes';
    public const string TOTP_SETUP = 'user_totp_setup_index';
    public const string IMPERSONATE = 'user_impersonate_index';

    /** The query parameter telling the forgot page it was reached by a dead link. */
    public const string PARAMETER_LINK = 'link';
    public const string LINK_INVALID = 'invalid';
}
