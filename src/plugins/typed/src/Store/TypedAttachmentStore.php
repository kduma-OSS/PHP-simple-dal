<?php

declare(strict_types=1);

namespace KDuma\SimpleDAL\Typed\Store;

use KDuma\SimpleDAL\Contracts\AttachmentInterface;
use KDuma\SimpleDAL\Contracts\AttachmentStoreInterface;
use KDuma\SimpleDAL\Typed\Contracts\Store\TypedAttachmentStoreInterface;

class TypedAttachmentStore implements TypedAttachmentStoreInterface
{
    public function __construct(
        private readonly AttachmentStoreInterface $inner,
    ) {}

    public function put(\BackedEnum $name, string $contents, string $mimeType = 'application/octet-stream'): AttachmentInterface
    {
        return $this->inner->put($name->value, $contents, $mimeType);
    }

    public function putStream(\BackedEnum $name, mixed $stream, string $mimeType = 'application/octet-stream'): AttachmentInterface
    {
        return $this->inner->putStream($name->value, $stream, $mimeType);
    }

    public function get(\BackedEnum $name): AttachmentInterface
    {
        return $this->inner->get($name->value);
    }

    public function getOrNull(\BackedEnum $name): ?AttachmentInterface
    {
        return $this->inner->getOrNull($name->value);
    }

    public function has(\BackedEnum $name): bool
    {
        return $this->inner->has($name->value);
    }

    public function list(): array
    {
        return $this->inner->list();
    }

    public function delete(\BackedEnum $name): void
    {
        $this->inner->delete($name->value);
    }

    public function deleteAll(): void
    {
        $this->inner->deleteAll();
    }
}
