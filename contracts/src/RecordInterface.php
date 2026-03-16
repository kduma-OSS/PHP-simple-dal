<?php

declare(strict_types=1);

namespace KDuma\SimpleDAL\Contracts;

interface RecordInterface
{
    public string $id { get; }

    /** @var array<string, mixed> */
    public array $data { get; }

    public ?\DateTimeImmutable $createdAt { get; }

    public ?\DateTimeImmutable $updatedAt { get; }

    /**
     * Read a single field using dot-notation (e.g. "subject.commonName").
     */
    public function get(string $key, mixed $default = null): mixed;

    /**
     * Check whether a key exists (dot-notation).
     */
    public function has(string $key): bool;

    /**
     * Return the record's data as a JSON string.
     */
    public function toJson(int $flags = 0): string;

    /**
     * Set a field value using dot-notation. Fluent.
     * Changes are in-memory only until persisted via entity store.
     */
    public function set(string $key, mixed $value): static;

    /**
     * Remove a field using dot-notation. Fluent.
     */
    public function unset(string $key): static;

    /**
     * Deep merge an array of data into the record. Fluent.
     *
     * @param array<string, mixed> $data
     */
    public function merge(array $data): static;
}
