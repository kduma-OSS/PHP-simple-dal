<?php

declare(strict_types=1);

namespace KDuma\SimpleDAL\Typed\Contracts\Store;

use KDuma\SimpleDAL\Typed\Contracts\TypedRecord;

interface TypedSingletonEntityInterface
{
    public string $name { get; }

    public function get(): TypedRecord;

    public function getOrNull(): ?TypedRecord;

    public function exists(): bool;

    public function set(array $data): TypedRecord;

    public function save(TypedRecord $record): TypedRecord;

    public function update(array $data): TypedRecord;

    public function delete(): void;

    public function attachments(): TypedAttachmentStoreInterface;
}
