<?php

declare(strict_types=1);

namespace KDuma\SimpleDAL\DataIntegrity\Hash\Hasher;

class Sha3_256HashingAlgorithm extends GenericPhpHashingAlgorithm
{
    public function __construct()
    {
        parent::__construct('sha3-256', 133);
    }
}
