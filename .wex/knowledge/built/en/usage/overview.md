## Pages and routes

| Route | Path | What |
|---|---|---|
| `user_security_login` | `/login` | password form, magic link form, link to the reset |
| `user_security_logout` | `/logout` | handled by the firewall |
| `user_security_login_link` | `/login/link` | check route of the magic links |
| `user_security_two_factor` | `/login/2fa` | code form, resend, cancel |
| `user_password_forgot` | `/password/forgot` | reset request |
| `user_password_reset` | `/password/reset` | target of the reset mail |
| `user_password_new` | `/password/new` | new password, once the reset is proven |
| `user_totp_index` | `/account/authenticator` | set the authenticator app up, or turn it off |
| `user_totp_backup_codes` | `/account/authenticator/backup-codes` | the new backup codes, shown once |

Templates live under the bundle `assets/` and are overridden like any `symfony-loader` template.

## Showing the login form elsewhere

The login form is an ajax form: any page can render it, and a successful login lands on the target path saved beforehand — the way a tunnel step would use it.

```php
use TargetPathTrait;

$this->saveTargetPath($request->getSession(), 'main', $this->generateUrl('checkout_payment'));

return $this->renderPage('login_step', [
    'login_form' => $loginFormProcessor->createForm()->createView(),
]);
```

```twig
{{ form_load(render_pass, login_form, '@WexampleSymfonyUserBundle/forms/login_form.html.twig') }}
```

An ajax call reaching a protected URL gets a `401` JSON payload whose action redirects to `/login`, instead of an HTML redirect.

## Tunnel steps

Two steps for `symfony-tunnels`, for a checkout or a sign-up that does not start with a login:

- `LoginStep` (`login`) signs the visitor in with the package's login form — second factor included — and sends them on; a signed-in visitor goes straight through.
- `AbstractUserMailStep` (`user-mail`) asks an email. An activated account goes through the login step, the address typed already; any other address gets an account, not activated, created once per tunnel session. The application extends it:

```php
class CheckoutUserMailStep extends AbstractUserMailStep
{
    protected function getStepAfter(TunnelCursor $cursor): AbstractTunnelStep
    {
        return $this->addressesStep;
    }

    protected function createUser(string $email): AbstractUser
    {
        return new User();
    }

    protected function onUserResolved(AbstractUser $user, TunnelCursor $cursor): void
    {
        $this->cart->setUser($user);
    }
}
```

The later steps read the account with `getTunnelUser($cursor)`. The tunnel templates of the application include the bodies the package ships: `@WexampleSymfonyUserBundle/tunnels/partials/user_mail.html.twig` and `login.html.twig`.

## Magic links in application mails

```php
$link = $magicLinkService->createLink($user, '/invitations/42');
```

The link signs the user in and lands on the given local path. It dies with the next login or password change of the user. `MagicLinkService::createLink()` also works outside a request (commands, workers).

## The account's language

`AbstractUser` implements `HasLocaleInterface` (symfony-translations) and carries a nullable `locale` column, so the mails of an account can be written in its language even when they leave outside its requests (a worker, a webhook). An application adding this version generates a migration for the column.

The application fills it. With `remember_locale: true` and the URL prefixes of symfony-translations, an account reading a page whose URL names a language keeps that language. Otherwise the application calls `setLocale()` where the language is chosen (sign-up, a settings page), or leaves it empty, and mails go out in the default locale.

## Passwords

- `ChangePasswordForm`: the signed-in user, current password required.
- `SetPasswordForm` and `PasswordUpdaterService`: to let an administrator set a password in an application processor.
- `PasswordUpdaterService::update()` ends the other sessions of the account and kills the links sent before.

## Second factor

- `AbstractUser::setEmailTwoFactorEnabled(false)` spares an account the code — unless `two_factor.required` is on, which makes every sign-in ask a second factor, magic link and login after a reset included.
- `two_factor.app_required_roles` makes an authenticator app mandatory for some roles: the email code lets them in once, the authenticator gate then holds them on `/account/authenticator` until it is set up, and it cannot be turned off.
- `AbstractUser::revokeTrustedDevices()` makes every trusted device ask for a code again.
- Only password logins ask for the code; magic links and the login after a reset do not.
- A correct password waiting for its second factor opens nothing: no protected page (an ajax call gets a `401`), no last login date, no remember-me cookie, no impersonation, no reset proof. It waits `two_factor.pending_lifetime` seconds; past it, the session is dropped and the sign-in starts over from the password. Listeners of `LoginSuccessEvent` in the application should skip a `TwoFactorTokenInterface` token the same way: the event comes again once the code is checked.
- An authenticator app, once set up on `/account/authenticator`, replaces the email code. Its ten backup codes are shown once; each signs in once, on `/login/2fa?backup=1`.

## Roles

```php
$repository->findByRoles($reversedRoleHierarchy->getParentRoles('ROLE_MANAGER')); // managers and above
$assignableRoles->canAssign($editor, ['ROLE_ADMIN']);                            // never above the editor
```

`findByRoles()` matches whole roles: `ROLE_ADMIN` does not match `ROLE_SUPER_ADMIN`. For impersonation, enable `switch_user` on the firewall and test `is_granted('IS_IMPERSONATOR')` in templates.

## Administering accounts

An administrator creates an account with `createAccount()`: built by the application — email, roles, its own fields —, it is written enabled with no password, and its holder gets an activation mail to choose one (`/password/activate`, then `/password/new`, signed in afterwards; the second factor and the account gates apply as on any sign-in). With no password, nothing signs in with it. A dead activation link — expired, or the account already activated — shows a page telling to ask for a new one. `sendPasswordMail()` sends the activation mail again to an account waiting for it, a mail to choose a new password to an activated one; an earlier link keeps working until it expires, the password is chosen, or the address changes.

