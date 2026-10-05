# symfony-user

Version: 10.0.3

## Routes

`Routing\UserRoute` names every route the package sends users to. Its endpoints:

| Route | Path | What |
|---|---|---|
| `user_security_login_link` | `/login/link` | check route of the magic links |
| `user_security_logout` | `/logout` | handled by the firewall |
| `user_password_reset` | `/password/reset` | target of the reset mail |
| `user_password_activate` | `/password/activate` | target of the activation mail |
| `user_security_two_factor_resend` | `/login/2fa/resend` | sends the code again (POST) |
| `user_totp_regenerate` | `/account/authenticator/backup-codes/regenerate` | new backup codes (POST) |

The pages — `LOGIN`, `TWO_FACTOR`, `PASSWORD_FORGOT`, `PASSWORD_NEW`, `PASSWORD_ACTIVATION_INVALID`, `TERMS`, `TOTP`, `TOTP_BACKUP_CODES`, `TOTP_SETUP` — are `symfony-user-ds`'s. The texts of the forms and the mails stay here, under `assets/forms/` and `assets/mails/`: a form reads its labels and errors from the domain of its class.

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
{{ form_load(render_pass, login_form, '@WexampleSymfonyUserDsBundle/forms/login_form.html.twig') }}
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

The later steps read the account with `getTunnelUser($cursor)`. The tunnel templates of the application include the bodies `symfony-user-ds` ships: `@WexampleSymfonyUserDsBundle/tunnels/partials/user_mail.html.twig` and `login.html.twig`.

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

## Impersonation

Who may impersonate is the firewall's: its `switch_user` key, and the role it names. Without the key, nobody can, the impersonation page and search answer 404 — declare it under `when@dev` alone for an application impersonating in development only:

```yaml
when@dev:
    security:
        firewalls:
            main:
                switch_user: { role: IS_AUTHENTICATED_FULLY }   # everyone, in development
```

Who may be impersonated is `impersonation.targets`: `administered`, the accounts whose roles the actor administers — the hierarchy, or `administration.manages`, read at each request —, or `any`, every account — a development setting, where the scope of the application's `AccountAdministrationGuardInterface` is not asked either. Never oneself, a disabled or a locked account. `ImpersonationService` applies the rule to the list, the search and — through `ImpersonationGuardSubscriber`, on `SwitchUserEvent` — the switch itself: an account absent from the list cannot be reached by its URL. A voter could not refuse it: with the default strategy, the role voter's grant wins.

The switch happens on the page the chosen account lands on once signed in (`post_login`), so it opens where that account starts. From an impersonation, the page offers the next account and the way back: Symfony leaves the current account before taking the next, so the rights and the rule are always the original account's.

A switch must come from `ImpersonateForm` (`require_intent`): a bare `?_switch_user=` link sent to an administrator switches nobody. A refusal is journaled as `access.denied`, the rule in `extra.reasons` (`impersonation=not_administered`, `no_intent`…).

`GET /account/impersonate/search?q=` (`UserRoute::IMPERSONATE_SEARCH`) answers the accounts matching by email, username or name: 2 characters at least, 20 results, 30 searches a minute per actor. The page is symfony-user-ds's.

## Account picker

Signing in by choosing an account, with no password and no second factor: for an application whose users trust each other, or a demonstration. It is turned on by the firewall alone — no option:

```yaml
security:
    firewalls:
        main:
            custom_authenticators:
                - Wexample\SymfonyUser\Security\Authenticator\LoginFormAuthenticator
                - Wexample\SymfonyUser\Security\Authenticator\AccountPickerAuthenticator
```

Without it, the page (`/login/accounts`, `UserRoute::ACCOUNT_PICKER`, symfony-user-ds) answers 404 and a crafted submission signs nobody in. Every active account can be chosen: all listed up to `impersonation.list_threshold`, searched above (30 searches a minute per IP). The session opens as an `AccountPickerToken`, a class scheb's `security_tokens` does not list, hence no second factor; the account gates still apply, and the journal records `login.succeeded` with the method `account_picker`. Whoever reaches the page signs in as anyone: list it only where that is the intent, or under `when@<env>` for one environment.

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

