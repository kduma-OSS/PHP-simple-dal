<?php

declare(strict_types=1);

namespace KDuma\SimpleDAL\Encryption\PhpSecLib;

use KDuma\SimpleDAL\Encryption\Contracts\EncryptionAlgorithmInterface;
use KDuma\SimpleDAL\Encryption\Contracts\Exception\DecryptionException;
use phpseclib3\Crypt\AES;
use phpseclib3\Crypt\RSA\PrivateKey;
use phpseclib3\Crypt\RSA\PublicKey;

class RsaAlgorithm implements EncryptionAlgorithmInterface
{
    public const int ALGORITHM = 4;

    private const int AES_KEY_LENGTH = 32; // AES-256

    private const int IV_LENGTH = 16;

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
            $publicKey = $key->getPublicKey();
            assert($publicKey instanceof PublicKey);
            $this->publicKey = $publicKey;
        } else {
            $this->publicKey = $key;
            $this->privateKey = null;
        }
    }

    public function encrypt(string $plaintext): string
    {
        $aesKey = random_bytes(self::AES_KEY_LENGTH);
        $iv = random_bytes(self::IV_LENGTH);

        $cipher = new AES('ctr');
        $cipher->setKeyLength(self::AES_KEY_LENGTH * 8);
        $cipher->setKey($aesKey);
        $cipher->setIV($iv);
        $aesCiphertext = $cipher->encrypt($plaintext);

        $encryptedKey = $this->publicKey->encrypt($aesKey);

        if (! is_string($encryptedKey)) { // @codeCoverageIgnoreStart
            throw new DecryptionException('RSA encryption failed.');
        } // @codeCoverageIgnoreEnd

        return pack('n', strlen($encryptedKey)).$encryptedKey.$iv.$aesCiphertext;
    }

    public function decrypt(string $payload): string
    {
        if ($this->privateKey === null) {
            throw new DecryptionException("Cannot decrypt with key '{$this->id}' — no private key provided (encrypt-only mode).");
        }

        if (strlen($payload) < 2) {
            throw new DecryptionException('Encrypted payload is too short — missing key length header.');
        }

        $unpacked = unpack('n', substr($payload, 0, 2));

        if ($unpacked === false) { // @codeCoverageIgnoreStart
            throw new DecryptionException('Failed to unpack key length header.');
        } // @codeCoverageIgnoreEnd

        /** @var int $keyLen */
        $keyLen = $unpacked[1];
        $offset = 2;

        if (strlen($payload) < $offset + $keyLen + self::IV_LENGTH) {
            throw new DecryptionException('Encrypted payload is truncated.');
        }

        $encryptedKey = substr($payload, $offset, $keyLen);
        $offset += $keyLen;

        try {
            $aesKey = $this->privateKey->decrypt($encryptedKey);
        } catch (\RuntimeException|\OutOfRangeException $e) {
            throw new DecryptionException('RSA decryption failed — wrong key or corrupted data.', 0, $e);
        }

        if (! is_string($aesKey)) { // @codeCoverageIgnoreStart
            throw new DecryptionException('RSA decryption failed — wrong key or corrupted data.');
        } // @codeCoverageIgnoreEnd

        $iv = substr($payload, $offset, self::IV_LENGTH);
        $offset += self::IV_LENGTH;

        $aesCiphertext = substr($payload, $offset);

        $cipher = new AES('ctr');
        $cipher->setKeyLength(self::AES_KEY_LENGTH * 8);
        $cipher->setKey($aesKey);
        $cipher->setIV($iv);

        return $cipher->decrypt($aesCiphertext);
    }
}
