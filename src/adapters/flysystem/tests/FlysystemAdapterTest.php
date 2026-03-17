<?php

declare(strict_types=1);

use KDuma\SimpleDAL\Adapter\Flysystem\FlysystemAdapter;
use KDuma\SimpleDAL\Contracts\EntityDefinitionInterface;
use League\Flysystem\Filesystem;
use League\Flysystem\Local\LocalFilesystemAdapter;

beforeEach(function () {
    $this->tempDir = sys_get_temp_dir().'/simple-dal-test-'.uniqid();
    mkdir($this->tempDir, 0777, true);

    $flysystem = new Filesystem(new LocalFilesystemAdapter($this->tempDir));

    $this->adapter = new FlysystemAdapter($flysystem);
    $this->entityName = 'test_entity';

    $definition = new class('test_entity', false, true, false, ['status', 'meta.role']) implements EntityDefinitionInterface
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

afterEach(function () {
    if (is_dir($this->tempDir)) {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->tempDir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($iterator as $file) {
            if ($file->isDir()) {
                rmdir($file->getPathname());
            } else {
                unlink($file->getPathname());
            }
        }

        rmdir($this->tempDir);
    }
});

// ---------------------------------------------------------------
//  Flysystem-specific tests
// ---------------------------------------------------------------

test('record data.json exists in the record directory', function () {
    $this->adapter->writeRecord('test_entity', 'rec-1', ['name' => 'Alice']);

    expect(file_exists($this->tempDir.'/test_entity/rec-1/data.json'))->toBeTrue();
});

test('data.json is pretty-printed with sorted keys', function () {
    $this->adapter->writeRecord('test_entity', 'rec-1', [
        'zebra' => 1,
        'alpha' => 2,
        'meta' => ['z_key' => 'z', 'a_key' => 'a'],
    ]);

    $raw = file_get_contents($this->tempDir.'/test_entity/rec-1/data.json');
    $decoded = json_decode($raw, true);

    // Keys should be sorted alphabetically at both levels
    expect(array_keys($decoded))->toBe(['alpha', 'meta', 'zebra']);
    expect(array_keys($decoded['meta']))->toBe(['a_key', 'z_key']);

    // Should be pretty-printed (contains newlines)
    expect($raw)->toContain("\n");
});

test('attachments are stored alongside data.json', function () {
    $this->adapter->writeRecord('test_entity', 'rec-1', ['x' => 1]);
    $this->adapter->writeAttachment('test_entity', 'rec-1', 'certificate.pem', 'CERT_DATA');

    expect(file_exists($this->tempDir.'/test_entity/rec-1/data.json'))->toBeTrue();
    expect(file_exists($this->tempDir.'/test_entity/rec-1/certificate.pem'))->toBeTrue();
    expect(file_get_contents($this->tempDir.'/test_entity/rec-1/certificate.pem'))->toBe('CERT_DATA');
});

test('index file is created and maintained', function () {
    $this->adapter->writeRecord('test_entity', 'rec-1', [
        'name' => 'Alice',
        'status' => 'active',
        'meta' => ['role' => 'admin'],
    ]);

    $indexPath = $this->tempDir.'/test_entity/_index.json';
    expect(file_exists($indexPath))->toBeTrue();

    $index = json_decode(file_get_contents($indexPath), true);

    expect($index)->toHaveKey('status');
    expect($index['status'])->toHaveKey('active');
    expect($index['status']['active'])->toContain('rec-1');

    expect($index)->toHaveKey('meta.role');
    expect($index['meta.role'])->toHaveKey('admin');
    expect($index['meta.role']['admin'])->toContain('rec-1');
});

test('index is updated when a record is overwritten', function () {
    $this->adapter->writeRecord('test_entity', 'rec-1', [
        'status' => 'active',
        'meta' => ['role' => 'admin'],
    ]);

    $this->adapter->writeRecord('test_entity', 'rec-1', [
        'status' => 'inactive',
        'meta' => ['role' => 'user'],
    ]);

    $index = json_decode(file_get_contents($this->tempDir.'/test_entity/_index.json'), true);

    // Old value should be gone
    expect($index['status'])->not->toHaveKey('active');

    // New value should be present
    expect($index['status'])->toHaveKey('inactive');
    expect($index['status']['inactive'])->toContain('rec-1');
});

test('index is updated when a record is deleted', function () {
    $this->adapter->writeRecord('test_entity', 'rec-1', [
        'status' => 'active',
        'meta' => ['role' => 'admin'],
    ]);
    $this->adapter->writeRecord('test_entity', 'rec-2', [
        'status' => 'active',
        'meta' => ['role' => 'user'],
    ]);

    $this->adapter->deleteRecord('test_entity', 'rec-1');

    $index = json_decode(file_get_contents($this->tempDir.'/test_entity/_index.json'), true);

    // rec-1 should be removed from the active index
    expect($index['status']['active'])->not->toContain('rec-1');
    expect($index['status']['active'])->toContain('rec-2');
});

test('index file is removed on purge', function () {
    $this->adapter->writeRecord('test_entity', 'rec-1', [
        'status' => 'active',
        'meta' => ['role' => 'admin'],
    ]);

    $indexPath = $this->tempDir.'/test_entity/_index.json';
    expect(file_exists($indexPath))->toBeTrue();

    $this->adapter->purgeEntity('test_entity');

    expect(file_exists($indexPath))->toBeFalse();
});

test('directory layout follows entity/id/data.json convention', function () {
    $this->adapter->writeRecord('test_entity', 'my-record', ['key' => 'value']);

    $expectedDir = $this->tempDir.'/test_entity/my-record';
    expect(is_dir($expectedDir))->toBeTrue();
    expect(file_exists($expectedDir.'/data.json'))->toBeTrue();
});

test('listRecordIds excludes underscore-prefixed entries', function () {
    $this->adapter->writeRecord('test_entity', 'rec-1', ['status' => 'active', 'meta' => ['role' => 'admin']]);
    $this->adapter->writeRecord('test_entity', 'rec-2', ['status' => 'active', 'meta' => ['role' => 'user']]);

    $ids = $this->adapter->listRecordIds('test_entity');

    // Should not include _index.json or any _-prefixed directory
    expect($ids)->not->toContain('_index.json');
    expect($ids)->not->toContain('_singleton');

    sort($ids);
    expect($ids)->toBe(['rec-1', 'rec-2']);
});