## Table of Contents

- [Routes](#routes)
- [Showing the login form elsewhere](#showing-the-login-form-elsewhere)
- [Tunnel steps](#tunnel-steps)
- [Magic links in application mails](#magic-links-in-application-mails)
- [The account's language](#the-accounts-language)
- [Passwords](#passwords)
- [Second factor](#second-factor)
- [Roles](#roles)
- [Administering accounts](#administering-accounts)
- [Impersonation](#impersonation)
- [Account picker](#account-picker)
- [After signing in](#after-signing-in)
- [Security journal](#security-journal)
- [Account gates](#account-gates)
- [Architecture](#architecture)
- [Integration in the Suite](#integration-in-the-suite)
- [Dependencies](#dependencies)
- [Versioning & Compatibility Policy](#versioning--compatibility-policy)
- [License](#license)
- [About us](#about-us)
- [Migration Notes](#migration-notes)

## Architecture

Every form of the package is an ordinary `symfony-forms` ajax form: it posts to `/_forms/submit/{name}`, and the front `Form` class reads a `FormResponsePayload` back. The security forms keep that transport, but the firewall answers before the forms bundle does. This section follows a login through those layers.

### Login

src/Form/LoginForm.php is rendered through src/Service/FormProcessor/LoginFormProcessor.php, which is never asked to process it. On submission, src/Security/Authenticator/LoginFormAuthenticator.php `supports()` the forms bundle route for that form name and builds a passport: a `UserBadge` resolved by the firewall provider — src/Repository/AbstractUserRepository.php reads an email or a username —, the password, a CSRF badge using the token id the form was built with, and a remember-me badge.

Symfony then runs its own listeners: login throttling, password check, src/Security/UserChecker.php after the password (so a locked account is only revealed to its owner), password upgrade. src/EventSubscriber/LastLoginSubscriber.php stamps `dateLastLogin`.

`onAuthenticationSuccess()` and `onAuthenticationFailure()` answer JSON with the payload builder of the forms bundle when the caller expects it, a redirect otherwise. Failures become form errors through `LoginFormProcessor::addAuthenticationError()`, which maps unknown user and wrong password to the same message.

### Second factor

scheb/2fa-bundle turns the token of a password login into a `TwoFactorToken` when src/Security/TwoFactor/EmailCodeTwoFactorProvider.php begins, which it does only for the login form route. `prepare_on_login` makes src/Service/TwoFactorCodeService.php send the code with the password check: the code is kept hmac-hashed in session, with its expiry and attempts, and failures are also counted per account in `cache.app`.

`LoginFormAuthenticator` sees the `TwoFactorToken` and answers with the code page, `UserRoute::TWO_FACTOR` (served by symfony-user-ds). The code form posts to the forms bundle route too, which the firewall declares as scheb's `check_path`; src/Security/Handler/TwoFactorResponseHandler.php answers success, failure and "code required" the same way the login does. Trusted devices are scheb's signed cookie, versioned by `AbstractUser::getTrustedTokenVersion()`.

An authenticator app uses scheb's own `totp` provider, and the email provider steps aside for a user who has one. src/Service/TotpService.php keeps the secret being set up in session until a first code proves the app holds it, then stores it encrypted by src/Service/TotpSecretCipherService.php; src/EventSubscriber/TotpSecretSubscriber.php decrypts it on load, in memory only. Backup codes are stored as hashes on the user and checked by scheb before the provider.

### Links

src/Service/MagicLinkService.php wraps the `login_link` handler of the firewall — the one of the current request, or of `main` without a request — and appends a checked local `_target_path`. src/Service/PasswordResetService.php signs reset links with Symfony's `SignatureHasher` over the password hash, so nothing is stored and the new password kills the link. Both reset modes end on a proof kept in session, granted by the reset link or, in magic link mode, by src/EventSubscriber/PasswordResetProofSubscriber.php; src/Service/FormProcessor/SetPasswordFormProcessor.php requires it.

Every link and code leaves through src/Interface/SecurityMessageSenderInterface.php, aliased to src/Service/SecurityMessageMailerSenderService.php, one template per src/Enum/SecurityMessageType.php under `assets/mails/`.

### Tunnel steps

src/Service/Tunnel/Step/LoginStep.php is a plain step: it saves its own URL as the firewall target path when displayed, so the login form, and the second factor after it, come back to it; it then redirects a signed-in visitor to its next cursor, which completes it (`ON_NEXT_REDIRECT`). The tunnel session survives the login: `symfony-tunnels` lets the account signing in take on the anonymous session of its own browser. src/Service/Tunnel/Step/AbstractUserMailStep.php is a form step, its form posting to the step URL like every tunnel form; it keeps the account it goes on with, and the one it created, as session variables of the tunnel.

### Tests

The fixture kernel (tests/Fixtures/App/AppKernel.php) boots the security, forms, loader and scheb bundles, and symfony-user-ds (a dev dependency) so the walks reach real pages, on SQLite in memory, with the stateless CSRF of the Flex recipe. Integration tests walk the flows over HTTP with `disableReboot()`; two traps:

- The test client resets services between requests: the entity manager forgets the entities a test holds, and an array cache is emptied. Reload entities before changing them, and clear `cache.rate_limiter` / `cache.app` in `setUp()` rather than using an array cache.
- Loader pages cannot be rendered twice by the same fixture kernel: controllers redirect with a query flag rather than render an error page, and tests assert the redirect.

Tests run from the design-system containers: `docker exec design_system_local_symfony sh -lc 'cd /var/www/vendor-dev/wexample/symfony-user && php vendor/bin/phpunit'`.

## Integration in the Suite

This package is part of the Wexample Suite — a collection of high-quality, modular tools designed to work seamlessly together across multiple languages and environments.

### Related Packages

The suite includes packages for configuration management, file handling, prompts, and more. Each package can be used independently or as part of the integrated suite.

Visit the [Wexample Suite documentation](https://docs.wexample.com) for the complete package ecosystem.

## Dependencies

- php: >=8.5
- doctrine/orm: ^3.0
- symfony/security-bundle: ^7.4
- wexample/symfony-helpers: >=14.0.0
- wexample/symfony-security: >=2.0.0
- wexample/symfony-translations: >=8.0.0
- symfony/validator: ^7.4
- wexample/symfony-forms: >=10.0.0
- wexample/symfony-loader: >=20.0.0
- wexample/symfony-mail: >=2.0.0
- symfony/form: ^7.4
- symfony/rate-limiter: ^7.4
- symfony/mailer: ^7.4
- symfony/twig-bridge: ^7.4
- scheb/2fa-bundle: ^8.6
- scheb/2fa-trusted-device: ^8.6
- scheb/2fa-totp: ^8.6
- scheb/2fa-backup-code: ^8.6
- endroid/qr-code: ^6.0
- wexample/symfony-tunnels: >=10.0.0

## Versioning & Compatibility Policy

Wexample packages follow **Semantic Versioning** (SemVer):

- **MAJOR**: Breaking changes
- **MINOR**: New features, backward compatible
- **PATCH**: Bug fixes, backward compatible

We maintain backward compatibility within major versions and provide clear migration guides for breaking changes.

## License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

Free to use in both personal and commercial projects.

## About us

[Wexample](https://wexample.com) stands as a cornerstone of the digital ecosystem — a collective of seasoned engineers, researchers, and creators driven by a relentless pursuit of technological excellence. More than a media platform, it has grown into a vibrant community where innovation meets craftsmanship, and where every line of code reflects a commitment to clarity, durability, and shared intelligence.

This packages suite embodies this spirit. Trusted by professionals and enthusiasts alike, it delivers a consistent, high-quality foundation for modern development — open, elegant, and battle-tested. Its reputation is built on years of collaboration, refinement, and rigorous attention to detail, making it a natural choice for those who demand both robustness and beauty in their tools.

Wexample cultivates a culture of mastery. Each package, each contribution carries the mark of a community that values precision, ethics, and innovation — a community proud to shape the future of digital craftsmanship.

## Migration Notes

When upgrading between major versions, refer to the migration guides in the documentation.

Breaking changes are clearly documented with upgrade paths and examples.
