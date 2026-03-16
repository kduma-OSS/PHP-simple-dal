<?php

declare(strict_types=1);

namespace KDuma\SimpleDAL\Contracts;

interface AttachmentStoreInterface
{
    /**
     * Store an attachment from raw content.
     */
    public function put(string $name, string $contents, string $mimeType = 'application/octet-stream'): AttachmentInterface;

    /**
     * Store an attachment from a stream resource.
     *
     * @param  resource  $stream
     */
    public function putStream(string $name, mixed $stream, string $mimeType = 'application/octet-stream'): AttachmentInterface;

    /**
     * Retrieve an attachment descriptor.
     *
     * @throws Exception\AttachmentNotFoundException
     */
    public function get(string $name): AttachmentInterface;

    /**
     * Retrieve an attachment, or null if not found.
     */
    public function getOrNull(string $name): ?AttachmentInterface;

    /**
     * Check whether an attachment exists.
     */
    public function has(string $name): bool;

    /**
     * List all attachments for this record.
     *
     * @return AttachmentInterface[]
     */
    public function list(): array;

    /**
     * Delete an attachment.
     *
     * @throws Exception\AttachmentNotFoundException
     */
    public function delete(string $name): void;

    /**
     * Delete all attachments for this record.
     */
    public function deleteAll(): void;
}
