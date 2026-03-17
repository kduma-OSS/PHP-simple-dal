<?php

declare(strict_types=1);

use KDuma\SimpleDAL\Contracts\Query\FilterOperator;
use KDuma\SimpleDAL\Contracts\Query\SortDirection;
use KDuma\SimpleDAL\Query\Filter;

test('where creates filter with initial AND condition', function () {
    $filter = Filter::where('name', FilterOperator::Equals, 'Alice');

    $descriptors = $filter->toFilterDescriptors();

    expect($descriptors)->toHaveCount(1);
    expect($descriptors[0])->toBe([
        'type' => 'and',
        'field' => 'name',
        'operator' => '=',
        'value' => 'Alice',
    ]);
});

test('where accepts string operator', function () {
    $filter = Filter::where('age', '>', 30);

    $descriptors = $filter->toFilterDescriptors();

    expect($descriptors[0]['operator'])->toBe('>');
});

test('andWhere adds AND condition', function () {
    $filter = Filter::where('name', FilterOperator::Equals, 'Alice')
        ->andWhere('age', FilterOperator::GreaterThan, 25);

    $descriptors = $filter->toFilterDescriptors();

    expect($descriptors)->toHaveCount(2);
    expect($descriptors[0]['type'])->toBe('and');
    expect($descriptors[1]['type'])->toBe('and');
    expect($descriptors[1]['field'])->toBe('age');
});

test('orWhere adds OR condition', function () {
    $filter = Filter::where('name', FilterOperator::Equals, 'Alice')
        ->orWhere('name', FilterOperator::Equals, 'Bob');

    $descriptors = $filter->toFilterDescriptors();

    expect($descriptors)->toHaveCount(2);
    expect($descriptors[1]['type'])->toBe('or');
});

test('orderBy with default ascending direction', function () {
    $filter = (new Filter)->orderBy('name');

    $sorts = $filter->toSortDescriptors();

    expect($sorts)->toHaveCount(1);
    expect($sorts[0])->toBe(['field' => 'name', 'direction' => 'asc']);
});

test('orderBy with explicit descending direction', function () {
    $filter = (new Filter)->orderBy('age', SortDirection::Desc);

    $sorts = $filter->toSortDescriptors();

    expect($sorts[0])->toBe(['field' => 'age', 'direction' => 'desc']);
});

test('multiple orderBy calls chain', function () {
    $filter = (new Filter)->orderBy('name')->orderBy('age', SortDirection::Desc);

    $sorts = $filter->toSortDescriptors();

    expect($sorts)->toHaveCount(2);
});

test('limit and offset', function () {
    $filter = (new Filter)->limit(10)->offset(20);

    expect($filter->getLimit())->toBe(10);
    expect($filter->getOffset())->toBe(20);
});

test('default limit is null and offset is zero', function () {
    $filter = new Filter;

    expect($filter->getLimit())->toBeNull();
    expect($filter->getOffset())->toBe(0);
});

test('empty filter produces empty descriptors', function () {
    $filter = new Filter;

    expect($filter->toFilterDescriptors())->toBeEmpty();
    expect($filter->toSortDescriptors())->toBeEmpty();
});

test('fluent chaining returns same instance', function () {
    $filter = new Filter;

    $result = $filter->limit(5)->offset(10)->orderBy('name');

    expect($result)->toBe($filter);
});
