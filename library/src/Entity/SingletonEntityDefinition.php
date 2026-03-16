<?php

declare(strict_types=1);

namespace KDuma\SimpleDAL\Entity;

use KDuma\SimpleDAL\Contracts\EntityDefinitionInterface;

final class SingletonEntityDefinition implements EntityDefinitionInterface
{
    public bool $isSingleton {
        get => true;
    }

    /** @var string[] */
    public array $indexedFields {
        get => [];
    }

    public function __construct(
        public readonly string $name,
        public readonly bool $hasAttachments = true,
        public readonly bool $hasTimestamps = true,
    ) {}
}
