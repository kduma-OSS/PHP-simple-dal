<?php

declare(strict_types=1);

namespace KDuma\SimpleDAL\DataIntegrity\Sodium;

use KDuma\SimpleDAL\DataIntegrity\Contracts\HashingAlgorithmInterface;

class Blake2bHashingAlgorithm implements HashingAlgorithmInterface
{
    public const int ALGORITHM = 1;

    public int $algorithm {
        get => self::ALGORITHM;
    }

    public function hash(string $data): string
    {
        return sodium_crypto_generichash($data);
    }
}
