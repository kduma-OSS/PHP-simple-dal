<?php

declare(strict_types=1);

namespace KDuma\SimpleDAL\Integrity;

use KDuma\SimpleDAL\Integrity\Contracts\HashingAlgorithmInterface;
use KDuma\SimpleDAL\Integrity\Contracts\SigningAlgorithmInterface;

class IntegrityConfig
{
    public function __construct(
        public readonly ?HashingAlgorithmInterface $hasher = null,
        public readonly ?SigningAlgorithmInterface $signer = null,
        public readonly FailureMode $onChecksumFailure = FailureMode::Throw,
        public readonly FailureMode $onSignatureFailure = FailureMode::Throw,
        public readonly FailureMode $onMissingIntegrity = FailureMode::Throw,
        public readonly bool $detachedAttachments = true,
    ) {}
}
