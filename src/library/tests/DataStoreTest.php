<?php

declare(strict_types=1);

use KDuma\SimpleDAL\Adapter\Database\DatabaseAdapter;
use KDuma\SimpleDAL\Contracts\Exception\EntityNotFoundException;
use KDuma\SimpleDAL\DataStore;
use KDuma\SimpleDAL\Entity\CollectionEntityDefinition;
use KDuma\SimpleDAL\Entity\SingletonEntityDefinition;
use KDuma\SimpleDAL\Store\CollectionEntityStore;
use KDuma\SimpleDAL\Store\SingletonEntityStore;

function makeDataStore(): DataStore
{
    $pdo = new PDO('sqlite::memory:');
    $adapter = new DatabaseAdapter($pdo);

    return new DataStore($adapter, [
        new CollectionEntityDefinition(name: 'articles'),
        new SingletonEntityDefinition(name: 'settings'),
    ]);
}

test('collection returns CollectionEntityStore', function () {
    $store = makeDataStore();

    $collection = $store->collection('articles');

    expect($collection)->toBeInstanceOf(CollectionEntityStore::class);
});

test('singleton returns SingletonEntityStore', function () {
    $store = makeDataStore();

    $singleton = $store->singleton('settings');

    expect($singleton)->toBeInstanceOf(SingletonEntityStore::class);
});

test('collection throws when entity is singleton', function () {
    $store = makeDataStore();

    $store->collection('settings');
})->throws(EntityNotFoundException::class, 'singleton');

test('singleton throws when entity is collection', function () {
    $store = makeDataStore();

    $store->singleton('articles');
})->throws(EntityNotFoundException::class, 'collection');

test('collection returns cached store instance', function () {
    $store = makeDataStore();

    $a = $store->collection('articles');
    $b = $store->collection('articles');

    expect($a)->toBe($b);
});

test('singleton returns cached store instance', function () {
    $store = makeDataStore();

    $a = $store->singleton('settings');
    $b = $store->singleton('settings');

    expect($a)->toBe($b);
});

test('entities returns all registered definitions', function () {
    $store = makeDataStore();

    $entities = $store->entities();

    expect($entities)->toHaveCount(2);
    expect(array_keys($entities))->toBe(['articles', 'settings']);
});

test('hasEntity returns true for registered entity', function () {
    $store = makeDataStore();

    expect($store->hasEntity('articles'))->toBeTrue();
    expect($store->hasEntity('settings'))->toBeTrue();
});

test('hasEntity returns false for unregistered entity', function () {
    $store = makeDataStore();

    expect($store->hasEntity('nonexistent'))->toBeFalse();
});

test('collection throws for nonexistent entity', function () {
    $store = makeDataStore();

    $store->collection('nonexistent');
})->throws(EntityNotFoundException::class);
