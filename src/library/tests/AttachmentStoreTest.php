<?php

declare(strict_types=1);

use KDuma\SimpleDAL\Adapter\Database\DatabaseAdapter;
use KDuma\SimpleDAL\Attachment\Attachment;
use KDuma\SimpleDAL\Attachment\AttachmentStore;
use KDuma\SimpleDAL\Contracts\Exception\AttachmentNotFoundException;
use KDuma\SimpleDAL\Entity\CollectionEntityDefinition;

function makeAttachmentStore(): AttachmentStore
{
    $pdo = new PDO('sqlite::memory:');
    $adapter = new DatabaseAdapter($pdo);
    $def = new CollectionEntityDefinition(name: 'items', hasAttachments: true);
    $adapter->initializeEntity('items', $def);
    $adapter->writeRecord('items', 'rec-1', ['title' => 'test']);

    return new AttachmentStore($adapter, 'items', 'rec-1');
}

test('put stores string content and returns Attachment', function () {
    $store = makeAttachmentStore();

    $attachment = $store->put('file.txt', 'hello world', 'text/plain');

    expect($attachment)->toBeInstanceOf(Attachment::class);
    expect($attachment->name)->toBe('file.txt');
    expect($attachment->mimeType)->toBe('text/plain');
    expect($attachment->size)->toBe(11);
    expect($attachment->contents())->toBe('hello world');
});

test('putStream stores stream content', function () {
    $store = makeAttachmentStore();
    $stream = fopen('php://memory', 'r+');
    assert(is_resource($stream));
    fwrite($stream, 'stream data');
    rewind($stream);

    $attachment = $store->putStream('file.bin', $stream, 'application/octet-stream');

    expect($attachment->name)->toBe('file.bin');
    expect($attachment->size)->toBeNull();
    expect($attachment->contents())->toBe('stream data');
});

test('get returns existing attachment', function () {
    $store = makeAttachmentStore();
    $store->put('file.txt', 'hello');

    $attachment = $store->get('file.txt');

    expect($attachment->name)->toBe('file.txt');
    expect($attachment->contents())->toBe('hello');
});

test('get throws AttachmentNotFoundException for missing attachment', function () {
    $store = makeAttachmentStore();

    $store->get('nonexistent.txt');
})->throws(AttachmentNotFoundException::class);

test('getOrNull returns attachment when found', function () {
    $store = makeAttachmentStore();
    $store->put('file.txt', 'hello');

    $attachment = $store->getOrNull('file.txt');

    assert($attachment !== null);
    expect($attachment->name)->toBe('file.txt');
});

test('getOrNull returns null for missing attachment', function () {
    $store = makeAttachmentStore();

    expect($store->getOrNull('nonexistent.txt'))->toBeNull();
});

test('has returns true for existing attachment', function () {
    $store = makeAttachmentStore();
    $store->put('file.txt', 'hello');

    expect($store->has('file.txt'))->toBeTrue();
});

test('has returns false for missing attachment', function () {
    $store = makeAttachmentStore();

    expect($store->has('nonexistent.txt'))->toBeFalse();
});

test('list returns all attachments', function () {
    $store = makeAttachmentStore();
    $store->put('a.txt', 'aaa');
    $store->put('b.txt', 'bbb');

    $list = $store->list();

    expect($list)->toHaveCount(2);
    expect($list[0])->toBeInstanceOf(Attachment::class);

    $names = array_map(fn ($a) => $a->name, $list);
    expect($names)->toContain('a.txt');
    expect($names)->toContain('b.txt');
});

test('list returns empty array when no attachments', function () {
    $store = makeAttachmentStore();

    expect($store->list())->toBeEmpty();
});

test('delete removes existing attachment', function () {
    $store = makeAttachmentStore();
    $store->put('file.txt', 'hello');

    $store->delete('file.txt');

    expect($store->has('file.txt'))->toBeFalse();
});

test('delete throws AttachmentNotFoundException for missing attachment', function () {
    $store = makeAttachmentStore();

    $store->delete('nonexistent.txt');
})->throws(AttachmentNotFoundException::class);

test('deleteAll removes all attachments', function () {
    $store = makeAttachmentStore();
    $store->put('a.txt', 'aaa');
    $store->put('b.txt', 'bbb');

    $store->deleteAll();

    expect($store->list())->toBeEmpty();
});

test('attachment stream returns readable resource', function () {
    $store = makeAttachmentStore();
    $store->put('file.txt', 'hello');

    $attachment = $store->get('file.txt');
    $stream = $attachment->stream();

    expect(is_resource($stream))->toBeTrue();
    expect(stream_get_contents($stream))->toBe('hello');
    fclose($stream);
});
