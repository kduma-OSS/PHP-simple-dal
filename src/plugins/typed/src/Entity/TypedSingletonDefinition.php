<?php

declare(strict_types=1);

namespace KDuma\SimpleDAL\Typed\Entity;

use KDuma\SimpleDAL\Contracts\EntityDefinitionInterface;

class TypedSingletonDefinition implements EntityDefinitionInterface
{
    public bool $isSingleton {
        get => true;
    }

    /** @var string[] */
    public array $indexedFields {
        get => [];
    }

    /**
     * @param  string  $name  Entity name.
     * @param  class-string|null  $recordClass  TypedRecord subclass for hydration.
     * @param  class-string<\BackedEnum>|null  $attachmentEnum  Enum class for typed attachments.
     * @param  bool  $hasAttachments  Whether the entity supports attachments.
     * @param  bool  $hasTimestamps  Whether the entity tracks timestamps.
     */
    public function __construct(
        public readonly string $name,
        public readonly ?string $recordClass = null,
        public readonly ?string $attachmentEnum = null,
        public readonly bool $hasAttachments = true,
        public readonly bool $hasTimestamps = true,
    ) {}
}
