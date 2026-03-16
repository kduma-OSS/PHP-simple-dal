<?php

declare(strict_types=1);

namespace KDuma\SimpleDAL\Typed;

use KDuma\SimpleDAL\Adapter\Contracts\StorageAdapterInterface;
use KDuma\SimpleDAL\Contracts\EntityDefinitionInterface;
use KDuma\SimpleDAL\DataStore;
use KDuma\SimpleDAL\Typed\Entity\TypedCollectionDefinition;
use KDuma\SimpleDAL\Typed\Entity\TypedSingletonDefinition;
use KDuma\SimpleDAL\Typed\Store\TypedCollectionEntity;
use KDuma\SimpleDAL\Typed\Store\TypedSingletonEntity;

class TypedDataStore
{
    private readonly DataStore $inner;

    /** @var array<string, EntityDefinitionInterface> */
    private array $entityDefs;

    /** @var array<string, TypedCollectionEntity|TypedSingletonEntity> */
    private array $stores = [];

    /**
     * @param  EntityDefinitionInterface[]  $entities
     */
    public function __construct(
        StorageAdapterInterface $adapter,
        array $entities,
    ) {
        $this->inner = new DataStore($adapter, $entities);

        $this->entityDefs = [];

        foreach ($entities as $def) {
            $this->entityDefs[$def->name] = $def;
        }
    }

    public function collection(string $entity): TypedCollectionEntity
    {
        if (! isset($this->stores[$entity])) {
            $inner = $this->inner->collection($entity);
            $def = $this->entityDefs[$entity] ?? null;
            $recordClass = ($def instanceof TypedCollectionDefinition) ? $def->recordClass : null;
            $attachmentEnum = ($def instanceof TypedCollectionDefinition) ? $def->attachmentEnum : null;
            $this->stores[$entity] = new TypedCollectionEntity($inner, $recordClass, $attachmentEnum);
        }

        return $this->stores[$entity];
    }

    public function singleton(string $entity): TypedSingletonEntity
    {
        if (! isset($this->stores[$entity])) {
            $inner = $this->inner->singleton($entity);
            $def = $this->entityDefs[$entity] ?? null;
            $recordClass = ($def instanceof TypedSingletonDefinition) ? $def->recordClass : null;
            $attachmentEnum = ($def instanceof TypedSingletonDefinition) ? $def->attachmentEnum : null;
            $this->stores[$entity] = new TypedSingletonEntity($inner, $recordClass, $attachmentEnum);
        }

        return $this->stores[$entity];
    }

    /**
     * @return array<string, EntityDefinitionInterface>
     */
    public function entities(): array
    {
        return $this->inner->entities();
    }

    public function hasEntity(string $entity): bool
    {
        return $this->inner->hasEntity($entity);
    }
}
