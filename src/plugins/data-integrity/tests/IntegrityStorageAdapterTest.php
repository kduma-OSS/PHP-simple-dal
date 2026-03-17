<?php

declare(strict_types=1);

use KDuma\SimpleDAL\Adapter\Database\DatabaseAdapter;
use KDuma\SimpleDAL\Contracts\EntityDefinitionInterface;
use KDuma\SimpleDAL\DataIntegrity\Contracts\HashingAlgorithmInterface;
use KDuma\SimpleDAL\DataIntegrity\Contracts\SigningAlgorithmInterface;
use KDuma\SimpleDAL\DataIntegrity\Exception\IntegrityException;
use KDuma\SimpleDAL\DataIntegrity\FailureMode;
use KDuma\SimpleDAL\DataIntegrity\IntegrityConfig;
use KDuma\SimpleDAL\DataIntegrity\IntegrityPayload;
use KDuma\SimpleDAL\DataIntegrity\IntegrityStorageAdapter;

beforeEach(function () {
    $this->pdo = new PDO('sqlite::memory:');
    $this->inner = new DatabaseAdapter($this->pdo);

    $this->hasher = new class implements HashingAlgorithmInterface
    {
        public int $algorithm {
            get => 0x01;
        }

        public function hash(string $data): string
        {
            return hash('sha256', $data, binary: true);
        }
    };

    $this->signer = new class implements SigningAlgorithmInterface
    {
        public string $id {
            get => 'test-signer';
        }

        public int $algorithm {
            get => 0x10;
        }

        public function sign(string $message): string
        {
            return hash_hmac('sha256', $message, 'test-secret', binary: true);
        }

        public function verify(string $message, string $signature): bool
        {
            return hash_equals($this->sign($message), $signature);
        }
    };

    $definition = new class('test_entity', false, true, false, []) implements EntityDefinitionInterface
    {
        public function __construct(
            public readonly string $name,
            public readonly bool $isSingleton,
            public readonly bool $hasAttachments,
            public readonly bool $hasTimestamps,
            public readonly array $indexedFields,
        ) {}
    };

    $this->inner->initializeEntity('test_entity', $definition);
});

test('write and read record round-trip with checksum only', function () {
    $adapter = new IntegrityStorageAdapter($this->inner, new IntegrityConfig(
        hasher: $this->hasher,
    ));

    $adapter->writeRecord('test_entity', 'rec-1', ['name' => 'Alice', 'age' => 30]);
    $data = $adapter->readRecord('test_entity', 'rec-1');

    expect($data)->toBe(['name' => 'Alice', 'age' => 30]);
});

test('write and read record round-trip with checksum and signature', function () {
    $adapter = new IntegrityStorageAdapter($this->inner, new IntegrityConfig(
        hasher: $this->hasher,
        signer: $this->signer,
    ));

    $adapter->writeRecord('test_entity', 'rec-1', ['name' => 'Bob', 'role' => 'admin']);
    $data = $adapter->readRecord('test_entity', 'rec-1');

    expect($data)->toBe(['name' => 'Bob', 'role' => 'admin']);
});

test('record integrity metadata is transparent', function () {
    $adapter = new IntegrityStorageAdapter($this->inner, new IntegrityConfig(
        hasher: $this->hasher,
    ));

    $original = ['z' => 3, 'a' => 1, 'nested' => ['b' => 2, 'a' => 1]];
    $adapter->writeRecord('test_entity', 'rec-1', $original);
    $data = $adapter->readRecord('test_entity', 'rec-1');

    expect($data)->toBe($original);
});

test('raw record contains _integrity metadata', function () {
    $adapter = new IntegrityStorageAdapter($this->inner, new IntegrityConfig(
        hasher: $this->hasher,
    ));

    $adapter->writeRecord('test_entity', 'rec-1', ['x' => 1]);

    // Read raw from inner adapter
    $raw = $this->inner->readRecord('test_entity', 'rec-1');

    expect($raw)->toHaveKey('_integrity');
    expect($raw['_integrity'])->toHaveKey('algorithm');
    expect($raw['_integrity'])->toHaveKey('hash');
});

