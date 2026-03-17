<?php

declare(strict_types=1);

namespace KDuma\SimpleDAL\Integrity\PhpSecLib;

use phpseclib3\Crypt\RSA\PrivateKey;
use phpseclib3\Crypt\RSA\PublicKey;

class RsaSigningAlgorithm extends GenericPhpSecLibSigningAlgorithm
{
    public function __construct(string $id, PublicKey|PrivateKey $key)
    {
        parent::__construct($id, $key, 2);
    }
}
