# Extract user authentication from network into symfony-user

Opened: 2026-09-24
Updated: 2026-09-24
Author: agent:archeology

## Read this first — status of this todo

> **This is a proposal for discussion, not an order to code.** It was written by the 2026-09 network archaeology pass. Read it, then discuss it with the owner: every design choice and recommendation below is to be challenged and validated **before** any code is written. Do not start implementing on your own.
>
> - Context: `/home/weeger/Desktop/WIP/WEB/WEXAMPLE/NETWORK/local/network/.wex/knowledge/readme/archeology/index.md.j2` (entry point, order between packages), then `sources.md.j2` (where the legacy code lives: archive repo, branch checkouts, GitLab issues) and the domain page linked below.
> - Pending owner decisions affecting this work are listed in `/home/weeger/Desktop/WIP/WEB/WEXAMPLE/NETWORK/local/network/.wex/knowledge/readme/archeology/recap.md.j2`, section "Décisions qui t'attendent". Where this todo assumes an answer, treat it as an open question.
> - Safety: `NETWORK/local/network` runs on **production data** (real bookkeeping, real invoices in `var/`, a prod dump in `.wex/mysql/dumps/`) — read its code only, never run anything against it. Anonymize any fixture taken from network (bank exports, FEC, mails contain real names/accounts). Never copy secrets found in its history (Stripe keys, tokens, passwords, private keys).

## Goal

Turn `wexample/symfony-user` (1.0.3: empty, no `src/`) into a Symfony 7.4 bundle for user accounts and authentication:
- an abstract user model;
- a password login form authenticator that works both as a page and as AJAX;
- a second factor on untrusted devices (email code now, TOTP later);
- trusted devices;
- magic links;
- password change and reset;
- hardening (throttling, user checker, last login).

The design comes from network branch `develop-131-fos-user` (Dec 2022). The broken parts are rebuilt the modern way. Business fields stay in the app.

## Read first

- Knowledge page (inventory, algorithms, pitfalls): `/home/weeger/Desktop/WIP/WEB/WEXAMPLE/NETWORK/local/network/.wex/knowledge/readme/archeology/user.md.j2`
- Source map: `/home/weeger/Desktop/WIP/WEB/WEXAMPLE/NETWORK/local/network/.wex/knowledge/readme/archeology/sources.md.j2`
- Issues: `/home/weeger/Desktop/WIP/WEB/WEXAMPLE/NETWORK/archeo/gitlab/issues/` #29, #30, #32, #131, #139, #172, #220, #271, #295, #306
- Package rules: run `wex ai::design/rules --formatter php-code` (and `javascript-code`) in this package.
- Style reference app: `/home/weeger/Desktop/WIP/WEB/WEXAMPLE/SERVICES/local/app-board` (it requires this package but uses nothing yet; its `config/packages/security.yaml` is the Flex default).

Source root below: `S=/home/weeger/Desktop/WIP/WEB/WEXAMPLE/NETWORK/archeo/trees/develop-131-fos-user`. Prod (FOS-era reference): `P=/home/weeger/Desktop/WIP/WEB/WEXAMPLE/NETWORK/local/network`. Read only: never run anything in network (production data).

## Prerequisites

- `wexample/symfony-helpers` (>= 9): already has `Entity/AbstractUser`, `Entity/Traits/{UserEntityTrait,HasPasswordTrait,HasRolesTrait,UserWithRolesTrait,UserWithNameTrait}`, `Repository/AbstractUserRepository`, `Voter/*`, `Helper/RoleHelper` (all in `/home/weeger/Desktop/WIP/WEB/WEXAMPLE/PACKAGES/PHP/packages/wexample/symfony-helpers/src`).
- `symfony/security-bundle` 7.4, `symfony/rate-limiter`, `symfony/notifier` or `symfony/mailer`, `doctrine/orm`.
- Owner decision pending (see the questions below): whether to build on `scheb/2fa-bundle`. Do steps 1–3 first: they do not depend on it.

## Steps

