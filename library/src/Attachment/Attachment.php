<?php

declare(strict_types=1);

namespace KDuma\SimpleDAL\Attachment;

use KDuma\SimpleDAL\Adapter\Contracts\StorageAdapterInterface;
use KDuma\SimpleDAL\Contracts\AttachmentInterface;

final class Attachment implements AttachmentInterface
{
    public string $name {
        get => $this->_name;
    }

    public string $mimeType {
        get => $this->_mimeType;
    }

    public ?int $size {
        get => $this->_size;
    }

    public function __construct(
        private readonly StorageAdapterInterface $adapter,
        private readonly string $entityName,
        private readonly string $recordId,
        private readonly string $_name,
        private readonly string $_mimeType,
        private readonly ?int $_size = null,
    ) {}

    public function contents(): string
    {
        $stream = $this->adapter->readAttachment($this->entityName, $this->recordId, $this->_name);

        try {
            return stream_get_contents($stream);
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }
    }

    public function stream(): mixed
    {
        return $this->adapter->readAttachment($this->entityName, $this->recordId, $this->_name);
    }
}
