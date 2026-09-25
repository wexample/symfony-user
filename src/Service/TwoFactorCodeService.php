<?php

namespace Wexample\SymfonyUser\Service;

use DateTimeImmutable;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\CacheStorage;
use Wexample\SymfonyUser\Entity\AbstractUser;
use Wexample\SymfonyUser\Enum\SecurityMessageType;
use Wexample\SymfonyUser\Enum\TwoFactorCodeFailure;
use Wexample\SymfonyUser\Interface\SecurityMessageSenderInterface;

/**
 * The codes of the email second factor. A code lives in the session of the
 * login waiting for it, hashed, for CODE_LIFETIME and MAX_ATTEMPTS tries. The
 * failures of an account are also counted across sessions: restarting the
 * login does not buy more guesses.
 */
class TwoFactorCodeService
{
    public const int CODE_LIFETIME = 600;
    public const int MAX_ATTEMPTS = 5;
    public const int RESEND_DELAY = 60;
    public const int MAX_FAILURES_PER_HOUR = 20;

    private const string SESSION_KEY = 'wexample_user_two_factor_code';
    private const string SESSION_FAILURE_KEY = 'wexample_user_two_factor_failure';

    private readonly RateLimiterFactory $failureLimiter;

    public function __construct(
        private readonly SecurityMessageSenderInterface $sender,
        private readonly RequestStack $requestStack,
        #[Autowire(service: 'cache.app')]
        CacheItemPoolInterface $cache,
        #[Autowire(param: 'kernel.secret')]
        private readonly string $secret,
    ) {
        $this->failureLimiter = new RateLimiterFactory(
            [
                'id' => 'wexample_user_two_factor',
                'policy' => 'sliding_window',
                'limit' => self::MAX_FAILURES_PER_HOUR,
                'interval' => '1 hour',
            ],
            new CacheStorage($cache)
        );
    }

    public function send(AbstractUser $user): void
    {
        $code = str_pad((string) random_int(0, 999_999), 6, '0', STR_PAD_LEFT);
        $expiresAt = new DateTimeImmutable('+' . self::CODE_LIFETIME . ' seconds');

        $this->requestStack->getSession()->set(self::SESSION_KEY, [
            'user' => $user->getUserIdentifier(),
            'hash' => $this->hash($code),
            'expires' => $expiresAt->getTimestamp(),
            'attempts' => 0,
            'sent' => time(),
        ]);

        $this->sender->send($user, SecurityMessageType::TWO_FACTOR_CODE, $code, $expiresAt);
    }

    public function canResend(): bool
    {
        $state = $this->getState();

        return ! $state || $state['sent'] + self::RESEND_DELAY <= time();
    }

    public function validate(AbstractUser $user, string $code): bool
    {
        $state = $this->getState();

        if (! $state || $state['user'] !== $user->getUserIdentifier() || $state['expires'] < time()) {
            return $this->fail(TwoFactorCodeFailure::EXPIRED);
        }

        $limiter = $this->failureLimiter->create($user->getUserIdentifier());

        if ($state['attempts'] >= self::MAX_ATTEMPTS || ! $limiter->consume(0)->isAccepted()) {
            return $this->fail(TwoFactorCodeFailure::TOO_MANY_ATTEMPTS);
        }

        if (! hash_equals($state['hash'], $this->hash(preg_replace('/\D/', '', $code)))) {
            ++$state['attempts'];
            $this->requestStack->getSession()->set(self::SESSION_KEY, $state);
            $limiter->consume();

            return $this->fail(TwoFactorCodeFailure::INVALID);
        }

        $this->requestStack->getSession()->remove(self::SESSION_KEY);
        $this->requestStack->getSession()->remove(self::SESSION_FAILURE_KEY);
        $limiter->reset();

        return true;
    }

    /**
     * Why the last code was refused, for the failure handler.
     */
    public function getLastFailure(): TwoFactorCodeFailure
    {
        return TwoFactorCodeFailure::tryFrom(
            (string) $this->requestStack->getSession()->get(self::SESSION_FAILURE_KEY)
        ) ?? TwoFactorCodeFailure::INVALID;
    }

    public function clear(): void
    {
        $this->requestStack->getSession()->remove(self::SESSION_KEY);
    }

    private function fail(TwoFactorCodeFailure $failure): bool
    {
        $this->requestStack->getSession()->set(self::SESSION_FAILURE_KEY, $failure->value);

        return false;
    }

    /**
     * @return array{user: string, hash: string, expires: int, attempts: int, sent: int}|null
     */
    private function getState(): ?array
    {
        return $this->requestStack->getSession()->get(self::SESSION_KEY);
    }

    private function hash(string $code): string
    {
        return hash_hmac('sha256', $code, $this->secret);
    }
}
