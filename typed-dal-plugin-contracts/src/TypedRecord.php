<?php

declare(strict_types=1);

namespace KDuma\SimpleDAL\Typed\Contracts;

abstract class TypedRecord
{
    public readonly string $id;

    public readonly ?\DateTimeImmutable $createdAt;

    public readonly ?\DateTimeImmutable $updatedAt;

    /** Non-mapped extra data fields */
    private array $_extraData = [];

    /** Field mappings -- set by the hydrator, not by the constructor */
    private array $_fieldMappings = [];

    /**
     * Protected constructor -- records are created via fromRecord().
     */
    protected function __construct(
        string $id,
        ?\DateTimeImmutable $createdAt = null,
        ?\DateTimeImmutable $updatedAt = null,
    ) {
        $this->id = $id;
        $this->createdAt = $createdAt;
        $this->updatedAt = $updatedAt;
    }

    /**
     * Get a raw field value from extra data by dot-notation path.
     * For use in property hooks on subclasses.
     */
    protected function getRawField(string $path, mixed $default = null): mixed
    {
        $segments = explode('.', $path);
        $current = $this->_extraData;

        foreach ($segments as $segment) {
            if (!is_array($current) || !array_key_exists($segment, $current)) {
                return $default;
            }
            $current = $current[$segment];
        }

        return $current;
    }

    /**
     * Set a raw field value in extra data by dot-notation path.
     * For use in property hooks on subclasses.
     */
    protected function setRawField(string $path, mixed $value): void
    {
        $segments = explode('.', $path);
        $current = &$this->_extraData;

        foreach (array_slice($segments, 0, -1) as $segment) {
            if (!isset($current[$segment]) || !is_array($current[$segment])) {
                $current[$segment] = [];
            }
            $current = &$current[$segment];
        }

        $current[end($segments)] = $value;
    }

    // --- Internal methods (used by TypedRecordHydrator, package-internal) ---

    /** @internal */
    public function _setExtraData(array $data): void
    {
        $this->_extraData = $data;
    }

    /** @internal */
    public function _getExtraData(): array
    {
        return $this->_extraData;
    }

    /** @internal */
    public function _setFieldMappings(array $mappings): void
    {
        $this->_fieldMappings = $mappings;
    }

    /** @internal */
    public function _getFieldMappings(): array
    {
        return $this->_fieldMappings;
    }
}
