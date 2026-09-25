# symfony-user

Version: 7.0.0

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

## Passwords

- `ChangePasswordForm`: the signed-in user, current password required.
- `SetPasswordForm` and `PasswordUpdaterService`: to let an administrator set a password in an application processor.
- `PasswordUpdaterService::update()` ends the other sessions of the account and kills the links sent before.

## Second factor

- `AbstractUser::setEmailTwoFactorEnabled(false)` spares an account the code.
- `AbstractUser::revokeTrustedDevices()` makes every trusted device ask for a code again.
- Only password logins ask for the code; magic links and the login after a reset do not.
- An authenticator app, once set up on `/account/authenticator`, replaces the email code. Its ten backup codes are shown once; each signs in once, on `/login/2fa?backup=1`.

## Roles

```php
$repository->findByRoles($reversedRoleHierarchy->getParentRoles('ROLE_MANAGER')); // managers and above
$assignableRoles->canAssign($editor, ['ROLE_ADMIN']);                            // never above the editor
```

`findByRoles()` matches whole roles: `ROLE_ADMIN` does not match `ROLE_SUPER_ADMIN`. For impersonation, enable `switch_user` on the firewall and test `is_granted('IS_IMPERSONATOR')` in templates.

## Table of Contents

- [Pages and routes](#pages-and-routes)
- [Showing the login form elsewhere](#showing-the-login-form-elsewhere)
- [Tunnel steps](#tunnel-steps)
- [Magic links in application mails](#magic-links-in-application-mails)
- [Passwords](#passwords)
- [Second factor](#second-factor)
- [Roles](#roles)
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

`LoginFormAuthenticator` sees the `TwoFactorToken` and answers with the code page, src/Controller/Pages/TwoFactorController.php. The code form posts to the forms bundle route too, which the firewall declares as scheb's `check_path`; src/Security/Handler/TwoFactorResponseHandler.php answers success, failure and "code required" the same way the login does. Trusted devices are scheb's signed cookie, versioned by `AbstractUser::getTrustedTokenVersion()`.

An authenticator app uses scheb's own `totp` provider, and the email provider steps aside for a user who has one. src/Service/TotpService.php keeps the secret being set up in session until a first code proves the app holds it, then stores it encrypted by src/Service/TotpSecretCipherService.php; src/EventSubscriber/TotpSecretSubscriber.php decrypts it on load, in memory only. Backup codes are stored as hashes on the user and checked by scheb before the provider.

### Links

src/Service/MagicLinkService.php wraps the `login_link` handler of the firewall — the one of the current request, or of `main` without a request — and appends a checked local `_target_path`. src/Service/PasswordResetService.php signs reset links with Symfony's `SignatureHasher` over the password hash, so nothing is stored and the new password kills the link. Both reset modes end on a proof kept in session, granted by the reset link or, in magic link mode, by src/EventSubscriber/PasswordResetProofSubscriber.php; src/Service/FormProcessor/SetPasswordFormProcessor.php requires it.

Every link and code leaves through src/Interface/SecurityMessageSenderInterface.php, aliased to src/Service/SecurityMessageMailerSenderService.php, one template per src/Enum/SecurityMessageType.php under `assets/mails/`.

### Tunnel steps

src/Service/Tunnel/Step/LoginStep.php is a plain step: it saves its own URL as the firewall target path when displayed, so the login form, and the second factor after it, come back to it; it then redirects a signed-in visitor to its next cursor, which completes it (`ON_NEXT_REDIRECT`). The tunnel session survives the login: `symfony-tunnels` lets the account signing in take on the anonymous session of its own browser. src/Service/Tunnel/Step/AbstractUserMailStep.php is a form step, its form posting to the step URL like every tunnel form; it keeps the account it goes on with, and the one it created, as session variables of the tunnel.

### Tests

The fixture kernel (tests/Fixtures/App/AppKernel.php) boots the security, forms, loader and scheb bundles on SQLite in memory, with the stateless CSRF of the Flex recipe. Integration tests walk the flows over HTTP with `disableReboot()`; two traps:

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
- wexample/symfony-helpers: >=11.0.0
- wexample/symfony-translations: >=4.0.0
- symfony/validator: ^7.4
- wexample/symfony-forms: >=8.0.0
- wexample/symfony-loader: >=14.0.0
- symfony/form: ^7.4
- symfony/rate-limiter: ^7.4
- symfony/mailer: ^7.4
- symfony/twig-bridge: ^7.4
- scheb/2fa-bundle: ^8.6
- scheb/2fa-trusted-device: ^8.6
- scheb/2fa-totp: ^8.6
- scheb/2fa-backup-code: ^8.6
- endroid/qr-code: ^6.0
- wexample/symfony-tunnels: >=8.0.0

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
