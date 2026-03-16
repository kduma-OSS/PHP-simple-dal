<?php

declare(strict_types=1);

namespace KDuma\SimpleDAL\Entity;

use KDuma\SimpleDAL\Contracts\EntityDefinitionInterface;

final class CollectionEntityDefinition implements EntityDefinitionInterface
{
    public bool $isSingleton {
        get => false;
    }

    /**
     * @param string[] $indexedFields
     */
    public function __construct(
        public readonly string $name,
        public readonly bool $hasAttachments = true,
        public readonly bool $hasTimestamps = true,
        public readonly ?string $idField = null,
        public readonly array $indexedFields = [],
    ) {}
}
