<?php

declare(strict_types=1);

namespace KDuma\SimpleDAL\Integrity\Hash\Hasher;

class Crc32HashingAlgorithm extends GenericPhpHashingAlgorithm
{
    public function __construct()
    {
        parent::__construct('crc32', 128);
    }
}