Deactivate, reactivate, lock, unlock and change roles through `AccountAdministrationService`, not the entity setters — those stay for fixtures and migrations:

```php
try {
    $administration->changeRoles($this->getUser(), $account, ['ROLE_MANAGER']);
} catch (AccountAdministrationException $exception) {
    $exception->refusal; // Enum\AccountAdministrationRefusal, and $exception->roles it is about
}
```

Who may administer is the application's access control. The roles an actor administers are those they reach through the hierarchy — or, with `administration.manages`, those their roles manage: a relation apart from the hierarchy, not transitive, granting no security right, for an administrator who must not open the pages of the accounts they manage. An application narrows it further — the accounts of one organization — with an `AccountAdministrationGuardInterface`, asked before every change.

The service refuses:

- `role_not_assignable`: a role the actor does not administer, given or removed, or an account holding one;
- `target_out_of_scope`: an `AccountAdministrationGuardInterface` refused the pair;
- `account_inactive`: a password mail asked for a disabled or locked account;
- `self_deactivation`, `self_demotion`: the actor deactivating, locking, or removing a role from themselves;
- `last_protected_role_holder`: leaving a role of `administration.protected_roles` with no active holder. The holders are counted under a row lock: of two administrators deactivating each other at once, the second is refused;
- `email_domain_not_allowed`, `exclusive_roles`: the rules of `AccountRulesService`.

`AccountRulesService` holds what an account must always be, whoever writes it: a role of `administration.role_email_domains` held — directly or through the hierarchy — by an address of its domains, compared whole; the roles of `administration.exclusive_roles` never given together. Only given roles count there, so an administrator can reach the roles of the accounts they administer. A Doctrine listener applies these rules on every flush; a profile form changing the address calls `assertValid()` first, to show the refusal. A database `CHECK` constraint, in the application's schema, is the last line.

Each change is journaled — `account.created`, `account.activation_sent`, `account.activated`, `account.password_mail_sent`, `account.deactivated`, `.reactivated`, `.locked`, `.unlocked`, `account.roles_changed` with `roles_before` / `roles_after` —, each refusal as `account.change_refused` with its code; `extra.actor_id` names the actor.

## After signing in

A user lands on the page they were sent away from, when `access_control` lets them in; otherwise on the route of the first role of `post_login.routes` they hold, through the hierarchy; otherwise on `post_login.default_route`, or the home page. Every way in ends there — password, second factor, reset, activation, terms accepted, magic link (`LoginLinkSuccessHandler`, the `success_handler` of `login_link`). A page refused by its controller rather than by `access_control` is not foreseen: it answers 403.

## Security journal

Every security fact is dispatched as a `Wexample\SymfonyUser\Event\SecurityEvent`: its `type` (`Enum\SecurityEventType` — `login.failed`, `second_factor.backup_code_used`, `password.set_by_admin`…), the real `cause` of a failure (`unknown_user`, `bad_password`, `disabled`, `locked`, `throttled`, `invalid_csrf`, `invalid_login_link`, `invalid`, `expired`, `too_many_attempts`, `rate_limited`), the `method` of a sign-in (`password`, `magic_link`, `password_reset`, `second_factor`), the user id, IP, user agent, firewall, request id (`X-Request-Id`, or one generated per request — the same for every package of the suite, see `symfony-security`) and UTC time. Its shape is `symfony-security`'s `AbstractSecurityEvent`, shared with the machine token journal of `symfony-api`.

Every access refused to a fully signed-in user — `access_control`, `#[IsGranted]`, a voter — is journaled as `access.denied`, with `extra.route`, `path` (without its query), `http_method`, `roles`, `attributes` and `reasons`: what the voters gave to `Vote::addReason()`, where an application names the scope it refused (`organization=42`). A visitor sent to the login page is not denied. The same denial — user, route, attributes — is journaled once a minute, the next entry carrying in `suppressed` the count held back. The response stays the application's.

Every security mail handed to the sender is journaled as `security_message.sent`, or `security_message.failed` with the exception class, with `extra.message_type` — never its link, code or body.

It never carries a secret. An identifier typed for an unknown account is kept as `extra.identifier_fingerprint`, an HMAC that groups the attempts without the text.

The package writes them to the `user_security` Monolog channel, warnings for failures: give that channel its own handler and retention. An audit package listens to `SecurityEvent` to store them. Facts of the application itself go through `SecurityJournalService::record()`.

The signature of magic and reset links is masked in every log record, the exceptions it carries included: the router logs each request URI, and a 404 quotes the referer. The package declares a masker (`wexample_symfony_user.log_masker.link_signature`, the `hash` query parameter) to `symfony-security`'s redaction processor; an application adds its own there too. Keep `doctrine.dbal.logging` off outside development: the SQL log carries query parameters.

## Account gates

What a signed-in user must do before reaching anything else, once the second factor is checked — whatever the way they signed in. `AccountGateSubscriber` holds them on the page of the first gate still blocking (an ajax call gets a `403` naming it); logout stays open. The package ships two, in this order:

- the authenticator app setup, for `two_factor.app_required_roles`;
- the terms of use, when `terms.version` is set: `/account/terms` shows a link to `terms.text_route`, an accept button, and a way out (sign out). Each acceptance is a `TermsAcceptance` row — user id and identifier, version, UTC time, IP, user agent — never changed nor removed, one per version, journaled as `terms.accepted`. Changing the version asks everyone again at their next request. An administrator impersonating a user is not held, and cannot accept in their name.

An application adds a gate by implementing `Interface\AccountGateInterface`.
