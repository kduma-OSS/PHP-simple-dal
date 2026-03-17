<?php

declare(strict_types=1);

namespace KDuma\SimpleDAL\Encryption\Sodium;

use KDuma\SimpleDAL\Encryption\Contracts\EncryptionAlgorithmInterface;
use KDuma\SimpleDAL\Encryption\Contracts\Exception\DecryptionException;

class SecretBoxAlgorithm implements EncryptionAlgorithmInterface
{
    public const int ALGORITHM = 1;

    public int $algorithm {
        get => self::ALGORITHM;
    }

    public function __construct(
        public readonly string $id,
        private readonly string $key,
    ) {
        if (strlen($key) !== SODIUM_CRYPTO_SECRETBOX_KEYBYTES) {
            throw new \InvalidArgumentException(
                sprintf('Symmetric key must be %d bytes, got %d.', SODIUM_CRYPTO_SECRETBOX_KEYBYTES, strlen($key)),
            );
        }
    }

    public function encrypt(string $plaintext): string
    {
        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $ciphertext = sodium_crypto_secretbox($plaintext, $nonce, $this->key);

        return $nonce.$ciphertext;
    }

    public function decrypt(string $payload): string
    {
        if (strlen($payload) < SODIUM_CRYPTO_SECRETBOX_NONCEBYTES + SODIUM_CRYPTO_SECRETBOX_MACBYTES) { // @codeCoverageIgnoreStart
            throw new DecryptionException('Encrypted payload is too short for symmetric decryption.');
        } // @codeCoverageIgnoreEnd

        $nonce = substr($payload, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $ciphertext = substr($payload, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);

        $plaintext = sodium_crypto_secretbox_open($ciphertext, $nonce, $this->key);

        if ($plaintext === false) {
            throw new DecryptionException('Symmetric decryption failed — wrong key or corrupted data.');
        }

        return $plaintext;
    }
}
