<?php

declare(strict_types=1);

namespace KDuma\SimpleDAL\Encryption\Contracts;

interface EncryptionKeyInterface
{
    public string $id { get; }

    /**
     * Algorithm identifier byte stored in the encrypted payload header.
     * Each implementation defines its own unique constant.
     */
    public int $algorithm { get; }

    /**
     * Encrypt plaintext. Returns the raw encrypted payload (e.g. nonce + ciphertext).
     */
    public function encrypt(string $plaintext): string;

    /**
     * Decrypt a raw encrypted payload previously produced by encrypt().
     *
     * @throws DecryptionException
     */
    public function decrypt(string $payload): string;
}
