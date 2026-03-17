<?php

declare(strict_types=1);

namespace KDuma\SimpleDAL\Integrity\Hash\Hasher;

class Sha1HashingAlgorithm extends GenericPhpHashingAlgorithm
{
    public function __construct()
    {
        parent::__construct('sha1', 130);
    }
}
