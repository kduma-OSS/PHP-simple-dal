<?php

declare(strict_types=1);

namespace KDuma\SimpleDAL;

use KDuma\SimpleDAL\Adapter\Contracts\StorageAdapterInterface;
use KDuma\SimpleDAL\Contracts\CollectionEntityInterface;
use KDuma\SimpleDAL\Contracts\DataStoreInterface;
use KDuma\SimpleDAL\Contracts\EntityDefinitionInterface;
use KDuma\SimpleDAL\Contracts\Exception\EntityNotFoundException;
use KDuma\SimpleDAL\Contracts\SingletonEntityInterface;
use KDuma\SimpleDAL\Entity\EntityRegistry;
use KDuma\SimpleDAL\Store\CollectionEntityStore;
use KDuma\SimpleDAL\Store\SingletonEntityStore;

final class DataStore implements DataStoreInterface
{
    private readonly EntityRegistry $registry;

    /** @var array<string, CollectionEntityStore|SingletonEntityStore> */
    private array $stores = [];

    /**
     * @param EntityDefinitionInterface[] $entities
     */
    public function __construct(
        private readonly StorageAdapterInterface $adapter,
        array $entities,
    ) {
        $this->registry = new EntityRegistry();

        foreach ($entities as $entity) {
            $this->registry->register($entity);
            $this->adapter->initializeEntity($entity->name, $entity);
        }
    }

    public function collection(string $entity): CollectionEntityInterface
    {
        $definition = $this->registry->get($entity);

        if ($definition->isSingleton) {
            throw new EntityNotFoundException(
                sprintf('Entity "%s" is a singleton. Use singleton() instead of collection().', $entity),
            );
        }

        if (!isset($this->stores[$entity])) {
            $this->stores[$entity] = new CollectionEntityStore($this->adapter, $definition);
        }

        return $this->stores[$entity];
    }

    public function singleton(string $entity): SingletonEntityInterface
    {
        $definition = $this->registry->get($entity);

        if (!$definition->isSingleton) {
            throw new EntityNotFoundException(
                sprintf('Entity "%s" is a collection. Use collection() instead of singleton().', $entity),
            );
        }

        if (!isset($this->stores[$entity])) {
            $this->stores[$entity] = new SingletonEntityStore($this->adapter, $definition);
        }

        return $this->stores[$entity];
    }

    public function entities(): array
    {
        return $this->registry->all();
    }

    public function hasEntity(string $entity): bool
    {
        return $this->registry->has($entity);
    }
}
