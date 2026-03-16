<?php

declare(strict_types=1);

namespace KDuma\SimpleDAL\Adapter\Zip;

use KDuma\SimpleDAL\Adapter\Contracts\StorageAdapterInterface;
use KDuma\SimpleDAL\Adapter\Directory\DirectoryAdapter;
use KDuma\SimpleDAL\Contracts\EntityDefinitionInterface;
use League\Flysystem\FilesystemOperator;

class ZipAdapter implements StorageAdapterInterface
{
    private DirectoryAdapter $inner;

    public function __construct(FilesystemOperator $filesystem)
    {
        $this->inner = new DirectoryAdapter($filesystem);
    }

    public function writeRecord(string $entityName, string $recordId, array $data): void
    {
        $this->inner->writeRecord($entityName, $recordId, $data);
    }

    public function readRecord(string $entityName, string $recordId): array
    {
        return $this->inner->readRecord($entityName, $recordId);
    }

    public function deleteRecord(string $entityName, string $recordId): void
    {
        $this->inner->deleteRecord($entityName, $recordId);
    }

    public function recordExists(string $entityName, string $recordId): bool
    {
        return $this->inner->recordExists($entityName, $recordId);
    }

    public function listRecordIds(string $entityName): array
    {
        return $this->inner->listRecordIds($entityName);
    }

    public function findRecords(
        string $entityName,
        array $filters = [],
        array $sort = [],
        ?int $limit = null,
        int $offset = 0,
    ): array {
        return $this->inner->findRecords($entityName, $filters, $sort, $limit, $offset);
    }

    public function writeAttachment(
        string $entityName,
        string $recordId,
        string $name,
        mixed $contents,
    ): void {
        $this->inner->writeAttachment($entityName, $recordId, $name, $contents);
    }

    public function readAttachment(string $entityName, string $recordId, string $name): mixed
    {
        return $this->inner->readAttachment($entityName, $recordId, $name);
    }

    public function deleteAttachment(string $entityName, string $recordId, string $name): void
    {
        $this->inner->deleteAttachment($entityName, $recordId, $name);
    }

    public function deleteAllAttachments(string $entityName, string $recordId): void
    {
        $this->inner->deleteAllAttachments($entityName, $recordId);
    }

    public function listAttachments(string $entityName, string $recordId): array
    {
        return $this->inner->listAttachments($entityName, $recordId);
    }

    public function attachmentExists(string $entityName, string $recordId, string $name): bool
    {
        return $this->inner->attachmentExists($entityName, $recordId, $name);
    }

    public function initializeEntity(string $entityName, EntityDefinitionInterface $definition): void
    {
        $this->inner->initializeEntity($entityName, $definition);
    }

    public function purgeEntity(string $entityName): void
    {
        $this->inner->purgeEntity($entityName);
    }
}
