<?php

declare(strict_types=1);

namespace KDuma\SimpleDAL\DataIntegrity\Exception;

use KDuma\SimpleDAL\Contracts\Exception\CorruptedDataException;

class IntegrityException extends CorruptedDataException
{
    public function __construct(
        public readonly string $entityName,
        public readonly string $recordId,
        public readonly string $expectedHash,
        public readonly string $actualHash,
        string $message = '',
        int $code = 0,
        ?\Throwable $previous = null,
    ) {
        if ($message === '') {
            $message = sprintf(
                "Integrity check failed for record '%s' in entity '%s': expected hash '%s', got '%s'.",
                $this->recordId,
                $this->entityName,
                bin2hex($this->expectedHash),
                bin2hex($this->actualHash),
            );
        }

        parent::__construct($message, $code, $previous);
    }
}
