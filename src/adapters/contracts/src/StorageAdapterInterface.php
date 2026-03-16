<?php

declare(strict_types=1);

namespace KDuma\SimpleDAL\Adapter\Contracts;

use KDuma\SimpleDAL\Contracts\EntityDefinitionInterface;
use KDuma\SimpleDAL\Contracts\Exception\AttachmentNotFoundException;
use KDuma\SimpleDAL\Contracts\Exception\RecordNotFoundException;

interface StorageAdapterInterface
{
    /**
     * Write (insert or replace) a record.
     *
     * @param  array<string, mixed>  $data
     */
    public function writeRecord(string $entityName, string $recordId, array $data): void;

    /**
     * Read a single record's data.
     *
     * @return array<string, mixed>
     *
     * @throws RecordNotFoundException
     */
    public function readRecord(string $entityName, string $recordId): array;

    /**
     * Delete a single record and all its attachments.
     */
    public function deleteRecord(string $entityName, string $recordId): void;

    /**
     * Check whether a record exists.
     */
    public function recordExists(string $entityName, string $recordId): bool;

    /**
     * List all record IDs for an entity.
     *
     * @return string[]
     */
    public function listRecordIds(string $entityName): array;

    /**
     * Find records matching filters.
     *
     * @param  array<int, array<string, mixed>>  $filters  Serialized filter descriptors.
     * @param  array<int, array<string, mixed>>  $sort  Serialized sort descriptors.
     * @return array<string, array<string, mixed>> Map of record ID => data.
     */
    public function findRecords(
        string $entityName,
        array $filters = [],
        array $sort = [],
        ?int $limit = null,
        int $offset = 0,
    ): array;

    /**
     * Write attachment content.
     *
     * @param  string|resource  $contents
     */
    public function writeAttachment(
        string $entityName,
        string $recordId,
        string $name,
        mixed $contents,
    ): void;

    /**
     * Read attachment content as a stream.
     *
     * @return resource
     *
     * @throws AttachmentNotFoundException
     */
    public function readAttachment(string $entityName, string $recordId, string $name): mixed;

    /**
     * Delete a specific attachment.
     */
    public function deleteAttachment(string $entityName, string $recordId, string $name): void;

    /**
     * Delete all attachments for a record.
     */
    public function deleteAllAttachments(string $entityName, string $recordId): void;

    /**
     * List attachment names for a record.
     *
     * @return string[]
     */
    public function listAttachments(string $entityName, string $recordId): array;

    /**
     * Check if an attachment exists.
     */
    public function attachmentExists(string $entityName, string $recordId, string $name): bool;

    /**
     * Ensure underlying storage structures exist for an entity.
     */
    public function initializeEntity(string $entityName, EntityDefinitionInterface $definition): void;

    /**
     * Remove all data for an entity.
     */
    public function purgeEntity(string $entityName): void;
}
