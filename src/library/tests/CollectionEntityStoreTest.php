<?php

declare(strict_types=1);

use KDuma\SimpleDAL\Adapter\Database\DatabaseAdapter;
use KDuma\SimpleDAL\Attachment\AttachmentStore;
use KDuma\SimpleDAL\Contracts\Exception\DuplicateRecordException;
use KDuma\SimpleDAL\Contracts\Exception\RecordNotFoundException;
use KDuma\SimpleDAL\Entity\CollectionEntityDefinition;
use KDuma\SimpleDAL\Query\Filter;
use KDuma\SimpleDAL\Store\CollectionEntityStore;

/**
 * @param  string[]  $indexedFields
 */
function makeCollectionStore(
    bool $hasTimestamps = true,
    bool $hasAttachments = true,
    ?string $idField = null,
    array $indexedFields = [],
): CollectionEntityStore {
    $pdo = new PDO('sqlite::memory:');
    $adapter = new DatabaseAdapter($pdo);
    $def = new CollectionEntityDefinition(
        name: 'items',
        hasAttachments: $hasAttachments,
        hasTimestamps: $hasTimestamps,
        idField: $idField,
        indexedFields: $indexedFields,
    );
    $adapter->initializeEntity('items', $def);

    return new CollectionEntityStore($adapter, $def);
}

test('name exposes entity name', function () {
    $store = makeCollectionStore();

    expect($store->name)->toBe('items');
});

test('create with auto-generated UUID', function () {
    $store = makeCollectionStore();

    $record = $store->create(['title' => 'Hello']);

    expect($record->id)->toMatch('/^[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/');
    expect($record->get('title'))->toBe('Hello');
});

test('create with custom ID', function () {
    $store = makeCollectionStore();

    $record = $store->create(['title' => 'Hello'], 'my-id');

    expect($record->id)->toBe('my-id');
});

test('create with idField extracts ID from data', function () {
    $store = makeCollectionStore(idField: 'slug');

    $record = $store->create(['slug' => 'hello-world', 'title' => 'Hello']);

    expect($record->id)->toBe('hello-world');
});

test('create with nested idField extracts ID from data', function () {
    $store = makeCollectionStore(idField: 'meta.slug');

    $record = $store->create(['meta' => ['slug' => 'nested-id'], 'title' => 'Hello']);

    expect($record->id)->toBe('nested-id');
});

test('create sets timestamps when enabled', function () {
    $store = makeCollectionStore(hasTimestamps: true);

    $record = $store->create(['title' => 'Hello']);

    expect($record->createdAt)->toBeInstanceOf(DateTimeImmutable::class);
    expect($record->updatedAt)->toBeInstanceOf(DateTimeImmutable::class);
});

test('create does not set timestamps when disabled', function () {
    $store = makeCollectionStore(hasTimestamps: false);

    $record = $store->create(['title' => 'Hello'], 'id-1');

    expect($record->createdAt)->toBeNull();
    expect($record->updatedAt)->toBeNull();
});

test('create throws DuplicateRecordException for existing ID', function () {
    $store = makeCollectionStore();

    $store->create(['title' => 'First'], 'dup-id');
    $store->create(['title' => 'Second'], 'dup-id');
})->throws(DuplicateRecordException::class);

test('create throws for invalid ID characters', function () {
    $store = makeCollectionStore();

    $store->create(['title' => 'Hello'], 'invalid id!');
})->throws(InvalidArgumentException::class, 'Invalid record ID');

test('find returns existing record', function () {
    $store = makeCollectionStore();
    $store->create(['title' => 'Hello'], 'rec-1');

    $record = $store->find('rec-1');

    expect($record->id)->toBe('rec-1');
    expect($record->get('title'))->toBe('Hello');
});

test('find throws RecordNotFoundException for missing record', function () {
    $store = makeCollectionStore();

    $store->find('nonexistent');
})->throws(RecordNotFoundException::class);

test('findOrNull returns record when found', function () {
    $store = makeCollectionStore();
    $store->create(['title' => 'Hello'], 'rec-1');

    $record = $store->findOrNull('rec-1');

    assert($record !== null);
    expect($record->id)->toBe('rec-1');
});

test('findOrNull returns null for missing record', function () {
    $store = makeCollectionStore();

    expect($store->findOrNull('nonexistent'))->toBeNull();
});

test('has returns true for existing record', function () {
    $store = makeCollectionStore();
    $store->create(['title' => 'Hello'], 'rec-1');

    expect($store->has('rec-1'))->toBeTrue();
});

