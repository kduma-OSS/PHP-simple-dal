<?php

declare(strict_types=1);

namespace KDuma\SimpleDAL\DataIntegrity\Hash\Hasher;

class Md5HashingAlgorithm extends GenericPhpHashingAlgorithm
{
    public function __construct()
    {
        parent::__construct('md5', 129);
    }
}
