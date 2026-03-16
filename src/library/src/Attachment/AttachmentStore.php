<?php

declare(strict_types=1);

namespace KDuma\SimpleDAL\Attachment;

use KDuma\SimpleDAL\Adapter\Contracts\StorageAdapterInterface;
use KDuma\SimpleDAL\Contracts\AttachmentInterface;
use KDuma\SimpleDAL\Contracts\AttachmentStoreInterface;
use KDuma\SimpleDAL\Contracts\Exception\AttachmentNotFoundException;

final class AttachmentStore implements AttachmentStoreInterface
{
    public function __construct(
        private readonly StorageAdapterInterface $adapter,
        private readonly string $entityName,
        private readonly string $recordId,
    ) {}

    public function put(string $name, string $contents, string $mimeType = 'application/octet-stream'): AttachmentInterface
    {
        $this->adapter->writeAttachment($this->entityName, $this->recordId, $name, $contents);

        return new Attachment(
            adapter: $this->adapter,
            entityName: $this->entityName,
            recordId: $this->recordId,
            _name: $name,
            _mimeType: $mimeType,
            _size: strlen($contents),
        );
    }

    public function putStream(string $name, mixed $stream, string $mimeType = 'application/octet-stream'): AttachmentInterface
    {
        $this->adapter->writeAttachment($this->entityName, $this->recordId, $name, $stream);

        return new Attachment(
            adapter: $this->adapter,
            entityName: $this->entityName,
            recordId: $this->recordId,
            _name: $name,
            _mimeType: $mimeType,
            _size: null,
        );
    }

    public function get(string $name): AttachmentInterface
    {
        if (! $this->has($name)) {
            throw new AttachmentNotFoundException(
                sprintf('Attachment "%s" not found for record "%s" in entity "%s".', $name, $this->recordId, $this->entityName),
            );
        }

        return $this->buildAttachment($name);
    }

    public function getOrNull(string $name): ?AttachmentInterface
    {
        if (! $this->has($name)) {
            return null;
        }

        return $this->buildAttachment($name);
    }

    public function has(string $name): bool
    {
        return $this->adapter->attachmentExists($this->entityName, $this->recordId, $name);
    }

    public function list(): array
    {
        $names = $this->adapter->listAttachments($this->entityName, $this->recordId);

        return array_map(
            fn (string $name) => $this->buildAttachment($name),
            $names,
        );
    }

    public function delete(string $name): void
    {
        if (! $this->has($name)) {
            throw new AttachmentNotFoundException(
                sprintf('Attachment "%s" not found for record "%s" in entity "%s".', $name, $this->recordId, $this->entityName),
            );
        }

        $this->adapter->deleteAttachment($this->entityName, $this->recordId, $name);
    }

    public function deleteAll(): void
    {
        $this->adapter->deleteAllAttachments($this->entityName, $this->recordId);
    }

    private function buildAttachment(string $name): Attachment
    {
        return new Attachment(
            adapter: $this->adapter,
            entityName: $this->entityName,
            recordId: $this->recordId,
            _name: $name,
            _mimeType: 'application/octet-stream',
            _size: null,
        );
    }
}
