<?php

declare(strict_types=1);

namespace KDuma\SimpleDAL\DataIntegrity\Contracts;

interface HashingAlgorithmInterface
{
    /**
     * Algorithm identifier byte stored in the integrity payload header.
     * Each implementation defines its own unique constant.
     */
    public int $algorithm { get; }

    /**
     * Compute a hash of the given data.
     *
     * @return string Raw hash bytes
     */
    public function hash(string $data): string;
}
