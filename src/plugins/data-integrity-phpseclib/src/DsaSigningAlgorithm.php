<?php

declare(strict_types=1);

namespace KDuma\SimpleDAL\DataIntegrity\PhpSecLib;

use phpseclib3\Crypt\DSA\PrivateKey;
use phpseclib3\Crypt\DSA\PublicKey;

class DsaSigningAlgorithm extends GenericPhpSecLibSigningAlgorithm
{
    public function __construct(string $id, PublicKey|PrivateKey $key)
    {
        parent::__construct($id, $key, 3);
    }
}
