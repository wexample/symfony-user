<?php

namespace Wexample\SymfonyUser\Service;

use DateTimeImmutable;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\Signature\Exception\ExpiredSignatureException;
use Symfony\Component\Security\Core\Signature\Exception\InvalidSignatureException;
use Symfony\Component\Security\Core\Signature\SignatureHasher;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use Wexample\SymfonyUser\Controller\Pages\PasswordController;
use Wexample\SymfonyUser\Entity\AbstractUser;
use Wexample\SymfonyUser\Enum\PasswordResetMode;
use Wexample\SymfonyUser\Enum\SecurityMessageType;
use Wexample\SymfonyUser\Interface\SecurityMessageSenderInterface;

/**
 * Lets a user who forgot their password choose a new one.
 *
 * Whatever the mode, it ends on a proof kept in session for a quarter of an
 * hour: this user may set a password without typing the current one. A
 * signed reset link grants it, or a sign-in through a magic link. Nothing is
 * stored in database: the reset link is signed with the password hash, so
 * the new password kills it.
 */
class PasswordResetService
{
    public const int LINK_LIFETIME = 3600;
    public const int PROOF_LIFETIME = 900;

    private const string SESSION_PROOF = 'wexample_user_password_reset';

    private readonly SignatureHasher $signatureHasher;

    public function __construct(
        private readonly SecurityMessageSenderInterface $sender,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly RequestStack $requestStack,
        private readonly UserProviderInterface $userProvider,
        private readonly MagicLinkService $magicLinkService,
        #[Autowire(param: 'wexample_symfony_user.password_reset')]
        private readonly string $mode,
        #[Autowire(param: 'kernel.secret')]
        string $secret,
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
                $this->urlGenerator->generate(PasswordController::ROUTE_NEW)
            );
        }

        $expires = time() + self::LINK_LIFETIME;

        $this->sender->send(
            $user,
            SecurityMessageType::PASSWORD_RESET,
            $this->urlGenerator->generate(
                PasswordController::ROUTE_RESET,
                [
                    'user' => $user->getUserIdentifier(),
                    'expires' => $expires,
                    'hash' => $this->signatureHasher->computeSignatureHash($user, $expires),
                ],
                UrlGeneratorInterface::ABSOLUTE_URL
            ),
            new DateTimeImmutable('@' . $expires)
        );

        return true;
    }

    /**
     * Checks a reset link and grants the proof when it is valid.
     */
    public function consumeResetLink(string $identifier, int $expires, string $hash): bool
    {
        $user = $this->loadUser($identifier);

        if (! $user || ! $user->isEnabled() || $user->isLocked()) {
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
     * The user the session may set a password for, without the current one.
     */
    public function getProofUser(): ?AbstractUser
    {
        $proof = $this->requestStack->getSession()->get(self::SESSION_PROOF);

        if (! is_array($proof) || ($proof['until'] ?? 0) < time()) {
            return null;
        }

        return $this->loadUser((string) $proof['user']);
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
