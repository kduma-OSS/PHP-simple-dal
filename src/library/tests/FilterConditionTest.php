<?php

declare(strict_types=1);

use KDuma\SimpleDAL\Contracts\Query\FilterOperator;
use KDuma\SimpleDAL\Query\FilterCondition;

test('FilterCondition stores constructor values', function () {
    $condition = new FilterCondition(
        type: 'and',
        field: 'name',
        operator: FilterOperator::Equals,
        value: 'Alice',
    );

    expect($condition->type)->toBe('and');
    expect($condition->field)->toBe('name');
    expect($condition->operator)->toBe(FilterOperator::Equals);
    expect($condition->value)->toBe('Alice');
});

test('FilterCondition toArray serializes correctly', function () {
    $condition = new FilterCondition(
        type: 'or',
        field: 'age',
        operator: FilterOperator::GreaterThan,
        value: 30,
    );

    expect($condition->toArray())->toBe([
        'type' => 'or',
        'field' => 'age',
        'operator' => '>',
        'value' => 30,
    ]);
});

test('FilterCondition toArray works with array value', function () {
    $condition = new FilterCondition(
        type: 'and',
        field: 'status',
        operator: FilterOperator::In,
        value: ['active', 'pending'],
    );

    $arr = $condition->toArray();

    expect($arr['operator'])->toBe('in');
    expect($arr['value'])->toBe(['active', 'pending']);
});
