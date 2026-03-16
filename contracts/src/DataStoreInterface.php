<?php

declare(strict_types=1);

namespace KDuma\SimpleDAL\Contracts;

interface DataStoreInterface
{
    /**
     * Get a collection-type entity handle for CRUD operations.
     *
     * @throws Exception\EntityNotFoundException If the entity is not registered or is a singleton.
     */
    public function collection(string $entity): CollectionEntityInterface;

    /**
     * Get a singleton-type entity handle for CRUD operations.
     *
     * @throws Exception\EntityNotFoundException If the entity is not registered or is a collection.
     */
    public function singleton(string $entity): SingletonEntityInterface;

    /**
     * List all registered entity definitions.
     *
     * @return array<string, EntityDefinitionInterface>
     */
    public function entities(): array;

    /**
     * Check whether a named entity is registered.
     */
    public function hasEntity(string $entity): bool;
}
