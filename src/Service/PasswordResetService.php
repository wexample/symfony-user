<?php

namespace Wexample\SymfonyUser\Service;

use DateTimeImmutable;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\SwitchUserToken;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\Signature\Exception\ExpiredSignatureException;
use Symfony\Component\Security\Core\Signature\Exception\InvalidSignatureException;
use Symfony\Component\Security\Core\Signature\SignatureHasher;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use Wexample\SymfonyUser\Entity\AbstractUser;
use Wexample\SymfonyUser\Enum\PasswordResetMode;
use Wexample\SymfonyUser\Enum\SecurityMessageType;
use Wexample\SymfonyUser\Routing\UserRoute;

/**
 * Lets a user who forgot their password choose a new one, and the holder of
 * an account an administrator created choose its first one.
 *
 * Whatever the mode, it ends on a proof kept in session for a quarter of an
 * hour: this user may set a password without typing the current one. A
 * signed reset link grants it, or a sign-in through a magic link. Nothing is
 * stored in database: the reset link is signed with the password hash, so
 * the new password kills it. An activation link is the same signed link,
 * living longer, for an account with no password yet.
 */
class PasswordResetService
{
    public const int LINK_LIFETIME = 3600;
    public const int PROOF_LIFETIME = 900;

    private const string SESSION_PROOF = 'wexample_user_password_reset';

    private readonly SignatureHasher $signatureHasher;

    public function __construct(
        private readonly SecurityMessageService $securityMessageService,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly RequestStack $requestStack,
        private readonly UserProviderInterface $userProvider,
        private readonly MagicLinkService $magicLinkService,
        private readonly Security $security,
        #[Autowire(param: 'wexample_symfony_user.password_reset')]
        private readonly string $mode,
        #[Autowire(param: 'kernel.secret')]
        string $secret,
        #[Autowire(param: 'wexample_symfony_user.activation.link_lifetime')]
        private readonly int $activationLifetime = 604800,
    ) {
        $this->signatureHasher = new SignatureHasher(
            PropertyAccess::createPropertyAccessor(),
            ['password', 'email'],
            $secret
        );
    }

    public function getMode(): PasswordResetMode
    {
        return PasswordResetMode::from($this->mode);
    }

    /**
     * Only an account allowed to sign in gets a link.
     */
    public function sendResetLink(AbstractUser $user): bool
    {
        if (! $user->isEnabled() || $user->isLocked()) {
            return false;
        }

        if ($this->getMode() === PasswordResetMode::MAGIC_LINK) {
            // The link signs in, then lands where the new password is chosen.
            return $this->magicLinkService->sendLink(
                $user,
                $this->urlGenerator->generate(UserRoute::PASSWORD_NEW)
            );
        }

        $this->securityMessageService->queue($user, SecurityMessageType::PASSWORD_RESET);

        return true;
    }

    /**
     * Only an enabled account with no password yet gets one.
     */
    public function sendActivationLink(AbstractUser $user): bool
    {
        if (! $user->isEnabled() || $user->isLocked() || $user->getPassword() !== null) {
            return false;
        }

        $this->securityMessageService->queue($user, SecurityMessageType::ACCOUNT_ACTIVATION);

        return true;
    }

    /**
     * @return array{0: string, 1: DateTimeImmutable}
     */
    public function createResetLink(AbstractUser $user): array
    {
        return $this->createSignedLink($user, UserRoute::PASSWORD_RESET, self::LINK_LIFETIME);
    }

    /**
     * @return array{0: string, 1: DateTimeImmutable}
     */
    public function createActivationLink(AbstractUser $user, ?int $lifetime = null): array
    {
        return $this->createSignedLink($user, UserRoute::PASSWORD_ACTIVATE, $lifetime ?? $this->activationLifetime);
    }

    /**
     * Checks a reset link and grants the proof when it is valid.
     */
    public function consumeResetLink(string $identifier, int $expires, string $hash): bool
    {
        return $this->consumeSignedLink($identifier, $expires, $hash, false);
    }

    /**
     * Checks an activation link: only an account still waiting for its first
     * password takes it.
     */
    public function consumeActivationLink(string $identifier, int $expires, string $hash): bool
    {
        return $this->consumeSignedLink($identifier, $expires, $hash, true);
    }

    /**
     * @return array{0: string, 1: DateTimeImmutable}
     */
    private function createSignedLink(AbstractUser $user, string $route, int $lifetime): array
    {
        $expires = time() + $lifetime;

        return [
            $this->urlGenerator->generate(
                $route,
                [
                    'user' => $user->getUserIdentifier(),
                    'expires' => $expires,
                    'hash' => $this->signatureHasher->computeSignatureHash($user, $expires),
                ],
                UrlGeneratorInterface::ABSOLUTE_URL
            ),
            new DateTimeImmutable('@' . $expires),
        ];
    }

    private function consumeSignedLink(string $identifier, int $expires, string $hash, bool $activation): bool
    {
        $user = $this->loadUser($identifier);

        if (! $user || ! $user->isEnabled() || $user->isLocked()
            || ($activation && $user->getPassword() !== null)) {
            return false;
        }

        try {
            $this->signatureHasher->acceptSignatureHash($identifier, $expires, $hash);
            $this->signatureHasher->verifySignatureHash($user, $expires, $hash);
        } catch (ExpiredSignatureException|InvalidSignatureException) {
            return false;
        }

        $this->grantProof($user);

        return true;
    }

    public function grantProof(AbstractUser $user): void
    {
        $this->requestStack->getSession()->set(self::SESSION_PROOF, [
            'user' => $user->getUserIdentifier(),
            'until' => time() + self::PROOF_LIFETIME,
        ]);
    }

    /**
     * The user the session may set a password for, without the current one:
     * the holder of a live proof, or — with no proof — a signed-in user owing
     * a forced change. They signed in a moment ago with the password an
     * administrator chose for them; asking for it again proves nothing, and
     * PasswordChangeGate lets them nowhere else.
     *
     * Never the account an administrator is impersonating: the impersonator
     * would be choosing its password.
     */
    public function getProofUser(): ?AbstractUser
    {
        if ($this->hasProof()) {
            $proof = $this->requestStack->getSession()->get(self::SESSION_PROOF);

            return $this->loadUser((string) $proof['user']);
        }

        $token = $this->security->getToken();
        $user = $token instanceof SwitchUserToken ? null : $token?->getUser();

        return $user instanceof AbstractUser && $user->isPasswordChangeRequired() ? $user : null;
    }

    /**
     * Whether the session carries a live proof — a reset link followed, a
     * magic link signed in with — as opposed to a forced change.
     */
    public function hasProof(): bool
    {
        $proof = $this->requestStack->getSession()->get(self::SESSION_PROOF);

        return is_array($proof) && ($proof['until'] ?? 0) >= time();
    }

    public function clearProof(): void
    {
        $this->requestStack->getSession()->remove(self::SESSION_PROOF);
    }

    private function loadUser(string $identifier): ?AbstractUser
    {
        try {
            $user = $this->userProvider->loadUserByIdentifier($identifier);
        } catch (UserNotFoundException) {
            return null;
        }

        return $user instanceof AbstractUser ? $user : null;
    }
}
