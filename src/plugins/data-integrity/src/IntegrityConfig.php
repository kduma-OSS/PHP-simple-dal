<?php

declare(strict_types=1);

namespace KDuma\SimpleDAL\DataIntegrity;

use KDuma\SimpleDAL\DataIntegrity\Contracts\HashingAlgorithmInterface;
use KDuma\SimpleDAL\DataIntegrity\Contracts\SigningAlgorithmInterface;

class IntegrityConfig
{
    public function __construct(
        public readonly ?HashingAlgorithmInterface $hasher = null,
        public readonly ?SigningAlgorithmInterface $signer = null,
        public readonly FailureMode $onChecksumFailure = FailureMode::Throw,
        public readonly FailureMode $onSignatureFailure = FailureMode::Throw,
    ) {}
}
