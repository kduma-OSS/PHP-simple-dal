<?php

declare(strict_types=1);

namespace KDuma\SimpleDAL\DataIntegrity\Hash\Hasher;

use KDuma\SimpleDAL\DataIntegrity\Contracts\HashingAlgorithmInterface;

class GenericPhpHashingAlgorithm implements HashingAlgorithmInterface
{
    public int $algorithm {
        get => $this->algorithmId;
    }

    /**
     * @param  string  $algo  PHP hash algorithm name (e.g., 'sha256', 'sha512', 'sha3-256')
     * @param  int  $algorithmId  Unique identifier for this algorithm in integrity headers.
     */
    public function __construct(
        private readonly string $algo,
        private readonly int $algorithmId,
        private readonly bool $binary = true,
    ) {
        if (! in_array($algo, hash_algos(), true)) {
            throw new \InvalidArgumentException("Unknown hash algorithm: {$algo}");
        }
    }

    public function hash(string $data): string
    {
        return hash($this->algo, $data, binary: $this->binary);
    }
}