1. **Bundle skeleton.** Create `src/WexampleSymfonyUserBundle.php`, `src/DependencyInjection/` (config: route names for login/check/success/failure/ajax-redirect, 2FA policy `always|new_device|never`, code TTL, max attempts, trusted device cookie lifetime), `src/Resources/config/services.yaml`, `composer.json` requirements, and a test kernel under `tests/` (SQLite). Copy the layout of `/home/weeger/Desktop/WIP/WEB/WEXAMPLE/PACKAGES/PHP/packages/wexample/symfony-tunnels/src`.
2. **User model.**
   - Create `Entity/AbstractUser` (MappedSuperclass) implementing `UserInterface`, `PasswordAuthenticatedUserInterface`, `EquatableInterface`. `isEqualTo` compares id, password hash and enabled, **not roles** (#271).
   - Fields: email (unique, lowercased in the setter), username (nullable unique, regex `^[a-z0-9][a-z0-9_-]{2,28}[a-z0-9]$`), password (nullable), roles JSON (always + ROLE_USER), `enabled` (= activated), `locked` (= banned by admin, #30), `dateLastLogin`, `dateCreated`. `getUserIdentifier()` = email.
   - Move the helpers user traits here, or re-use them and fix them: `UserWithNameTrait::getUserIdentifier()` must not return the display name, and `AbstractUserRepository::upgradePassword()` must not reference `App\Entity\User`.
   - Add `Repository/Traits` with `findOneByUserIdentifier(string)`: email, else username only if unique (see `$S/src/Repository/UserRepository.php`, last method).
   - Read: `$S/src/Wex/BaseBundle/Entity/User.php`, `$S/src/Entity/User.php` (security fields only: `enabled`, `lastLogin`, `doubleFactorEmailCode`, `devices`, `hasDeviceForRequest`), `$S/src/Wex/BaseBundle/Repository/UserRepository.php`.
   - Test: identifier lookup (email, username, ambiguous username → null), roles always contain ROLE_USER, email lowercased.
3. **Password login authenticator (no 2FA yet).**
   - Create `Security/Authenticator/LoginFormAuthenticator` (extends `AbstractLoginFormAuthenticator`), `Controller/SecurityController` (login page, check, success, failure, ajax redirect, logout), `Form/LoginForm` (identifier + password + remember me), `Security/UserChecker` (refuse locked or not enabled), a `LoginSuccessEvent` listener that sets `dateLastLogin`.
   - Behaviour to keep: form posts to a check route; the form data is read whatever the form name is (so tunnel forms reuse it); on failure, store the error and go back to the path that showed the form (`login_form_path`); honour a target path set beforehand by a caller (tunnel login step); an XHR hitting a protected URL gets JSON (redirect or open-login instruction), not a 302 to HTML (#32); map errors to generic messages ("unknown user") to avoid enumeration.
   - Read: `$S/src/Security/LoginFormAuthenticator.php`, `$S/src/Controller/SecurityController.php`, `$S/src/Form/Security/LoginForm.php`, `$S/src/Service/FormProcessor/Security/LoginFormProcessor.php`, `$S/config/packages/security.yaml`, `$S/front/forms/security/login_form.fr.yml`, `$S/front/pages/security/login.html.twig`.
   - Tests: login by email, login by username, bad password → form error, locked user refused, target path honoured, XHR entry point returns JSON, last login updated.
4. **Login throttling.** Configure `login_throttling` (RateLimiter) in the recipe/docs. Test: N failures → throttled (#295).
5. **Second factor: email code.**
   - Create `Security/TwoFactor/TwoFactorMethodInterface` and `EmailCodeTwoFactorMethod`.
   - Once the password is valid and the device is not trusted, create a **pending 2FA state** in session (user id, method, expiresAt, attempts) and throw a `NeedsTwoFactorException`. **Never keep the plain password in session** (network did; see the pitfalls on the knowledge page).
   - Code: `random_int` 6 digits, stored **hashed** with `expiresAt` (default 10 min), max 5 attempts then invalidated, resend throttled.
   - Code submission completes the login through a dedicated check or a second authenticator, then marks the device trusted (step 6).
   - Cancel action (use another identifier) and resend action. Send the mail through a mailer or notifier hook the app can override; the code must not be stored in any mail archive.
   - Read: `$S/src/Service/Security/DoubleFactorAuth/AbstractDoubleFactorAuth.php`, `$S/src/Service/Security/DoubleFactorAuth/EmailCodeDoubleFactorAuth.php`, `$S/src/Exception/NeedsDoubleAuthUserException.php`, `$S/src/Exception/BadDoubleAuthCodeException.php`, `$S/front/forms/security/login_form.html.twig`, `$S/front/mails/notification/double_auth_code.html.twig` and `.fr.yml`.
   - Tests: port `$S/tests/Integration/Role/Anonymous/Controller/SecurityControllerTest.php` (new device → mail; renew → new code and 2nd mail; wrong code → error; right code → logged in, code cleared, device created). Add expiry, attempt limit, cancel.
6. **Trusted devices.**
   - Create `Entity/AbstractUserTrustedDevice` (user, tokenHash, userAgent, anonymized IPv4/IPv6 via `IpUtils::anonymize`, dateCreated, dateLastUsed), a repository, and a `TrustedDeviceManager` (issue a signed httpOnly cookie with a random token; recognize it; revoke one or all).
   - Do **not** recognize devices by UA + IP equality as network did.
   - Read: `$S/src/Entity/UserSecurityDevice.php`, `$S/src/Repository/UserSecurityDeviceRepository.php`, `User::hasDeviceForRequest` in `$S/src/Entity/User.php`.
   - Tests: trusted cookie skips 2FA; revoked device asks again; policy `always` always asks; policy `never` never asks.
7. **Front component.**
   - Port `$S/front/components/double-factor-code-char.ts`: 6 digit inputs, auto-advance, arrows and backspace, paste spreads the code, auto-submit when full. Follow the JS design rules.
   - Templates: login, 2FA code, magic-link request; overridable, with French and English translations.
8. **Magic link.**
   - Wrap Symfony `login_link`: `Form/MagicLinkRequestForm` + processor (same response whether or not the user exists), `Notifier/MagicLinkNotification` with a real template (network's was a placeholder), and `MagicLinkFactory::createFor(user, ?targetPath)` for app mails (#139 renewal, #306 invitation).
   - `signature_properties` must include the password hash and `dateLastLogin`.
   - Read: `$S/src/Form/Security/MagicLoginLinkForm.php`, `$S/src/Service/FormProcessor/Security/MagicLoginLinkFormProcessor.php`, `$S/src/Notifier/MagicLoginLinkNotification.php`, the `login_link` block of `$S/config/packages/security.yaml`.
   - Tests: request (known and unknown user give the same response), consume, max uses, expired, invalidated after a password change, target path.
9. **Password management.**
   - `Service/PasswordUpdater` (hash + flush).
   - `Form/ChangePasswordForm`: current password required, except when an admin edits someone else (#29). Repeated new password, `PasswordStrength` + `NotCompromisedPassword` (optional).
   - Admin "set password".
   - Reset flow: the owner decides between a token reset and a magic link.
   - Read the working FOS-era versions: `$P/src/Wex/BaseBundle/Form/Traits/SecurityFormTrait.php`, `$P/src/Form/Entity/User/UserAccountChangePasswordEntityForm.php`, `$P/src/Wex/BaseBundle/Controller/ResettingController.php`, `$P/src/Wex/BaseBundle/Service/MailerUser.php`, `$P/src/Wex/BaseBundle/Form/PasswordType.php` + `Resources/js/components/password-type.ts` (show/hide toggle).
   - Tests: change own password (wrong current → error); admin changes another user's password without the current one; reset or magic flow.
10. **Roles helpers.**
    - Port `$S/src/Wex/BaseBundle/Service/ReversedRoleHierarchy.php` (`getParentRoles`).
    - Add a JSON-safe `queryByRoles` (exact element match; network's `LIKE %ROLE%` also matched ROLE_SUPER_ADMIN when asking for ROLE_ADMIN).
    - Optional: `AssignableRolesProvider` (an editor cannot grant roles above their own; network's roles form let ADMIN grant SUPER_ADMIN, see `$S/src/Service/FormProcessor/Entity/User/UserAccountRolesEntityFormProcessor.php`).
    - Add the `user_is_impersonator()` Twig function from `$P/src/Wex/BaseBundle/Twig/SecurityExtension.php`, and document `switch_user`.
    - Tests: reversed hierarchy on the network hierarchy fixture (CLIENT/WORKER→USER, MANAGER→WORKER, MANDATORY→MANAGER, TREASURER→MANDATORY, ADMIN→TREASURER, SUPER_ADMIN→ADMIN): `getParentRoles(ROLE_WORKER)` returns all roles ≥ WORKER.
11. **TOTP (later, after the owner's decision).** Add `TotpTwoFactorMethod` (encrypted secret, QR provisioning, backup codes), selectable per user.
12. **Docs.** Fill `.wex/knowledge/usage/overview.md.j2` with the install recipe: `security.yaml` example (firewall with the package authenticator, `login_link`, `login_throttling`, `remember_me` with `signature_properties: [password]`, `user_checker`) and how an app extends `AbstractUser`.

## Decisions already implied by the owner or by the network design

- The package hosts password login, email-code 2FA, magic link and, later, TOTP ("the package will also soon host magic-link and authenticator (TOTP) auth").
- 2FA is triggered only for untrusted devices (network default).
- Login accepts email or a unique username.
- The magic link doubles as account recovery.
- Login must work as a page, in an AJAX modal and embedded in a tunnel step (target path set by the caller).
- Voters stay in `symfony-helpers` (already extracted, used by app-board).

## Do not

- Do not store the plain password in session, or 2FA codes in clear. Do not use `rand()`.
- Do not recognize devices by user-agent + IP equality.
- Do not port app business code: memberships, organization, godparent, carts, RocketChat sync and password fallback, notification preferences (`UserContactTypeSubscription`), public profile, cascade `resetUser` deletions, `user:create` + organization.
- Do not depend on network `App\` classes, the old `AdaptiveResponse`, or the network `Mail` entity.
- Do not reuse `develop`/`develop-old-2` auth files (older, pre-2FA versions).
- Do not run anything in `NETWORK/local/network` (production database).
- Do not use Symfony 6-only APIs (`Symfony\Component\Security\Core\Security`, `enable_authenticator_manager`).

## Acceptance criteria

- `composer install` + PHPUnit green in the package test kernel (SQLite), with tests for steps 2–10 as listed.
- app-board can switch from `users_in_memory` to the package with a documented config, without copying code.
- No `App\` reference in `src/`.

## Cross-package notes

- `symfony-testing`: `src/Traits/LoggedUserTestCaseTrait.php` types `App\Entity\User`, and `src/Traits/Application/LoggedUserApplicationTestCaseTrait.php` hardcodes `fos_user_security_login`/`fos_user_security_logout`. Adapt them to this package's route config once step 3 exists.
- Migration of network prod users (for network 2027): roles are PHP-serialized (`DC2Type:array`) and must be converted to JSON; do not reset `enabled` to 0; lowercase emails and check uniqueness before dropping FOS `*_canonical`; bcrypt hashes are compatible.
