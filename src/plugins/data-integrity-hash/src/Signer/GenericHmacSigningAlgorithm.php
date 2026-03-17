<?php

declare(strict_types=1);

namespace KDuma\SimpleDAL\DataIntegrity\Hash\Signer;

use KDuma\SimpleDAL\DataIntegrity\Contracts\SigningAlgorithmInterface;

class GenericHmacSigningAlgorithm implements SigningAlgorithmInterface
{
    public int $algorithm {
        get => $this->algorithmId;
    }

    /**
     * @param  string  $id  Key identifier
     * @param  string  $secret  Raw secret key bytes
     * @param  string  $algo  PHP hash algorithm for HMAC (e.g., 'sha256')
     * @param  int  $algorithmId  Unique identifier for this signing algorithm.
     */
    public function __construct(
        public readonly string $id,
        private readonly string $secret,
        private readonly string $algo = 'sha256',
        private readonly int $algorithmId = 129,
    ) {
        if (! in_array($algo, hash_hmac_algos(), true)) {
            throw new \InvalidArgumentException("Unknown HMAC algorithm: {$algo}");
        }
    }

    public function sign(string $message): string
    {
        return hash_hmac($this->algo, $message, $this->secret, binary: true);
    }

    public function verify(string $message, string $signature): bool
    {
        return hash_equals($this->sign($message), $signature);
    }
}
