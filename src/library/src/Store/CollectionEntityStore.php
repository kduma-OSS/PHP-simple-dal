<?php

declare(strict_types=1);

namespace KDuma\SimpleDAL\Store;

use KDuma\SimpleDAL\Adapter\Contracts\StorageAdapterInterface;
use KDuma\SimpleDAL\Attachment\AttachmentStore;
use KDuma\SimpleDAL\Contracts\AttachmentStoreInterface;
use KDuma\SimpleDAL\Contracts\CollectionEntityInterface;
use KDuma\SimpleDAL\Contracts\EntityDefinitionInterface;
use KDuma\SimpleDAL\Contracts\Exception\DuplicateRecordException;
use KDuma\SimpleDAL\Contracts\Exception\RecordNotFoundException;
use KDuma\SimpleDAL\Contracts\Query\FilterInterface;
use KDuma\SimpleDAL\Contracts\RecordInterface;
use KDuma\SimpleDAL\Entity\CollectionEntityDefinition;
use KDuma\SimpleDAL\Record;

final class CollectionEntityStore implements CollectionEntityInterface
{
    private const string ID_PATTERN = '/^[a-zA-Z0-9._-]+$/';

    public string $name {
        get => $this->definition->name;
    }

    public function __construct(
        private readonly StorageAdapterInterface $adapter,
        private readonly EntityDefinitionInterface $definition,
    ) {}

    public function create(array $data, ?string $id = null): RecordInterface
    {
        $id = $this->resolveId($data, $id);

        $this->validateId($id);

        if ($this->adapter->recordExists($this->definition->name, $id)) {
            throw new DuplicateRecordException(
                sprintf('A record with ID "%s" already exists in entity "%s".', $id, $this->definition->name),
            );
        }

        $now = new \DateTimeImmutable;

        if ($this->definition->hasTimestamps) {
            $data['_createdAt'] = $now->format(\DateTimeInterface::RFC3339_EXTENDED);
            $data['_updatedAt'] = $now->format(\DateTimeInterface::RFC3339_EXTENDED);
        }

        $this->adapter->writeRecord($this->definition->name, $id, $data);

        return new Record(
            _id: $id,
            _data: $this->stripMetaFields($data),
            _createdAt: $this->definition->hasTimestamps ? $now : null,
            _updatedAt: $this->definition->hasTimestamps ? $now : null,
        );
    }

    public function find(string $id): RecordInterface
    {
        $data = $this->adapter->readRecord($this->definition->name, $id);

        return $this->hydrateRecord($id, $data);
    }

    public function findOrNull(string $id): ?RecordInterface
    {
        if (! $this->adapter->recordExists($this->definition->name, $id)) {
            return null;
        }

        return $this->find($id);
    }

    public function has(string $id): bool
    {
        return $this->adapter->recordExists($this->definition->name, $id);
    }

    public function all(): array
    {
        $ids = $this->adapter->listRecordIds($this->definition->name);

        return array_map(fn (string $id) => $this->find($id), $ids);
    }

    public function filter(FilterInterface $filter): array
    {
        $results = $this->adapter->findRecords(
            entityName: $this->definition->name,
            filters: $filter->toFilterDescriptors(),
            sort: $filter->toSortDescriptors(),
            limit: $filter->getLimit(),
            offset: $filter->getOffset(),
        );

        $records = [];

        foreach ($results as $id => $data) {
            $records[] = $this->hydrateRecord($id, $data);
        }

        return $records;
    }

    public function save(RecordInterface $record): RecordInterface
    {
        $data = $record->data;
        $now = new \DateTimeImmutable;

        if ($this->definition->hasTimestamps) {
            if ($record->createdAt !== null) {
                $data['_createdAt'] = $record->createdAt->format(\DateTimeInterface::RFC3339_EXTENDED);
            }

            $data['_updatedAt'] = $now->format(\DateTimeInterface::RFC3339_EXTENDED);
        }

        $this->adapter->writeRecord($this->definition->name, $record->id, $data);

        return new Record(
            _id: $record->id,
            _data: $this->stripMetaFields($data),
            _createdAt: $record->createdAt,
            _updatedAt: $this->definition->hasTimestamps ? $now : null,
        );
    }

    public function update(string $id, array $data): RecordInterface
    {
        $existing = $this->adapter->readRecord($this->definition->name, $id);
        $merged = Record::deepMerge($existing, $data);
        $now = new \DateTimeImmutable;

        if ($this->definition->hasTimestamps) {
            $merged['_updatedAt'] = $now->format(\DateTimeInterface::RFC3339_EXTENDED);
        }

        $this->adapter->writeRecord($this->definition->name, $id, $merged);

        return $this->hydrateRecord($id, $merged);
    }

