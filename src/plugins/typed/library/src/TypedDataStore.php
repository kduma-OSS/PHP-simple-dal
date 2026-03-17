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

    /** @var array<string, TypedCollectionEntity> */
    private array $collections = [];

    /** @var array<string, TypedSingletonEntity> */
    private array $singletons = [];

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
        if (! isset($this->collections[$entity])) {
            $inner = $this->inner->collection($entity);
            $def = $this->entityDefs[$entity] ?? null;
            $recordClass = ($def instanceof TypedCollectionDefinition) ? $def->recordClass : null;
            $this->collections[$entity] = new TypedCollectionEntity($inner, $recordClass);
        }

        return $this->collections[$entity];
    }

    public function singleton(string $entity): TypedSingletonEntity
    {
        if (! isset($this->singletons[$entity])) {
            $inner = $this->inner->singleton($entity);
            $def = $this->entityDefs[$entity] ?? null;
            $recordClass = ($def instanceof TypedSingletonDefinition) ? $def->recordClass : null;
            $this->singletons[$entity] = new TypedSingletonEntity($inner, $recordClass);
        }

        return $this->singletons[$entity];
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
