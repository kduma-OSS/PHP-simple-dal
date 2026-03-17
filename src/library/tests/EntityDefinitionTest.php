<?php

declare(strict_types=1);

use KDuma\SimpleDAL\Entity\CollectionEntityDefinition;
use KDuma\SimpleDAL\Entity\SingletonEntityDefinition;

test('CollectionEntityDefinition sets all properties', function () {
    $def = new CollectionEntityDefinition(
        name: 'articles',
        hasAttachments: true,
        hasTimestamps: false,
        idField: 'slug',
        indexedFields: ['title', 'status'],
    );

    expect($def->name)->toBe('articles');
    expect($def->hasAttachments)->toBeTrue();
    expect($def->hasTimestamps)->toBeFalse();
    expect($def->idField)->toBe('slug');
    expect($def->indexedFields)->toBe(['title', 'status']);
});

test('CollectionEntityDefinition isSingleton returns false', function () {
    $def = new CollectionEntityDefinition(name: 'items');

    expect($def->isSingleton)->toBeFalse();
});

test('CollectionEntityDefinition defaults', function () {
    $def = new CollectionEntityDefinition(name: 'items');

    expect($def->hasAttachments)->toBeTrue();
    expect($def->hasTimestamps)->toBeTrue();
    expect($def->idField)->toBeNull();
    expect($def->indexedFields)->toBeEmpty();
});

test('SingletonEntityDefinition sets all properties', function () {
    $def = new SingletonEntityDefinition(
        name: 'settings',
        hasAttachments: false,
        hasTimestamps: true,
    );

    expect($def->name)->toBe('settings');
    expect($def->hasAttachments)->toBeFalse();
    expect($def->hasTimestamps)->toBeTrue();
});

test('SingletonEntityDefinition isSingleton returns true', function () {
    $def = new SingletonEntityDefinition(name: 'config');

    expect($def->isSingleton)->toBeTrue();
});

test('SingletonEntityDefinition indexedFields returns empty array', function () {
    $def = new SingletonEntityDefinition(name: 'config');

    expect($def->indexedFields)->toBeEmpty();
});