test('tampered record data triggers IntegrityException in Throw mode', function () {
    $adapter = new IntegrityStorageAdapter($this->inner, new IntegrityConfig(
        hasher: $this->hasher,
    ));

    $adapter->writeRecord('test_entity', 'rec-1', ['x' => 1]);

    // Tamper with data directly
    $raw = $this->inner->readRecord('test_entity', 'rec-1');
    $raw['x'] = 999;
    $this->inner->writeRecord('test_entity', 'rec-1', $raw);

    $adapter->readRecord('test_entity', 'rec-1');
})->throws(IntegrityException::class);

test('tampered record data passes silently in Ignore mode', function () {
    $adapter = new IntegrityStorageAdapter($this->inner, new IntegrityConfig(
        hasher: $this->hasher,
        onChecksumFailure: FailureMode::Ignore,
    ));

    $adapter->writeRecord('test_entity', 'rec-1', ['x' => 1]);

    // Tamper with data directly
    $raw = $this->inner->readRecord('test_entity', 'rec-1');
    $raw['x'] = 999;
    $this->inner->writeRecord('test_entity', 'rec-1', $raw);

    $data = $adapter->readRecord('test_entity', 'rec-1');

    expect($data)->toBe(['x' => 999]);
});

test('write and read attachment round-trip with checksum', function () {
    $adapter = new IntegrityStorageAdapter($this->inner, new IntegrityConfig(
        hasher: $this->hasher,
    ));

    $this->inner->writeRecord('test_entity', 'rec-1', ['x' => 1]);
    $adapter->writeAttachment('test_entity', 'rec-1', 'file.txt', 'hello world');
    $stream = $adapter->readAttachment('test_entity', 'rec-1', 'file.txt');

    expect(stream_get_contents($stream))->toBe('hello world');
});

test('raw attachment contains integrity header', function () {
    $adapter = new IntegrityStorageAdapter($this->inner, new IntegrityConfig(
        hasher: $this->hasher,
    ));

    $this->inner->writeRecord('test_entity', 'rec-1', ['x' => 1]);
    $adapter->writeAttachment('test_entity', 'rec-1', 'file.txt', 'content');

    // Read raw from inner adapter
    $stream = $this->inner->readAttachment('test_entity', 'rec-1', 'file.txt');
    $rawData = stream_get_contents($stream);

    expect(IntegrityPayload::hasIntegrity($rawData))->toBeTrue();
});

test('tampered attachment triggers IntegrityException', function () {
    $adapter = new IntegrityStorageAdapter($this->inner, new IntegrityConfig(
        hasher: $this->hasher,
    ));

    $this->inner->writeRecord('test_entity', 'rec-1', ['x' => 1]);
    $adapter->writeAttachment('test_entity', 'rec-1', 'file.txt', 'original');

    // Read raw, tamper with content portion
    $stream = $this->inner->readAttachment('test_entity', 'rec-1', 'file.txt');
    $rawData = stream_get_contents($stream);
    $payload = IntegrityPayload::decode($rawData);

    // Re-encode with tampered content but same hash
    $tampered = IntegrityPayload::encode(
        'tampered content',
        hash: $payload->hash,
        hashAlgorithm: $payload->hashAlgorithm,
    );
    $this->inner->writeAttachment('test_entity', 'rec-1', 'file.txt', $tampered);

    $adapter->readAttachment('test_entity', 'rec-1', 'file.txt');
})->throws(IntegrityException::class);