    public function replace(string $id, array $data): RecordInterface
    {
        // Ensure the record exists first.
        $existing = $this->adapter->readRecord($this->definition->name, $id);
        $now = new \DateTimeImmutable;

        if ($this->definition->hasTimestamps) {
            // Preserve original createdAt.
            if (isset($existing['_createdAt'])) {
                $data['_createdAt'] = $existing['_createdAt'];
            }

            $data['_updatedAt'] = $now->format(\DateTimeInterface::RFC3339_EXTENDED);
        }

        $this->adapter->writeRecord($this->definition->name, $id, $data);

        return $this->hydrateRecord($id, $data);
    }

    public function delete(string $id): void
    {
        if (! $this->adapter->recordExists($this->definition->name, $id)) {
            throw new RecordNotFoundException(
                sprintf('Record "%s" not found in entity "%s".', $id, $this->definition->name),
            );
        }

        $this->adapter->deleteRecord($this->definition->name, $id);
    }

    public function count(?FilterInterface $filter = null): int
    {
        if ($filter !== null) {
            $results = $this->adapter->findRecords(
                entityName: $this->definition->name,
                filters: $filter->toFilterDescriptors(),
                sort: $filter->toSortDescriptors(),
                limit: $filter->getLimit(),
                offset: $filter->getOffset(),
            );

            return count($results);
        }

        return count($this->adapter->listRecordIds($this->definition->name));
    }

    public function attachments(string $recordId): AttachmentStoreInterface
    {
        return new AttachmentStore(
            adapter: $this->adapter,
            entityName: $this->definition->name,
            recordId: $recordId,
        );
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function resolveId(array $data, ?string $id): string
    {
        if ($id !== null) {
            return $id;
        }

        // If definition has an idField, extract from data.
        if ($this->definition instanceof CollectionEntityDefinition && $this->definition->idField !== null) {
            $idField = $this->definition->idField;
            $segments = explode('.', $idField);
            $current = $data;

            foreach ($segments as $segment) {
                if (! is_array($current) || ! array_key_exists($segment, $current)) {
                    break;
                }

                $current = $current[$segment];
            }

            if (is_string($current) && $current !== '') {
                return $current;
            }
        }

        return self::generateUuidV7();
    }

    private function validateId(string $id): void
    {
        if (! preg_match(self::ID_PATTERN, $id)) {
            throw new \InvalidArgumentException(
                sprintf('Invalid record ID "%s". IDs must match the pattern [a-zA-Z0-9._-]+.', $id),
            );
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function hydrateRecord(string $id, array $data): Record
    {
        $createdAt = null;
        $updatedAt = null;

        if ($this->definition->hasTimestamps) {
            if (isset($data['_createdAt']) && is_string($data['_createdAt'])) {
                $createdAt = new \DateTimeImmutable($data['_createdAt']);
            }

            if (isset($data['_updatedAt']) && is_string($data['_updatedAt'])) {
                $updatedAt = new \DateTimeImmutable($data['_updatedAt']);
            }
        }

        return new Record(
            _id: $id,
            _data: $this->stripMetaFields($data),
            _createdAt: $createdAt,
            _updatedAt: $updatedAt,
        );
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function stripMetaFields(array $data): array
    {
        unset($data['_createdAt'], $data['_updatedAt']);

        return $data;
    }

    /**
     * Generate a UUID v7 (time-ordered) using random bytes.
     *
     * Layout (RFC 9562):
     *   48 bits - unix_ts_ms
     *    4 bits - version (0111)
     *   12 bits - rand_a
     *    2 bits - variant (10)
     *   62 bits - rand_b
     */
    private static function generateUuidV7(): string
    {
        $time = (int) (microtime(true) * 1000);
        $random = random_bytes(10);

        // Encode 48-bit timestamp into the first 6 bytes.
        $bytes = pack('N', ($time >> 16) & 0xFFFFFFFF)
            .pack('n', $time & 0xFFFF);

        // Append 10 random bytes, then set version and variant bits.
        $bytes .= $random;

        // Set version to 7 (bits 48-51).
        $bytes[6] = chr((ord($bytes[6]) & 0x0F) | 0x70);

        // Set variant to RFC 4122 (bits 64-65).
        $bytes[8] = chr((ord($bytes[8]) & 0x3F) | 0x80);

        $hex = bin2hex($bytes);

        return sprintf(
            '%s-%s-%s-%s-%s',
            substr($hex, 0, 8),
            substr($hex, 8, 4),
            substr($hex, 12, 4),
            substr($hex, 16, 4),
            substr($hex, 20, 12),
        );
    }
}
