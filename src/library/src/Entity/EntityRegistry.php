<?php

declare(strict_types=1);

namespace KDuma\SimpleDAL\Entity;

use KDuma\SimpleDAL\Contracts\EntityDefinitionInterface;
use KDuma\SimpleDAL\Contracts\Exception\EntityNotFoundException;

final class EntityRegistry
{
    /** @var array<string, EntityDefinitionInterface> */
    private array $entities = [];

    public function register(EntityDefinitionInterface $definition): void
    {
        $this->entities[$definition->name] = $definition;
    }

    public function get(string $name): EntityDefinitionInterface
    {
        if (!$this->has($name)) {
            throw new EntityNotFoundException(
                sprintf('Entity "%s" is not registered.', $name),
            );
        }

        return $this->entities[$name];
    }

    public function has(string $name): bool
    {
        return isset($this->entities[$name]);
    }

    /**
     * @return array<string, EntityDefinitionInterface>
     */
    public function all(): array
    {
        return $this->entities;
    }

    public function isSingleton(string $name): bool
    {
        return $this->get($name)->isSingleton;
    }

    public function isCollection(string $name): bool
    {
        return !$this->get($name)->isSingleton;
    }
}
