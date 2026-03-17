<?php

declare(strict_types=1);

namespace KDuma\SimpleDAL\DataIntegrity\Contracts;

interface SigningAlgorithmInterface
{
    /**
     * Key identifier (user-provided string identifying this specific key instance).
     */
    public string $id { get; }

    /**
     * Algorithm identifier byte stored in the integrity payload header.
     * Each implementation defines its own unique constant.
     */
    public int $algorithm { get; }

    /**
     * Sign a message and return the raw signature bytes.
     */
    public function sign(string $message): string;

    /**
     * Verify a signature against a message.
     */
    public function verify(string $message, string $signature): bool;
}
