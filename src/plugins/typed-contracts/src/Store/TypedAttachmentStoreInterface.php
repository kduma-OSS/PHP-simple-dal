<?php

declare(strict_types=1);

namespace KDuma\SimpleDAL\Typed\Contracts\Store;

use KDuma\SimpleDAL\Contracts\AttachmentInterface;

interface TypedAttachmentStoreInterface
{
    public function put(\BackedEnum $name, string $contents, string $mimeType = 'application/octet-stream'): AttachmentInterface;

    /**
     * @param  resource  $stream
     */
    public function putStream(\BackedEnum $name, mixed $stream, string $mimeType = 'application/octet-stream'): AttachmentInterface;

    public function get(\BackedEnum $name): AttachmentInterface;

    public function getOrNull(\BackedEnum $name): ?AttachmentInterface;

    public function has(\BackedEnum $name): bool;

    /** @return AttachmentInterface[] */
    public function list(): array;

    public function delete(\BackedEnum $name): void;

    public function deleteAll(): void;
}
