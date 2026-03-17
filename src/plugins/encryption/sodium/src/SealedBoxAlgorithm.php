<?php

declare(strict_types=1);

namespace KDuma\SimpleDAL\Encryption\Sodium;

use KDuma\SimpleDAL\Encryption\Contracts\EncryptionAlgorithmInterface;
use KDuma\SimpleDAL\Encryption\Contracts\Exception\DecryptionException;

class SealedBoxAlgorithm implements EncryptionAlgorithmInterface
{
    public const int ALGORITHM = 2;

    public int $algorithm {
        get => self::ALGORITHM;
    }

    /**
     * @param  string  $publicKey  32-byte X25519 public key
     * @param  string|null  $secretKey  32-byte X25519 secret key (null = encrypt-only)
     */
    public function __construct(
        public readonly string $id,
        private readonly string $publicKey,
        private readonly ?string $secretKey = null,
    ) {
        if (strlen($publicKey) !== SODIUM_CRYPTO_BOX_PUBLICKEYBYTES) {
            throw new \InvalidArgumentException(
                sprintf('Public key must be %d bytes, got %d.', SODIUM_CRYPTO_BOX_PUBLICKEYBYTES, strlen($publicKey)),
            );
        }

        if ($secretKey !== null && strlen($secretKey) !== SODIUM_CRYPTO_BOX_SECRETKEYBYTES) {
            throw new \InvalidArgumentException(
                sprintf('Secret key must be %d bytes, got %d.', SODIUM_CRYPTO_BOX_SECRETKEYBYTES, strlen($secretKey)),
            );
        }
    }

    public function encrypt(string $plaintext): string
    {
        return sodium_crypto_box_seal($plaintext, $this->publicKey);
    }

    public function decrypt(string $payload): string
    {
        if ($this->secretKey === null) {
            throw new DecryptionException("Cannot decrypt with key '{$this->id}' — no secret key provided (encrypt-only mode).");
        }

        $keypair = sodium_crypto_box_keypair_from_secretkey_and_publickey($this->secretKey, $this->publicKey);

        $plaintext = sodium_crypto_box_seal_open($payload, $keypair);

        if ($plaintext === false) {
            throw new DecryptionException('Asymmetric decryption failed — wrong key or corrupted data.');
        }

        return $plaintext;
    }
}
