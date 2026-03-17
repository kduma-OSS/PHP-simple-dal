<?php

declare(strict_types=1);

namespace KDuma\SimpleDAL\Typed\Entity;

use KDuma\SimpleDAL\Contracts\EntityDefinitionInterface;

class TypedCollectionDefinition implements EntityDefinitionInterface
{
    public bool $isSingleton {
        get => false;
    }

    /**
     * @param  string  $name  Entity name.
     * @param  class-string|null  $recordClass  TypedRecord subclass for hydration.
     * @param  class-string<\BackedEnum>|null  $attachmentEnum  Enum class for typed attachments.
     * @param  bool  $hasAttachments  Whether the entity supports attachments.
     * @param  bool  $hasTimestamps  Whether the entity tracks timestamps.
     * @param  string|null  $idField  Field path to extract ID from data.
     * @param  string[]  $indexedFields  Fields to index for filtering.
     */
    public function __construct(
        public readonly string $name,
        public readonly ?string $recordClass = null,
        public readonly ?string $attachmentEnum = null,
        public readonly bool $hasAttachments = true,
        public readonly bool $hasTimestamps = true,
        public readonly ?string $idField = null,
        public readonly array $indexedFields = [],
    ) {}
}
