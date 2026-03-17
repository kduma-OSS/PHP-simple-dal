<?php

declare(strict_types=1);

namespace KDuma\SimpleDAL\DataIntegrity\Hash\Signer;

class HmacSha256SigningAlgorithm extends GenericHmacSigningAlgorithm
{
    public function __construct(string $id, string $secret)
    {
        parent::__construct($id, $secret, 'sha256', 129);
    }
}
