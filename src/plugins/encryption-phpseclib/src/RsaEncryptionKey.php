<?php

declare(strict_types=1);

namespace KDuma\SimpleDAL\Encryption\PhpSecLib;

use KDuma\SimpleDAL\Encryption\Contracts\EncryptionKeyInterface;
use KDuma\SimpleDAL\Encryption\Contracts\Exception\DecryptionException;
use phpseclib3\Crypt\RSA\PrivateKey;
use phpseclib3\Crypt\RSA\PublicKey;

class RsaEncryptionKey implements EncryptionKeyInterface
{
    public const int ALGORITHM = 4;

    public int $algorithm {
        get => self::ALGORITHM;
    }

    /**
     * @param  PublicKey  $publicKey  Pre-configured RSA public key (use withPadding/withHash before passing)
     * @param  PrivateKey|null  $privateKey  Pre-configured RSA private key (null = encrypt-only)
     */
    public function __construct(
        public readonly string $id,
        private readonly PublicKey $publicKey,
        private readonly ?PrivateKey $privateKey = null,
    ) {}

    public function encrypt(string $plaintext): string
    {
        $ciphertext = $this->publicKey->encrypt($plaintext);

        if ($ciphertext === false) {
            throw new DecryptionException('RSA encryption failed.');
        }

        return $ciphertext;
    }

    public function decrypt(string $payload): string
    {
        if ($this->privateKey === null) {
            throw new DecryptionException("Cannot decrypt with key '{$this->id}' — no private key provided (encrypt-only mode).");
        }

        try {
            $plaintext = $this->privateKey->decrypt($payload);
        } catch (\RuntimeException $e) {
            throw new DecryptionException('RSA decryption failed — wrong key or corrupted data.', 0, $e);
        }

        if ($plaintext === false) {
            throw new DecryptionException('RSA decryption failed — wrong key or corrupted data.');
        }

        return $plaintext;
    }
}
