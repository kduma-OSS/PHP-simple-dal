<?php

declare(strict_types=1);

namespace KDuma\SimpleDAL\Contracts;

interface EntityDefinitionInterface
{
    public string $name { get; }

    public bool $isSingleton { get; }

    public bool $hasAttachments { get; }

    public bool $hasTimestamps { get; }

    /**
     * Fields that should be indexed for faster filtering.
     * Dot-notation supported (e.g. "subject.commonName").
     *
     * @return string[]
     */
    public array $indexedFields { get; }
}
