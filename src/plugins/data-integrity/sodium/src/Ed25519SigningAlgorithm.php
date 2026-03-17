<?php

declare(strict_types=1);

namespace KDuma\SimpleDAL\DataIntegrity\Sodium;

use KDuma\SimpleDAL\DataIntegrity\Contracts\SigningAlgorithmInterface;

class Ed25519SigningAlgorithm implements SigningAlgorithmInterface
{
    public const int ALGORITHM = 1;

    public int $algorithm {
        get => self::ALGORITHM;
    }

    /**
     * @param  non-empty-string|null  $secretKey
     * @param  non-empty-string  $publicKey
     */
    public function __construct(
        public readonly string $id,
        private readonly ?string $secretKey,
        private readonly string $publicKey,
    ) {
        if (strlen($publicKey) !== SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES) {
            throw new \InvalidArgumentException(
                sprintf('Public key must be %d bytes, got %d.', SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES, strlen($publicKey)),
            );
        }

        if ($secretKey !== null && strlen($secretKey) !== SODIUM_CRYPTO_SIGN_SECRETKEYBYTES) {
            throw new \InvalidArgumentException(
                sprintf('Secret key must be %d bytes, got %d.', SODIUM_CRYPTO_SIGN_SECRETKEYBYTES, strlen($secretKey)),
            );
        }
    }

    /**
     * @param  non-empty-string  $publicKey
     */
    public static function verifyOnly(string $id, string $publicKey): self
    {
        return new self($id, null, $publicKey);
    }

    public function sign(string $message): string
    {
        if ($this->secretKey === null) {
            throw new \RuntimeException("Cannot sign with key '{$this->id}' — no secret key provided (verify-only mode).");
        }

        return sodium_crypto_sign_detached($message, $this->secretKey);
    }

    public function verify(string $message, string $signature): bool
    {
        if ($signature === '') {
            return false;
        }

        return sodium_crypto_sign_verify_detached($signature, $message, $this->publicKey);
    }
}