test('has returns false for missing record', function () {
    $store = makeCollectionStore();

    expect($store->has('nonexistent'))->toBeFalse();
});

test('all returns all records', function () {
    $store = makeCollectionStore();
    $store->create(['title' => 'A'], 'a');
    $store->create(['title' => 'B'], 'b');

    $all = $store->all();

    expect($all)->toHaveCount(2);
});

test('filter returns matching records', function () {
    $store = makeCollectionStore(indexedFields: ['status']);
    $store->create(['title' => 'A', 'status' => 'active'], 'a');
    $store->create(['title' => 'B', 'status' => 'inactive'], 'b');
    $store->create(['title' => 'C', 'status' => 'active'], 'c');

    $filter = Filter::where('status', '=', 'active');
    $results = $store->filter($filter);

    expect($results)->toHaveCount(2);
});

test('save preserves createdAt and updates updatedAt', function () {
    $store = makeCollectionStore(hasTimestamps: true);
    $original = $store->create(['title' => 'Hello'], 'rec-1');

    expect($original->createdAt)->not->toBeNull();
    expect($original->updatedAt)->not->toBeNull();

    $originalCreatedAt = $original->createdAt;
    assert($originalCreatedAt instanceof DateTimeImmutable);

    $original->set('title', 'Updated');
    $saved = $store->save($original);

    assert($saved->createdAt instanceof DateTimeImmutable);
    expect($saved->createdAt->format('Y-m-d'))->toBe($originalCreatedAt->format('Y-m-d'));
    expect($saved->updatedAt)->toBeInstanceOf(DateTimeImmutable::class);
});

test('update deep merges data', function () {
    $store = makeCollectionStore();
    $store->create(['title' => 'Hello', 'meta' => ['a' => 1, 'b' => 2]], 'rec-1');

    $updated = $store->update('rec-1', ['meta' => ['b' => 20, 'c' => 3]]);

    expect($updated->get('title'))->toBe('Hello');
    expect($updated->get('meta.a'))->toBe(1);
    expect($updated->get('meta.b'))->toBe(20);
    expect($updated->get('meta.c'))->toBe(3);
});

test('replace overwrites data but preserves createdAt', function () {
    $store = makeCollectionStore(hasTimestamps: true);
    $original = $store->create(['title' => 'Hello', 'extra' => 'value'], 'rec-1');

    assert($original->createdAt instanceof DateTimeImmutable);
    $originalCreatedAt = $original->createdAt;

    $replaced = $store->replace('rec-1', ['title' => 'Replaced']);

    expect($replaced->get('title'))->toBe('Replaced');
    expect($replaced->has('extra'))->toBeFalse();
    assert($replaced->createdAt instanceof DateTimeImmutable);
    expect($replaced->createdAt->format('Y-m-d'))->toBe($originalCreatedAt->format('Y-m-d'));
});

test('delete removes existing record', function () {
    $store = makeCollectionStore();
    $store->create(['title' => 'Hello'], 'rec-1');

    $store->delete('rec-1');

    expect($store->has('rec-1'))->toBeFalse();
});

test('delete throws RecordNotFoundException for missing record', function () {
    $store = makeCollectionStore();

    $store->delete('nonexistent');
})->throws(RecordNotFoundException::class);

test('count returns total number of records', function () {
    $store = makeCollectionStore();
    $store->create(['title' => 'A'], 'a');
    $store->create(['title' => 'B'], 'b');
    $store->create(['title' => 'C'], 'c');

    expect($store->count())->toBe(3);
});

test('count with filter returns filtered count', function () {
    $store = makeCollectionStore(indexedFields: ['status']);
    $store->create(['title' => 'A', 'status' => 'active'], 'a');
    $store->create(['title' => 'B', 'status' => 'inactive'], 'b');

    $filter = Filter::where('status', '=', 'active');

    expect($store->count($filter))->toBe(1);
});

test('attachments returns AttachmentStore', function () {
    $store = makeCollectionStore();
    $store->create(['title' => 'Hello'], 'rec-1');

    $attachments = $store->attachments('rec-1');

    expect($attachments)->toBeInstanceOf(AttachmentStore::class);
});

test('record data does not include meta fields', function () {
    $store = makeCollectionStore(hasTimestamps: true);
    $record = $store->create(['title' => 'Hello'], 'rec-1');

    expect($record->has('_createdAt'))->toBeFalse();
    expect($record->has('_updatedAt'))->toBeFalse();
});