test('non-integrity data passes through as legacy data', function () {
    $adapter = new IntegrityStorageAdapter($this->inner, new IntegrityConfig(
        hasher: $this->hasher,
    ));

    // Write plaintext directly via inner adapter (legacy)
    $this->inner->writeRecord('test_entity', 'rec-1', ['x' => 1]);
    $this->inner->writeAttachment('test_entity', 'rec-1', 'legacy.txt', 'plain data');

    $stream = $adapter->readAttachment('test_entity', 'rec-1', 'legacy.txt');

    expect(stream_get_contents($stream))->toBe('plain data');
});

test('non-integrity record data passes through as legacy data', function () {
    $adapter = new IntegrityStorageAdapter($this->inner, new IntegrityConfig(
        hasher: $this->hasher,
    ));

    // Write directly via inner adapter (legacy — no _integrity)
    $this->inner->writeRecord('test_entity', 'rec-1', ['x' => 1]);
    $data = $adapter->readRecord('test_entity', 'rec-1');

    expect($data)->toBe(['x' => 1]);
});

test('findRecords results have _integrity stripped', function () {
    $adapter = new IntegrityStorageAdapter($this->inner, new IntegrityConfig(
        hasher: $this->hasher,
    ));

    $adapter->writeRecord('test_entity', 'rec-1', ['name' => 'Alice']);
    $adapter->writeRecord('test_entity', 'rec-2', ['name' => 'Bob']);

    $results = $adapter->findRecords('test_entity');

    foreach ($results as $data) {
        expect($data)->not->toHaveKey('_integrity');
        expect($data)->toHaveKey('name');
    }

    expect($results)->toHaveCount(2);
});

test('stream input to writeAttachment works', function () {
    $adapter = new IntegrityStorageAdapter($this->inner, new IntegrityConfig(
        hasher: $this->hasher,
    ));

    $this->inner->writeRecord('test_entity', 'rec-1', ['x' => 1]);

    $stream = fopen('php://memory', 'r+');
    fwrite($stream, 'stream content');
    rewind($stream);

    $adapter->writeAttachment('test_entity', 'rec-1', 'file.txt', $stream);
    fclose($stream);

    $result = $adapter->readAttachment('test_entity', 'rec-1', 'file.txt');

    expect(stream_get_contents($result))->toBe('stream content');
});

test('sign-only mode: write and read record round-trip', function () {
    $adapter = new IntegrityStorageAdapter($this->inner, new IntegrityConfig(
        signer: $this->signer,
    ));

    $adapter->writeRecord('test_entity', 'rec-1', ['name' => 'Alice']);
    $data = $adapter->readRecord('test_entity', 'rec-1');

    expect($data)->toBe(['name' => 'Alice']);

    // Raw record should have signature but no hash
    $raw = $this->inner->readRecord('test_entity', 'rec-1');
    expect($raw['_integrity'])->toHaveKey('signature');
    expect($raw['_integrity'])->toHaveKey('key_id');
    expect($raw['_integrity'])->not->toHaveKey('hash');
});

test('sign-only mode: write and read attachment round-trip', function () {
    $adapter = new IntegrityStorageAdapter($this->inner, new IntegrityConfig(
        signer: $this->signer,
    ));

    $this->inner->writeRecord('test_entity', 'rec-1', ['x' => 1]);
    $adapter->writeAttachment('test_entity', 'rec-1', 'file.txt', 'signed content');
    $stream = $adapter->readAttachment('test_entity', 'rec-1', 'file.txt');

    expect(stream_get_contents($stream))->toBe('signed content');
});

test('sign-only mode: tampered record triggers IntegrityException', function () {
    $adapter = new IntegrityStorageAdapter($this->inner, new IntegrityConfig(
        signer: $this->signer,
    ));

    $adapter->writeRecord('test_entity', 'rec-1', ['x' => 1]);

    $raw = $this->inner->readRecord('test_entity', 'rec-1');
    $raw['x'] = 999;
    $this->inner->writeRecord('test_entity', 'rec-1', $raw);

    $adapter->readRecord('test_entity', 'rec-1');
})->throws(IntegrityException::class);
