<?php

declare(strict_types=1);

namespace KDuma\SimpleDAL\Typed\Store;

use KDuma\SimpleDAL\Contracts\CollectionEntityInterface;
use KDuma\SimpleDAL\Contracts\Query\FilterInterface;
use KDuma\SimpleDAL\Contracts\RecordInterface;
use KDuma\SimpleDAL\Typed\Contracts\Store\TypedAttachmentStoreInterface;
use KDuma\SimpleDAL\Typed\Contracts\Store\TypedCollectionEntityInterface;
use KDuma\SimpleDAL\Typed\Contracts\TypedRecord;
use KDuma\SimpleDAL\Typed\TypedRecordHydrator;

class TypedCollectionEntity implements TypedCollectionEntityInterface
{
    public string $name {
        get => $this->inner->name;
    }

    /**
     * @param class-string<TypedRecord>|null $recordClass
     * @param class-string<\BackedEnum>|null $attachmentEnum
     */
    public function __construct(
        private readonly CollectionEntityInterface $inner,
        private readonly ?string $recordClass,
        private readonly ?string $attachmentEnum,
    ) {}

    public function create(array $data, ?string $id = null): TypedRecord
    {
        $record = $this->inner->create($data, $id);

        return $this->wrapRecord($record);
    }

    public function find(string $id): TypedRecord
    {
        $record = $this->inner->find($id);

        return $this->wrapRecord($record);
    }

    public function findOrNull(string $id): ?TypedRecord
    {
        $record = $this->inner->findOrNull($id);

        if ($record === null) {
            return null;
        }

        return $this->wrapRecord($record);
    }

    public function has(string $id): bool
    {
        return $this->inner->has($id);
    }

    public function all(): array
    {
        $records = $this->inner->all();

        return array_map(fn (RecordInterface $record) => $this->wrapRecord($record), $records);
    }

    public function filter(FilterInterface $filter): array
    {
        $records = $this->inner->filter($filter);

        return array_map(fn (RecordInterface $record) => $this->wrapRecord($record), $records);
    }

    public function save(TypedRecord $record): TypedRecord
    {
        $data = TypedRecordHydrator::dehydrateToArray($record);
        $innerRecord = $this->inner->replace($record->id, $data);

        return $this->wrapRecord($innerRecord);
    }

    public function update(string $id, array $data): TypedRecord
    {
        $record = $this->inner->update($id, $data);

        return $this->wrapRecord($record);
    }

    public function replace(string $id, array $data): TypedRecord
    {
        $record = $this->inner->replace($id, $data);

        return $this->wrapRecord($record);
    }

    public function delete(string $id): void
    {
        $this->inner->delete($id);
    }

    public function count(?FilterInterface $filter = null): int
    {
        return $this->inner->count($filter);
    }

    public function attachments(string $recordId): TypedAttachmentStoreInterface
    {
        $inner = $this->inner->attachments($recordId);

        return new TypedAttachmentStore($inner);
    }

    private function wrapRecord(RecordInterface $record): TypedRecord
    {
        if ($this->recordClass === null) {
            throw new \LogicException('No recordClass configured for this typed collection entity.');
        }

        return TypedRecordHydrator::hydrateFromRecord($this->recordClass, $record);
    }
}
