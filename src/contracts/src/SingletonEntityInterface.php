<?php

declare(strict_types=1);

namespace KDuma\SimpleDAL\Contracts;

interface SingletonEntityInterface
{
    public string $name { get; }

    /**
     * Read the singleton data.
     *
     * @throws Exception\RecordNotFoundException If not yet set.
     */
    public function get(): RecordInterface;

    /**
     * Read the singleton data, or null if not yet set.
     */
    public function getOrNull(): ?RecordInterface;

    /**
     * Check whether the singleton has been stored.
     */
    public function exists(): bool;

    /**
     * Write (create or full replace) the singleton data.
     *
     * @param  array<string, mixed>  $data
     */
    public function set(array $data): RecordInterface;

    /**
     * Persist a modified singleton record.
     */
    public function save(RecordInterface $record): RecordInterface;

    /**
     * Shorthand: partial deep merge update.
     *
     * @param  array<string, mixed>  $data  Fields to merge.
     *
     * @throws Exception\RecordNotFoundException If not yet set.
     */
    public function update(array $data): RecordInterface;

    /**
     * Delete the singleton record and its attachments.
     */
    public function delete(): void;

    /**
     * Access attachment operations for the singleton.
     */
    public function attachments(): AttachmentStoreInterface;
}
