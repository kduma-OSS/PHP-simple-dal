<?php

declare(strict_types=1);

namespace KDuma\SimpleDAL\Typed\Store;

use KDuma\SimpleDAL\Contracts\RecordInterface;
use KDuma\SimpleDAL\Contracts\SingletonEntityInterface;
use KDuma\SimpleDAL\Typed\Contracts\Store\TypedAttachmentStoreInterface;
use KDuma\SimpleDAL\Typed\Contracts\Store\TypedSingletonEntityInterface;
use KDuma\SimpleDAL\Typed\Contracts\TypedRecord;
use KDuma\SimpleDAL\Typed\TypedRecordHydrator;

class TypedSingletonEntity implements TypedSingletonEntityInterface
{
    public string $name {
        get => $this->inner->name;
    }

    /**
     * @param  class-string<TypedRecord>|null  $recordClass
     */
    public function __construct(
        private readonly SingletonEntityInterface $inner,
        private readonly ?string $recordClass,
    ) {}

    public function get(): TypedRecord
    {
        $record = $this->inner->get();

        return $this->wrapRecord($record);
    }

    public function getOrNull(): ?TypedRecord
    {
        $record = $this->inner->getOrNull();

        if ($record === null) {
            return null;
        }

        return $this->wrapRecord($record); // @codeCoverageIgnore
    }

    public function exists(): bool
    {
        return $this->inner->exists();
    }

    public function make(): TypedRecord
    {
        if ($this->recordClass === null) { // @codeCoverageIgnoreStart
            throw new \LogicException('No recordClass configured for this typed singleton entity.');
        } // @codeCoverageIgnoreEnd

        return TypedRecordHydrator::createBlank($this->recordClass);
    }

    public function set(TypedRecord $record): TypedRecord
    {
        $data = TypedRecordHydrator::dehydrateToArray($record);
        $innerRecord = $this->inner->set($data);

        return $this->wrapRecord($innerRecord);
    }

    public function save(TypedRecord $record): TypedRecord
    {
        $data = TypedRecordHydrator::dehydrateToArray($record);
        $innerRecord = $this->inner->set($data);

        return $this->wrapRecord($innerRecord);
    }

    public function delete(): void
    {
        $this->inner->delete();
    }

    /** @codeCoverageIgnore */
    public function attachments(): TypedAttachmentStoreInterface
    {
        $inner = $this->inner->attachments();

        return new TypedAttachmentStore($inner);
    }

    private function wrapRecord(RecordInterface $record): TypedRecord
    {
        if ($this->recordClass === null) { // @codeCoverageIgnoreStart
            throw new \LogicException('No recordClass configured for this typed singleton entity.');
        } // @codeCoverageIgnoreEnd

        return TypedRecordHydrator::hydrateFromRecord($this->recordClass, $record);
    }
}
