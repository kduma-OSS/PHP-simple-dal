<?php

declare(strict_types=1);

namespace KDuma\SimpleDAL\Query;

use KDuma\SimpleDAL\Contracts\Query\FilterInterface;
use KDuma\SimpleDAL\Contracts\Query\FilterOperator;
use KDuma\SimpleDAL\Contracts\Query\SortDirection;

final class Filter implements FilterInterface
{
    /** @var FilterCondition[] */
    private array $conditions = [];

    /** @var array<int, array{field: string, direction: SortDirection}> */
    private array $sorts = [];

    private ?int $limit = null;

    private int $offset = 0;

    public function __construct() {}

    /**
     * Static entry point to create a filter with an initial AND condition.
     */
    public static function where(string $field, string|FilterOperator $operator, mixed $value): static
    {
        $instance = new self;

        return $instance->andWhere($field, $operator, $value);
    }

    /**
     * Add an AND condition to the filter.
     */
    public function andWhere(string $field, string|FilterOperator $operator, mixed $value): static
    {
        return $this->addCondition('and', $field, $operator, $value);
    }

    /**
     * Add an OR condition to the filter.
     */
    public function orWhere(string $field, string|FilterOperator $operator, mixed $value): static
    {
        return $this->addCondition('or', $field, $operator, $value);
    }

    public function orderBy(string $field, SortDirection $direction = SortDirection::Asc): static
    {
        $this->sorts[] = ['field' => $field, 'direction' => $direction];

        return $this;
    }

    public function limit(int $limit): static
    {
        $this->limit = $limit;

        return $this;
    }

    public function offset(int $offset): static
    {
        $this->offset = $offset;

        return $this;
    }

    public function toFilterDescriptors(): array
    {
        return array_map(
            fn (FilterCondition $condition) => $condition->toArray(),
            $this->conditions,
        );
    }

    public function toSortDescriptors(): array
    {
        return array_map(
            fn (array $sort) => [
                'field' => $sort['field'],
                'direction' => $sort['direction']->value,
            ],
            $this->sorts,
        );
    }

    public function getLimit(): ?int
    {
        return $this->limit;
    }

    public function getOffset(): int
    {
        return $this->offset;
    }

    private function addCondition(string $type, string $field, string|FilterOperator $operator, mixed $value): static
    {
        if (is_string($operator)) {
            $operator = FilterOperator::from($operator);
        }

        $this->conditions[] = new FilterCondition($type, $field, $operator, $value);

        return $this;
    }
}
