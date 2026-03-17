<?php

declare(strict_types=1);

use KDuma\SimpleDAL\Record;

test('constructor sets id and data', function () {
    $record = new Record('rec-1', ['name' => 'Alice']);

    expect($record->id)->toBe('rec-1');
    expect($record->data)->toBe(['name' => 'Alice']);
});

test('constructor defaults', function () {
    $record = new Record('rec-1');

    expect($record->data)->toBeEmpty();
    expect($record->createdAt)->toBeNull();
    expect($record->updatedAt)->toBeNull();
});

test('constructor with timestamps', function () {
    $created = new DateTimeImmutable('2024-01-01');
    $updated = new DateTimeImmutable('2024-06-01');

    $record = new Record('rec-1', [], $created, $updated);

    expect($record->createdAt)->toBe($created);
    expect($record->updatedAt)->toBe($updated);
});

test('get returns simple key value', function () {
    $record = new Record('r', ['name' => 'Alice', 'age' => 30]);

    expect($record->get('name'))->toBe('Alice');
    expect($record->get('age'))->toBe(30);
});

test('get returns nested dot-notation value', function () {
    $record = new Record('r', ['meta' => ['role' => 'admin', 'nested' => ['deep' => true]]]);

    expect($record->get('meta.role'))->toBe('admin');
    expect($record->get('meta.nested.deep'))->toBeTrue();
});

test('get returns default for missing key', function () {
    $record = new Record('r', ['name' => 'Alice']);

    expect($record->get('missing'))->toBeNull();
    expect($record->get('missing', 'fallback'))->toBe('fallback');
});

test('get returns default when intermediate is not array', function () {
    $record = new Record('r', ['name' => 'Alice']);

    expect($record->get('name.sub', 'default'))->toBe('default');
});

test('has returns true for existing key', function () {
    $record = new Record('r', ['name' => 'Alice']);

    expect($record->has('name'))->toBeTrue();
});

test('has returns false for missing key', function () {
    $record = new Record('r', ['name' => 'Alice']);

    expect($record->has('missing'))->toBeFalse();
});

test('has works with nested dot-notation', function () {
    $record = new Record('r', ['meta' => ['role' => 'admin']]);

    expect($record->has('meta.role'))->toBeTrue();
    expect($record->has('meta.missing'))->toBeFalse();
});

test('has returns false when intermediate is not array', function () {
    $record = new Record('r', ['name' => 'Alice']);

    expect($record->has('name.sub'))->toBeFalse();
});

test('set simple key', function () {
    $record = new Record('r', ['name' => 'Alice']);

    $result = $record->set('age', 30);

    expect($result)->toBe($record);
    expect($record->get('age'))->toBe(30);
});

test('set nested dot-notation creates intermediate arrays', function () {
    $record = new Record('r', []);

    $record->set('meta.role', 'admin');

    expect($record->get('meta.role'))->toBe('admin');
    expect($record->get('meta'))->toBe(['role' => 'admin']);
});

test('set overwrites non-array intermediate', function () {
    $record = new Record('r', ['meta' => 'scalar']);

    $record->set('meta.role', 'admin');

    expect($record->get('meta.role'))->toBe('admin');
});

test('unset removes simple key', function () {
    $record = new Record('r', ['name' => 'Alice', 'age' => 30]);

    $result = $record->unset('age');

    expect($result)->toBe($record);
    expect($record->has('age'))->toBeFalse();
    expect($record->has('name'))->toBeTrue();
});

test('unset removes nested key', function () {
    $record = new Record('r', ['meta' => ['role' => 'admin', 'active' => true]]);

    $record->unset('meta.role');

    expect($record->has('meta.role'))->toBeFalse();
    expect($record->has('meta.active'))->toBeTrue();
});

test('unset returns self when intermediate path missing', function () {
    $record = new Record('r', ['name' => 'Alice']);

    $result = $record->unset('missing.nested.key');

    expect($result)->toBe($record);
    expect($record->data)->toBe(['name' => 'Alice']);
});

test('merge shallow data', function () {
    $record = new Record('r', ['name' => 'Alice', 'age' => 30]);

    $result = $record->merge(['age' => 31, 'email' => 'a@b.com']);

    expect($result)->toBe($record);
    expect($record->get('name'))->toBe('Alice');
    expect($record->get('age'))->toBe(31);
    expect($record->get('email'))->toBe('a@b.com');
});

test('merge deep data', function () {
    $record = new Record('r', ['meta' => ['role' => 'admin', 'active' => true]]);

    $record->merge(['meta' => ['role' => 'user', 'new' => 'value']]);

    expect($record->get('meta.role'))->toBe('user');
    expect($record->get('meta.active'))->toBeTrue();
    expect($record->get('meta.new'))->toBe('value');
});

test('toJson returns valid JSON', function () {
    $record = new Record('r', ['name' => 'Alice', 'age' => 30]);

    $json = $record->toJson();

    expect($json)->toBe('{"name":"Alice","age":30}');
});

test('toJson accepts flags', function () {
    $record = new Record('r', ['name' => 'Alice']);

    $json = $record->toJson(JSON_PRETTY_PRINT);

    expect($json)->toContain("\n");
});

test('setCreatedAt updates createdAt', function () {
    $record = new Record('r');
    $date = new DateTimeImmutable('2024-01-01');

    $record->setCreatedAt($date);

    expect($record->createdAt)->toBe($date);
});

test('setUpdatedAt updates updatedAt', function () {
    $record = new Record('r');
    $date = new DateTimeImmutable('2024-06-01');

    $record->setUpdatedAt($date);

    expect($record->updatedAt)->toBe($date);
});

test('setData replaces all data', function () {
    $record = new Record('r', ['old' => 'data']);

    $record->setData(['new' => 'data']);

    expect($record->data)->toBe(['new' => 'data']);
});

test('deepMerge recursively merges arrays', function () {
    $result = Record::deepMerge(
        ['a' => ['b' => 1, 'c' => 2], 'd' => 3],
        ['a' => ['b' => 10, 'e' => 5], 'f' => 6],
    );

    expect($result)->toBe([
        'a' => ['b' => 10, 'c' => 2, 'e' => 5],
        'd' => 3,
        'f' => 6,
    ]);
});

test('deepMerge overwrites non-array values', function () {
    $result = Record::deepMerge(
        ['a' => 'string'],
        ['a' => ['nested' => true]],
    );

    expect($result)->toBe(['a' => ['nested' => true]]);
});
