<?php

namespace Wexample\SymfonyUser\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Encrypts the authenticator secrets with a key derived from `kernel.secret`.
 * Changing that secret makes every stored one unreadable: the users then set
 * their app up again.
 */
class TotpSecretCipherService
{
    private readonly string $key;

    public function __construct(
        #[Autowire(param: 'kernel.secret')]
        string $secret
    ) {
        $this->key = sodium_crypto_generichash('wexample_user_totp' . $secret, '', SODIUM_CRYPTO_SECRETBOX_KEYBYTES);
    }

    public function encrypt(string $plain): string
    {
        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);

        return base64_encode($nonce . sodium_crypto_secretbox($plain, $nonce, $this->key));
    }

    public function decrypt(string $encrypted): ?string
    {
        $raw = base64_decode($encrypted, true);

        if ($raw === false || strlen($raw) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) {
            return null;
        }

        $plain = sodium_crypto_secretbox_open(
            substr($raw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES),
            substr($raw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES),
            $this->key
        );

        return $plain === false ? null : $plain;
    }
}
