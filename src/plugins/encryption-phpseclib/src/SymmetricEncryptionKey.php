<?php

declare(strict_types=1);

namespace KDuma\SimpleDAL\Encryption\PhpSecLib;

use KDuma\SimpleDAL\Encryption\Contracts\EncryptionKeyInterface;
use KDuma\SimpleDAL\Encryption\Contracts\Exception\DecryptionException;
use phpseclib3\Crypt\Common\SymmetricKey;

class SymmetricEncryptionKey implements EncryptionKeyInterface
{
    public const int ALGORITHM = 3;

    public int $algorithm {
        get => self::ALGORITHM;
    }

    public function __construct(
        public readonly string $id,
        private readonly SymmetricKey $cipher,
    ) {}

    public function encrypt(string $plaintext): string
    {
        $iv = random_bytes($this->cipher->getBlockLength() >> 3);
        $this->cipher->setIV($iv);

        return $iv.$this->cipher->encrypt($plaintext);
    }

    public function decrypt(string $payload): string
    {
        $ivLength = $this->cipher->getBlockLength() >> 3;

        if (strlen($payload) < $ivLength) {
            throw new DecryptionException('Encrypted payload is too short — missing IV.');
        }

        $iv = substr($payload, 0, $ivLength);
        $ciphertext = substr($payload, $ivLength);

        $this->cipher->setIV($iv);

        return $this->cipher->decrypt($ciphertext);
    }
}
