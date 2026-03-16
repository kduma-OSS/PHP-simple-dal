<?php

declare(strict_types=1);

namespace KDuma\SimpleDAL\Typed\Contracts\Store;

use KDuma\SimpleDAL\Contracts\Query\FilterInterface;
use KDuma\SimpleDAL\Typed\Contracts\TypedRecord;

interface TypedCollectionEntityInterface
{
    public string $name { get; }

    public function create(array $data, ?string $id = null): TypedRecord;

    public function find(string $id): TypedRecord;

    public function findOrNull(string $id): ?TypedRecord;

    public function has(string $id): bool;

    /** @return TypedRecord[] */
    public function all(): array;

    /** @return TypedRecord[] */
    public function filter(FilterInterface $filter): array;

    public function save(TypedRecord $record): TypedRecord;

    public function update(string $id, array $data): TypedRecord;

    public function replace(string $id, array $data): TypedRecord;

    public function delete(string $id): void;

    public function count(?FilterInterface $filter = null): int;

    public function attachments(string $recordId): TypedAttachmentStoreInterface;
}
