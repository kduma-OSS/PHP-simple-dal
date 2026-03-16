<?php

declare(strict_types=1);

use KDuma\SimpleDAL\Adapter\Database\DatabaseAdapter;
use KDuma\SimpleDAL\Contracts\EntityDefinitionInterface;

beforeEach(function () {
    $pdo = new PDO('sqlite::memory:');
    $this->adapter = new DatabaseAdapter($pdo);
    $this->entityName = 'test_entity';

    // Initialize the entity tables so every test starts with a clean schema.
    $definition = new class('test_entity', false, true, true, ['status', 'meta.role']) implements EntityDefinitionInterface
    {
        public function __construct(
            public readonly string $name,
            public readonly bool $isSingleton,
            public readonly bool $hasAttachments,
            public readonly bool $hasTimestamps,
            public readonly array $indexedFields,
        ) {}
    };

    $this->adapter->initializeEntity($this->entityName, $definition);
});

// -----------------------------------------------------------------
//  SQLite-specific tests
// -----------------------------------------------------------------

test('rejects invalid entity names', function () {
    $this->adapter->writeRecord('test_entity; DROP TABLE users', 'id-1', ['x' => 1]);
})->throws(InvalidArgumentException::class);

test('rejects entity names with special characters', function () {
    $this->adapter->writeRecord('test-entity', 'id-1', ['x' => 1]);
})->throws(InvalidArgumentException::class);

test('purge entity allows re-initialization', function () {
    $this->adapter->writeRecord($this->entityName, 'rec-1', ['a' => 1]);
    $this->adapter->purgeEntity($this->entityName);

    $definition = new class('test_entity', false, true, true, []) implements EntityDefinitionInterface
    {
        public function __construct(
            public readonly string $name,
            public readonly bool $isSingleton,
            public readonly bool $hasAttachments,
            public readonly bool $hasTimestamps,
            public readonly array $indexedFields,
        ) {}
    };

    $this->adapter->initializeEntity($this->entityName, $definition);

    // Should work on fresh tables.
    $this->adapter->writeRecord($this->entityName, 'rec-2', ['b' => 2]);
    expect($this->adapter->readRecord($this->entityName, 'rec-2'))->toBe(['b' => 2]);
});

test('handles binary attachment content', function () {
    $this->adapter->writeRecord($this->entityName, 'rec-1', ['x' => 1]);

    $binaryData = random_bytes(256);
    $this->adapter->writeAttachment($this->entityName, 'rec-1', 'binary.dat', $binaryData);

    $stream = $this->adapter->readAttachment($this->entityName, 'rec-1', 'binary.dat');
    $content = stream_get_contents($stream);

    expect($content)->toBe($binaryData);
});

test('filter with or connector', function () {
    $this->adapter->writeRecord($this->entityName, 'u1', ['name' => 'Alice', 'age' => 30]);
    $this->adapter->writeRecord($this->entityName, 'u2', ['name' => 'Bob', 'age' => 25]);
    $this->adapter->writeRecord($this->entityName, 'u3', ['name' => 'Charlie', 'age' => 35]);

    $results = $this->adapter->findRecords($this->entityName, filters: [
        ['type' => 'and', 'field' => 'name', 'operator' => '=', 'value' => 'Alice'],
        ['type' => 'or', 'field' => 'name', 'operator' => '=', 'value' => 'Charlie'],
    ]);

    $ids = array_keys($results);
    sort($ids);

    expect($ids)->toBe(['u1', 'u3']);
});

test('preserves nested data structures in records', function () {
    $data = [
        'name' => 'Test',
        'tags' => ['a', 'b', 'c'],
        'meta' => ['nested' => ['deep' => true]],
    ];

    $this->adapter->writeRecord($this->entityName, 'nested-1', $data);
    $result = $this->adapter->readRecord($this->entityName, 'nested-1');

    expect($result)->toBe($data);
});
