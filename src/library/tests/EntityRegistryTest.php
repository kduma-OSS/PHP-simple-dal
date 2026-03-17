<?php

declare(strict_types=1);

use KDuma\SimpleDAL\Contracts\Exception\EntityNotFoundException;
use KDuma\SimpleDAL\Entity\CollectionEntityDefinition;
use KDuma\SimpleDAL\Entity\EntityRegistry;
use KDuma\SimpleDAL\Entity\SingletonEntityDefinition;

test('register and get round-trip', function () {
    $registry = new EntityRegistry;
    $def = new CollectionEntityDefinition(name: 'articles');

    $registry->register($def);

    expect($registry->get('articles'))->toBe($def);
});

test('get throws EntityNotFoundException for missing entity', function () {
    $registry = new EntityRegistry;

    $registry->get('nonexistent');
})->throws(EntityNotFoundException::class, 'Entity "nonexistent" is not registered.');

test('has returns true for registered entity', function () {
    $registry = new EntityRegistry;
    $registry->register(new CollectionEntityDefinition(name: 'articles'));

    expect($registry->has('articles'))->toBeTrue();
});

test('has returns false for unregistered entity', function () {
    $registry = new EntityRegistry;

    expect($registry->has('articles'))->toBeFalse();
});

test('all returns all registered entities', function () {
    $registry = new EntityRegistry;
    $articles = new CollectionEntityDefinition(name: 'articles');
    $settings = new SingletonEntityDefinition(name: 'settings');

    $registry->register($articles);
    $registry->register($settings);

    $all = $registry->all();

    expect($all)->toHaveCount(2);
    expect($all['articles'])->toBe($articles);
    expect($all['settings'])->toBe($settings);
});

test('isSingleton returns true for singleton entity', function () {
    $registry = new EntityRegistry;
    $registry->register(new SingletonEntityDefinition(name: 'settings'));

    expect($registry->isSingleton('settings'))->toBeTrue();
});

test('isSingleton returns false for collection entity', function () {
    $registry = new EntityRegistry;
    $registry->register(new CollectionEntityDefinition(name: 'articles'));

    expect($registry->isSingleton('articles'))->toBeFalse();
});

test('isCollection returns true for collection entity', function () {
    $registry = new EntityRegistry;
    $registry->register(new CollectionEntityDefinition(name: 'articles'));

    expect($registry->isCollection('articles'))->toBeTrue();
});

test('isCollection returns false for singleton entity', function () {
    $registry = new EntityRegistry;
    $registry->register(new SingletonEntityDefinition(name: 'settings'));

    expect($registry->isCollection('settings'))->toBeFalse();
});
