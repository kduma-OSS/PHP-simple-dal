<?php

declare(strict_types=1);

namespace KDuma\SimpleDAL\Contracts;

interface AttachmentInterface
{
    public string $name { get; }

    public string $mimeType { get; }

    public ?int $size { get; }

    /**
     * Read the full attachment content into memory.
     */
    public function contents(): string;

    /**
     * Get a readable stream resource.
     *
     * @return resource
     */
    public function stream(): mixed;
}
