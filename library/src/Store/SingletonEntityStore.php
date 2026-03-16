<?php

declare(strict_types=1);

namespace KDuma\SimpleDAL\Store;

use KDuma\SimpleDAL\Adapter\Contracts\StorageAdapterInterface;
use KDuma\SimpleDAL\Contracts\AttachmentStoreInterface;
use KDuma\SimpleDAL\Contracts\EntityDefinitionInterface;
use KDuma\SimpleDAL\Contracts\RecordInterface;
use KDuma\SimpleDAL\Contracts\SingletonEntityInterface;

final class SingletonEntityStore implements SingletonEntityInterface
{
    private const string SINGLETON_ID = '_singleton';

    private readonly CollectionEntityStore $inner;

    public string $name {
        get => $this->inner->name;
    }

    public function __construct(
        StorageAdapterInterface $adapter,
        EntityDefinitionInterface $definition,
    ) {
        $this->inner = new CollectionEntityStore($adapter, $definition);
    }

    public function get(): RecordInterface
    {
        return $this->inner->find(self::SINGLETON_ID);
    }

    public function getOrNull(): ?RecordInterface
    {
        return $this->inner->findOrNull(self::SINGLETON_ID);
    }

    public function exists(): bool
    {
        return $this->inner->has(self::SINGLETON_ID);
    }

    public function set(array $data): RecordInterface
    {
        if ($this->exists()) {
            return $this->inner->replace(self::SINGLETON_ID, $data);
        }

        return $this->inner->create($data, self::SINGLETON_ID);
    }

    public function save(RecordInterface $record): RecordInterface
    {
        return $this->inner->save($record);
    }

    public function update(array $data): RecordInterface
    {
        return $this->inner->update(self::SINGLETON_ID, $data);
    }

    public function delete(): void
    {
        if ($this->exists()) {
            $this->inner->delete(self::SINGLETON_ID);
        }
    }

    public function attachments(): AttachmentStoreInterface
    {
        return $this->inner->attachments(self::SINGLETON_ID);
    }
}
