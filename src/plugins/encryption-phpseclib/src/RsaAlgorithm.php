<?php

declare(strict_types=1);

namespace KDuma\SimpleDAL\Encryption\PhpSecLib;

use KDuma\SimpleDAL\Encryption\Contracts\EncryptionAlgorithmInterface;
use KDuma\SimpleDAL\Encryption\Contracts\Exception\DecryptionException;
use phpseclib3\Crypt\RSA\PrivateKey;
use phpseclib3\Crypt\RSA\PublicKey;

class RsaAlgorithm implements EncryptionAlgorithmInterface
{
    public const int ALGORITHM = 4;

    public int $algorithm {
        get => self::ALGORITHM;
    }

    private readonly PublicKey $publicKey;

    private readonly ?PrivateKey $privateKey;

    /**
     * @param  PublicKey|PrivateKey  $key  Pass a PrivateKey for encrypt+decrypt, or a PublicKey for encrypt-only.
     *                                     Configure padding/hash before passing (e.g. withPadding, withHash).
     */
    public function __construct(
        public readonly string $id,
        PublicKey|PrivateKey $key,
    ) {
        if ($key instanceof PrivateKey) {
            $this->privateKey = $key;
            $this->publicKey = $key->getPublicKey();
        } else {
            $this->publicKey = $key;
            $this->privateKey = null;
        }
    }

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
