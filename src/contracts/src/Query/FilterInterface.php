<?php

declare(strict_types=1);

namespace KDuma\SimpleDAL\Contracts\Query;

interface FilterInterface
{
    /**
     * Serialize the filter to a descriptor array the adapter can interpret.
     *
     * @return array<int, array<string, mixed>>
     */
    public function toFilterDescriptors(): array;

    /**
     * Serialize sort instructions to a descriptor array.
     *
     * @return array<int, array<string, mixed>>
     */
    public function toSortDescriptors(): array;

    /**
     * Get the limit, or null for no limit.
     */
    public function getLimit(): ?int;

    /**
     * Get the offset.
     */
    public function getOffset(): int;
}
