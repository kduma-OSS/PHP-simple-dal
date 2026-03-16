<?php

declare(strict_types=1);

namespace KDuma\SimpleDAL\Contracts;

use KDuma\SimpleDAL\Contracts\Query\FilterInterface;

interface CollectionEntityInterface
{
    public string $name { get; }

    /**
     * Create a new record. Returns the persisted record.
     *
     * @param array<string, mixed> $data
     * @param string|null $id Explicit ID, or null to auto-generate.
     *
     * @throws Exception\DuplicateRecordException If a record with the given ID already exists.
     */
    public function create(array $data, ?string $id = null): RecordInterface;

    /**
     * Retrieve a record by its unique ID.
     *
     * @throws Exception\RecordNotFoundException
     */
    public function find(string $id): RecordInterface;

    /**
     * Retrieve a record by its unique ID, or null if not found.
     */
    public function findOrNull(string $id): ?RecordInterface;

    /**
     * Check whether a record with the given ID exists.
     */
    public function has(string $id): bool;

    /**
     * Return all records in this entity.
     *
     * @return RecordInterface[]
     */
    public function all(): array;

    /**
     * Return a filtered/sorted subset of records.
     *
     * @return RecordInterface[]
     */
    public function filter(FilterInterface $filter): array;

    /**
     * Persist a modified record.
     */
    public function save(RecordInterface $record): RecordInterface;

    /**
     * Shorthand: partial deep merge update by ID.
     *
     * @param array<string, mixed> $data Fields to merge into the existing record.
     *
     * @throws Exception\RecordNotFoundException
     */
    public function update(string $id, array $data): RecordInterface;

    /**
     * Full overwrite of a record's data by ID.
     *
     * @param array<string, mixed> $data Complete replacement data.
     *
     * @throws Exception\RecordNotFoundException
     */
    public function replace(string $id, array $data): RecordInterface;

    /**
     * Delete a record and all its attachments.
     *
     * @throws Exception\RecordNotFoundException
     */
    public function delete(string $id): void;

    /**
     * Count records, optionally filtered.
     */
    public function count(?FilterInterface $filter = null): int;

    /**
     * Access attachment operations scoped to a specific record.
     */
    public function attachments(string $recordId): AttachmentStoreInterface;
}
