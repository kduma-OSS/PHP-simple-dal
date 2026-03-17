<?php

declare(strict_types=1);

use KDuma\SimpleDAL\Adapter\Database\DatabaseAdapter;
use KDuma\SimpleDAL\Contracts\EntityDefinitionInterface;
use KDuma\SimpleDAL\DataIntegrity\Contracts\HashingAlgorithmInterface;
use KDuma\SimpleDAL\DataIntegrity\Contracts\SigningAlgorithmInterface;
use KDuma\SimpleDAL\DataIntegrity\IntegrityConfig;
use KDuma\SimpleDAL\DataIntegrity\IntegrityMigrator;
use KDuma\SimpleDAL\DataIntegrity\IntegrityPayload;
use KDuma\SimpleDAL\DataIntegrity\IntegrityStorageAdapter;

beforeEach(function () {
    $this->pdo = new PDO('sqlite::memory:');
    $this->adapter = new DatabaseAdapter($this->pdo);

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

    $this->signerB = new class implements SigningAlgorithmInterface
    {
        public string $id {
            get => 'signer-b';
        }

        public int $algorithm {
            get => 0x11;
        }

        public function sign(string $message): string
        {
            return hash_hmac('sha256', $message, 'different-secret', binary: true);
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

    $this->adapter->initializeEntity('test_entity', $definition);
});

test('adds integrity to unprotected records', function () {
    // Write record without integrity
    $this->adapter->writeRecord('test_entity', 'rec-1', ['name' => 'Alice']);

    $config = new IntegrityConfig(hasher: $this->hasher);

    (new IntegrityMigrator($this->adapter, $config))->migrate(['test_entity']);

    $raw = $this->adapter->readRecord('test_entity', 'rec-1');
    expect($raw)->toHaveKey('_integrity');
    expect($raw['_integrity'])->toHaveKey('hash');
    expect($raw['_integrity'])->toHaveKey('algorithm');

    // Verify the integrity adapter can read it
    $adapter = new IntegrityStorageAdapter($this->adapter, $config);
    $data = $adapter->readRecord('test_entity', 'rec-1');
    expect($data)->toBe(['name' => 'Alice']);
});

test('adds integrity to unprotected attachments', function () {
    $this->adapter->writeRecord('test_entity', 'rec-1', ['x' => 1]);
    $this->adapter->writeAttachment('test_entity', 'rec-1', 'file.txt', 'plaintext');

    $config = new IntegrityConfig(hasher: $this->hasher);

    (new IntegrityMigrator($this->adapter, $config))->migrate(['test_entity']);

    $stream = $this->adapter->readAttachment('test_entity', 'rec-1', 'file.txt');
    $rawData = stream_get_contents($stream);

    expect(IntegrityPayload::hasIntegrity($rawData))->toBeTrue();

    // Verify the integrity adapter can read it
    $adapter = new IntegrityStorageAdapter($this->adapter, $config);
    $result = $adapter->readAttachment('test_entity', 'rec-1', 'file.txt');
    expect(stream_get_contents($result))->toBe('plaintext');
});

test('skips already-correct data', function () {
    $config = new IntegrityConfig(hasher: $this->hasher);

    // Write via integrity adapter (already correct)
    $adapter = new IntegrityStorageAdapter($this->adapter, $config);
    $adapter->writeRecord('test_entity', 'rec-1', ['name' => 'Alice']);
    $this->adapter->writeRecord('test_entity', 'rec-1-noop', ['x' => 1]);
    $adapter->writeAttachment('test_entity', 'rec-1-noop', 'file.txt', 'content');

    // Capture raw state before migration
    $rawRecordBefore = $this->adapter->readRecord('test_entity', 'rec-1');
    $rawAttachmentBefore = stream_get_contents(
        $this->adapter->readAttachment('test_entity', 'rec-1-noop', 'file.txt'),
    );

    // Migrate — should be a no-op for already-correct data
    (new IntegrityMigrator($this->adapter, $config))->migrate(['test_entity']);

    $rawRecordAfter = $this->adapter->readRecord('test_entity', 'rec-1');
    $rawAttachmentAfter = stream_get_contents(
        $this->adapter->readAttachment('test_entity', 'rec-1-noop', 'file.txt'),
    );

    expect($rawRecordAfter)->toBe($rawRecordBefore);
    expect($rawAttachmentAfter)->toBe($rawAttachmentBefore);
});

test('re-signs with different config', function () {
    $configA = new IntegrityConfig(hasher: $this->hasher, signer: $this->signer);

    // Write with signer A
    $adapter = new IntegrityStorageAdapter($this->adapter, $configA);
    $adapter->writeRecord('test_entity', 'rec-1', ['name' => 'Alice']);
    $adapter->writeAttachment('test_entity', 'rec-1', 'file.txt', 'content');

    $rawBefore = $this->adapter->readRecord('test_entity', 'rec-1');
    expect($rawBefore['_integrity']['key_id'])->toBe('test-signer');

    // Migrate to signer B
    $configB = new IntegrityConfig(hasher: $this->hasher, signer: $this->signerB);
    (new IntegrityMigrator($this->adapter, $configB))->migrate(['test_entity']);

    $rawAfter = $this->adapter->readRecord('test_entity', 'rec-1');
    expect($rawAfter['_integrity']['key_id'])->toBe('signer-b');

    // Verify attachment was also re-signed
    $stream = $this->adapter->readAttachment('test_entity', 'rec-1', 'file.txt');
    $rawAttachment = stream_get_contents($stream);
    $payload = IntegrityPayload::decode($rawAttachment);
    expect($payload->keyId)->toBe('signer-b');

    // Verify integrity adapter with new config can read it
    $adapterB = new IntegrityStorageAdapter($this->adapter, $configB);
    $data = $adapterB->readRecord('test_entity', 'rec-1');
    expect($data)->toBe(['name' => 'Alice']);

    $result = $adapterB->readAttachment('test_entity', 'rec-1', 'file.txt');
    expect(stream_get_contents($result))->toBe('content');
});
