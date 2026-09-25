## Installation

### Bundles

```php
// config/bundles.php
Symfony\Bundle\SecurityBundle\SecurityBundle::class => ['all' => true],
Scheb\TwoFactorBundle\SchebTwoFactorBundle::class => ['all' => true],
Wexample\SymfonyUser\WexampleSymfonyUserBundle::class => ['all' => true],
```

The scheb recipe also adds `config/routes/scheb_2fa.yaml`: delete it, the code form is served by this package on `/login/2fa`.

### The user entity

```php
#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: '`user`')]
class User extends AbstractUser
{
    use UserWithNameTrait; // optional: first and last name, display name
}

class UserRepository extends AbstractUserRepository
{
    public static function getEntityClassName(): string
    {
        return User::class;
    }
}
```

`AbstractUser` is not a mapped superclass: its columns land in the application table. Generate a migration after extending it.

### Security

```yaml
# config/packages/security.yaml
security:
    providers:
        users:
            entity:
                class: App\Entity\User   # no `property`: email or username
    firewalls:
        main:
            lazy: true
            provider: users
            custom_authenticators:
                - Wexample\SymfonyUser\Security\Authenticator\LoginFormAuthenticator
            entry_point: Wexample\SymfonyUser\Security\Authenticator\LoginFormAuthenticator
            user_checker: Wexample\SymfonyUser\Security\UserChecker
            logout:
                path: user_security_logout
            login_throttling: ~
            remember_me:
                secret: '%kernel.secret%'
                signature_properties: [password]
            login_link:
                check_route: user_security_login_link
                signature_properties: [password, dateLastLogin]
                lifetime: 600
            two_factor:
                auth_form_path: user_security_two_factor
                prepare_on_login: true
                check_path: /_forms/submit/form-two_factor_code_form
                auth_code_parameter_name: two_factor_code_form[code]
                trusted_parameter_name: two_factor_code_form[trusted]
                success_handler: Wexample\SymfonyUser\Security\Handler\TwoFactorResponseHandler
                failure_handler: Wexample\SymfonyUser\Security\Handler\TwoFactorResponseHandler
                authentication_required_handler: Wexample\SymfonyUser\Security\Handler\TwoFactorResponseHandler
                enable_csrf: true
                csrf_parameter: two_factor_code_form[_token]
                csrf_token_id: submit   # the form CSRF token id of the app
    access_control:
        - { path: ^/login/2fa, roles: IS_AUTHENTICATED_2FA_IN_PROGRESS }
```

```yaml
# config/packages/scheb_2fa.yaml
scheb_two_factor:
    security_tokens:
        - Symfony\Component\Security\Http\Authenticator\Token\PostAuthenticationToken
    trusted_device:
        enabled: true
        lifetime: 5184000
    # Authenticator apps, and their backup codes: /account/authenticator
    totp:
        enabled: true
        issuer: 'My application'
        leeway: 1
    backup_codes:
        enabled: true
```

- `APP_SECRET` must be 32 bytes at least: the trusted device cookie is a JWT signed with it. The authenticator secrets are encrypted with a key derived from it too: changing it makes users set their app up again.
- While a login waits for its code, scheb only lets through the paths `access_control` opens to `PUBLIC_ACCESS` or `IS_AUTHENTICATED_2FA_IN_PROGRESS`.
- Without a second factor, drop the `two_factor` key; to ask it on every login, set `trusted_device.enabled: false`.

### Mails

Links and codes are sent with `symfony/mailer`. The sender comes from the mailer configuration:

```yaml
framework:
    mailer:
        headers:
            From: 'no-reply@example.com'
```

To deliver them another way, alias `Wexample\SymfonyUser\Interface\SecurityMessageSenderInterface` to your own service.

### Password reset

```yaml
# config/packages/wexample_symfony_user.yaml
wexample_symfony_user:
    password_reset: token   # or magic_link
```
