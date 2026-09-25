<?php

namespace Wexample\SymfonyUser\Service;

use Doctrine\ORM\EntityManagerInterface;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Writer\SvgWriter;
use LogicException;
use Scheb\TwoFactorBundle\Security\TwoFactor\Provider\Totp\TotpAuthenticatorInterface;
use Scheb\TwoFactorBundle\Security\TwoFactor\Provider\Totp\TotpFactory;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RequestStack;
use Wexample\SymfonyUser\Entity\AbstractUser;

/**
 * Sets an authenticator app up for a user. The secret waits in session until
 * the user types a first code from their app, which proves the app has it.
 */
class TotpService
{
    public const int BACKUP_CODES_COUNT = 10;

    private const string SESSION_PENDING = 'wexample_user_totp_pending';
    private const string SESSION_BACKUP_CODES = 'wexample_user_totp_backup_codes';

    public function __construct(
        private readonly TotpSecretCipherService $cipher,
        private readonly EntityManagerInterface $entityManager,
        private readonly RequestStack $requestStack,
        // Both absent when `scheb_two_factor.totp.enabled` is false.
        private readonly ?TotpAuthenticatorInterface $totpAuthenticator = null,
        #[Autowire(service: 'scheb_two_factor.security.totp_factory')]
        private readonly ?TotpFactory $totpFactory = null,
    ) {
    }

    /**
     * The secret being set up, created on first call.
     */
    public function getPendingSecret(): string
    {
        $session = $this->requestStack->getSession();

        if (! $secret = $session->get(self::SESSION_PENDING)) {
            $secret = $this->getAuthenticator()->generateSecret();
            $session->set(self::SESSION_PENDING, $secret);
        }

        return $secret;
    }

    /**
     * The QR code the app scans, as an SVG data URI.
     */
    public function getPendingQrCodeDataUri(AbstractUser $user): string
    {
        if (! $this->totpFactory) {
            throw new LogicException('Enable scheb_two_factor.totp to set authenticator apps up.');
        }

        $uri = $this->totpFactory
            ->createTotpForUser($this->withSecret($user, $this->getPendingSecret()))
            ->getProvisioningUri();

        return (new Builder(writer: new SvgWriter(), data: $uri, size: 240, margin: 8))
            ->build()
            ->getDataUri();
    }

    /**
     * Turns the pending secret on when $code comes from the app, and hands out
     * fresh backup codes.
     */
    public function confirm(AbstractUser $user, string $code): bool
    {
        $secret = $this->getPendingSecret();

        if (! $this->getAuthenticator()->checkCode($this->withSecret($user, $secret), $code)) {
            return false;
        }

        $user->setTotpSecret($secret, $this->cipher->encrypt($secret));
        $this->requestStack->getSession()->remove(self::SESSION_PENDING);
        $this->regenerateBackupCodes($user);

        return true;
    }

    public function disable(AbstractUser $user): void
    {
        $user->setTotpSecret(null, null)->setBackupCodes([]);
        $this->entityManager->flush();
    }

    /**
     * The previous codes stop working. The new ones are kept in session until
     * shown, once.
     *
     * @return list<string>
     */
    public function regenerateBackupCodes(AbstractUser $user): array
    {
        $codes = [];
        for ($index = 0; $index < self::BACKUP_CODES_COUNT; ++$index) {
            $codes[] = implode('-', str_split(bin2hex(random_bytes(5)), 5));
        }

        $user->setBackupCodes($codes);
        $this->entityManager->flush();
        $this->requestStack->getSession()->set(self::SESSION_BACKUP_CODES, $codes);

        return $codes;
    }

    /**
     * @return list<string>|null
     */
    public function pullNewBackupCodes(): ?array
    {
        $session = $this->requestStack->getSession();
        $codes = $session->get(self::SESSION_BACKUP_CODES);
        $session->remove(self::SESSION_BACKUP_CODES);

        return $codes;
    }

    private function getAuthenticator(): TotpAuthenticatorInterface
    {
        return $this->totpAuthenticator
            ?? throw new LogicException('Enable scheb_two_factor.totp to set authenticator apps up.');
    }

    /**
     * A copy of the user holding $secret, to use scheb's TOTP configuration
     * (issuer, window) before the secret is saved.
     */
    private function withSecret(AbstractUser $user, string $secret): AbstractUser
    {
        return (clone $user)->setTotpSecret($secret, null);
    }
}
