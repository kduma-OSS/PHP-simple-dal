<?php

declare(strict_types=1);

namespace KDuma\SimpleDAL\Query;

use KDuma\SimpleDAL\Contracts\Query\FilterOperator;

final readonly class FilterCondition
{
    public function __construct(
        public string $type,
        public string $field,
        public FilterOperator $operator,
        public mixed $value,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'field' => $this->field,
            'operator' => $this->operator->value,
            'value' => $this->value,
        ];
    }
}
