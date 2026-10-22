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

### A password its holder did not choose

The forced change reuses the reset walk whole rather than adding a second password page beside it. `PasswordResetService::getProofUser()` — "who may set a password without typing the current one" — answers the holder of a session proof, and, failing that, the signed-in user whose `passwordChangeRequired` flag is up; `hasProof()` tells the two apart where it matters, which is the journal (`password.reset` against `password.changed`) and whatever a page wants to say about why it is being read. src/Security/Gate/PasswordChangeGate.php holds the account on that page, and src/Service/PasswordUpdaterService.php raises or clears the flag as it writes the password — raised when the actor is somebody else than the account, cleared in every other case.

An impersonated account is left out of both: `getProofUser()` refuses a `SwitchUserToken` and so does the gate, or an administrator visiting an account would be choosing its password.

### The administration screens

Two forms whose processors act inside `formIsValid()`: most of what the rules refuse — a guard of the application, a protected role left without a holder — is only known by trying, and past `onValid()` the answer is already a success. src/Service/FormProcessor/AccountCreateFormProcessor.php keeps the account it opened, src/Service/FormProcessor/AccountRolesFormProcessor.php the one it is editing, and `onValid()` is left with the notification and where to go next.

Which account a submission is about travels in its URL — the entity form route of symfony-forms, `/_forms/submit/{name}/entity/{id}` — and never in a field: `handleSubmittedRequest()` reads the id, `createForm()` is handed the account and fills one switch per role from it. A switch per role rather than a multiple select, which the design system's `select_input` does not draw; the field is the role lowercased, and src/Traits/AccountRolesFormTrait.php is where both forms get it.

src/Controller/AccountActionController.php serves what carries no value but the intent — deactivate, lock, the password mail — as one POST route over `Enum\AccountAction`, so an action nobody declared is a 404 from the router. Its CSRF token id is the application's own form one, `submit`, so the buttons ask for no second `stateless_token_ids` entry.

### Session lifetime

src/EventSubscriber/SessionIdleSubscriber.php runs between the firewall (8) and the account gates (4), at priority 6: a session past `session.idle_lifetime` is signed out before a gate can hold it on a page. It signs out through `Security::logout()` rather than by emptying the token storage, which is what clears the remember-me cookie — src/EventSubscriber/TwoFactorPendingSubscriber.php can do the shorter thing, since a password waiting for its code has no such cookie yet.

The stamp it keeps is refreshed by pages only. A request whose path opens on `/_` is reached by a program — a component rendered on demand, a live subscription — and is held to the delay without pushing it back.

### Tests

The fixture kernel (tests/Fixtures/App/AppKernel.php) boots the security, forms, loader and scheb bundles, and symfony-user-ds (a dev dependency) so the walks reach real pages, on SQLite in memory, with the stateless CSRF of the Flex recipe. Integration tests walk the flows over HTTP with `disableReboot()`; three traps:

- The test client resets services between requests: the entity manager forgets the entities a test holds, and an array cache is emptied. Reload entities before changing them, and clear `cache.rate_limiter` / `cache.app` in `setUp()` rather than using an array cache.
- Loader pages cannot be rendered twice by the same fixture kernel: controllers redirect with a query flag rather than render an error page, and tests assert the redirect.
- Nothing in the suite may reach the network. The leaked-password check is the one feature that would: tests/Fixtures/App/config/leaked_password.yaml gives the real validator a `MockHttpClient` answering as `api.pwnedpasswords.com` does, so the constraint is exercised offline.

A delay is never waited for: a test puts the session stamp back instead, as tests/Integration/IdleSessionTest.php and tests/Integration/SecondFactorPendingTest.php do.

Tests run from the design-system containers: `docker exec design_system_local_symfony sh -lc 'cd /var/www/vendor-dev/wexample/symfony-user && php vendor/bin/phpunit'`.
