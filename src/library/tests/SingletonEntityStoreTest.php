<?php

declare(strict_types=1);

use KDuma\SimpleDAL\Adapter\Database\DatabaseAdapter;
use KDuma\SimpleDAL\Attachment\AttachmentStore;
use KDuma\SimpleDAL\Contracts\Exception\RecordNotFoundException;
use KDuma\SimpleDAL\Entity\SingletonEntityDefinition;
use KDuma\SimpleDAL\Store\SingletonEntityStore;

function makeSingletonStore(bool $hasTimestamps = true): SingletonEntityStore
{
    $pdo = new PDO('sqlite::memory:');
    $adapter = new DatabaseAdapter($pdo);
    $def = new SingletonEntityDefinition(
        name: 'settings',
        hasTimestamps: $hasTimestamps,
    );
    $adapter->initializeEntity('settings', $def);

    return new SingletonEntityStore($adapter, $def);
}

test('name exposes entity name', function () {
    $store = makeSingletonStore();

    expect($store->name)->toBe('settings');
});

test('set creates singleton record', function () {
    $store = makeSingletonStore();

    $record = $store->set(['theme' => 'dark']);

    expect($record->get('theme'))->toBe('dark');
});

test('get retrieves singleton record', function () {
    $store = makeSingletonStore();
    $store->set(['theme' => 'dark']);

    $record = $store->get();

    expect($record->get('theme'))->toBe('dark');
});

test('get throws when singleton not set', function () {
    $store = makeSingletonStore();

    $store->get();
})->throws(RecordNotFoundException::class);

test('getOrNull returns null when singleton not set', function () {
    $store = makeSingletonStore();

    expect($store->getOrNull())->toBeNull();
});

test('getOrNull returns record when set', function () {
    $store = makeSingletonStore();
    $store->set(['theme' => 'dark']);

    $record = $store->getOrNull();

    assert($record !== null);
    expect($record->get('theme'))->toBe('dark');
});

test('exists returns false when not set', function () {
    $store = makeSingletonStore();

    expect($store->exists())->toBeFalse();
});

test('exists returns true when set', function () {
    $store = makeSingletonStore();
    $store->set(['theme' => 'dark']);

    expect($store->exists())->toBeTrue();
});

test('set replaces existing data', function () {
    $store = makeSingletonStore();
    $store->set(['theme' => 'dark', 'lang' => 'en']);
    $store->set(['theme' => 'light']);

    $record = $store->get();

    expect($record->get('theme'))->toBe('light');
    expect($record->has('lang'))->toBeFalse();
});

test('save persists modified record', function () {
    $store = makeSingletonStore();
    $record = $store->set(['theme' => 'dark']);

    $record->set('theme', 'light');
    $saved = $store->save($record);

    expect($saved->get('theme'))->toBe('light');
    expect($store->get()->get('theme'))->toBe('light');
});

test('update deep merges data', function () {
    $store = makeSingletonStore();
    $store->set(['theme' => 'dark', 'notifications' => ['email' => true, 'sms' => false]]);

    $updated = $store->update(['notifications' => ['sms' => true, 'push' => true]]);

    expect($updated->get('theme'))->toBe('dark');
    expect($updated->get('notifications.email'))->toBeTrue();
    expect($updated->get('notifications.sms'))->toBeTrue();
    expect($updated->get('notifications.push'))->toBeTrue();
});

test('delete removes singleton', function () {
    $store = makeSingletonStore();
    $store->set(['theme' => 'dark']);

    $store->delete();

    expect($store->exists())->toBeFalse();
});

test('delete when not set does nothing', function () {
    $store = makeSingletonStore();

    $store->delete();

    expect($store->exists())->toBeFalse();
});

test('attachments returns attachment store', function () {
    $store = makeSingletonStore();
    $store->set(['theme' => 'dark']);

    $attachments = $store->attachments();

    expect($attachments)->toBeInstanceOf(AttachmentStore::class);
});
