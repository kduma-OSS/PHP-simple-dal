<?php

declare(strict_types=1);

use KDuma\SimpleDAL\Adapter\Database\DatabaseAdapter;
use KDuma\SimpleDAL\Contracts\EntityDefinitionInterface;
use KDuma\SimpleDAL\Encryption\Contracts\Exception\DecryptionException;
use KDuma\SimpleDAL\Encryption\EncryptedPayload;
use KDuma\SimpleDAL\Encryption\EncryptingStorageAdapter;
use KDuma\SimpleDAL\Encryption\EncryptionConfig;
use KDuma\SimpleDAL\Encryption\EncryptionRule;
use KDuma\SimpleDAL\Encryption\Sodium\KeyPair;
use KDuma\SimpleDAL\Encryption\Sodium\SymmetricKey;

beforeEach(function () {
    $this->pdo = new PDO('sqlite::memory:');
    $this->inner = new DatabaseAdapter($this->pdo);
    $this->symmetricKey = new SymmetricKey('sym-key', sodium_crypto_secretbox_keygen());

    $keypair = sodium_crypto_box_keypair();
    $this->asymmetricKey = new KeyPair(
        'asym-key',
        sodium_crypto_box_publickey($keypair),
        sodium_crypto_box_secretkey($keypair),
    );

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
    $this->inner->initializeEntity('other_entity', $definition);
    $this->inner->writeRecord('test_entity', 'rec-1', ['x' => 1]);
    $this->inner->writeRecord('other_entity', 'rec-1', ['x' => 1]);
});

test('write and read attachment round-trips with symmetric encryption', function () {
    $adapter = new EncryptingStorageAdapter($this->inner, new EncryptionConfig(
        keys: [$this->symmetricKey],
        rules: [new EncryptionRule('sym-key', 'test_entity')],
    ));

    $adapter->writeAttachment('test_entity', 'rec-1', 'secret.txt', 'hello world');
    $stream = $adapter->readAttachment('test_entity', 'rec-1', 'secret.txt');

    expect(stream_get_contents($stream))->toBe('hello world');
});

test('write and read attachment round-trips with asymmetric encryption', function () {
    $adapter = new EncryptingStorageAdapter($this->inner, new EncryptionConfig(
        keys: [$this->asymmetricKey],
        rules: [new EncryptionRule('asym-key', 'test_entity')],
    ));

    $adapter->writeAttachment('test_entity', 'rec-1', 'secret.txt', 'sealed content');
    $stream = $adapter->readAttachment('test_entity', 'rec-1', 'secret.txt');

    expect(stream_get_contents($stream))->toBe('sealed content');
});

test('non-matching attachments pass through unencrypted', function () {
    $adapter = new EncryptingStorageAdapter($this->inner, new EncryptionConfig(
        keys: [$this->symmetricKey],
        rules: [new EncryptionRule('sym-key', 'test_entity', attachmentNames: 'secret.txt')],
    ));

    $adapter->writeAttachment('test_entity', 'rec-1', 'public.txt', 'not secret');

    // Read via inner adapter — should be plaintext
    $stream = $this->inner->readAttachment('test_entity', 'rec-1', 'public.txt');

    expect(stream_get_contents($stream))->toBe('not secret');
});

test('raw data is encrypted in storage', function () {
    $adapter = new EncryptingStorageAdapter($this->inner, new EncryptionConfig(
        keys: [$this->symmetricKey],
        rules: [new EncryptionRule('sym-key', 'test_entity')],
    ));

    $adapter->writeAttachment('test_entity', 'rec-1', 'secret.txt', 'plaintext');

    // Read raw from inner adapter — should be encrypted
    $stream = $this->inner->readAttachment('test_entity', 'rec-1', 'secret.txt');
    $rawData = stream_get_contents($stream);

    expect($rawData)->not->toBe('plaintext');
    expect(EncryptedPayload::isEncrypted($rawData))->toBeTrue();
});

test('reading unencrypted data when rule exists passes through gracefully', function () {
    // Write plaintext directly via inner adapter
    $this->inner->writeAttachment('test_entity', 'rec-1', 'file.txt', 'plain data');

    $adapter = new EncryptingStorageAdapter($this->inner, new EncryptionConfig(
        keys: [$this->symmetricKey],
        rules: [new EncryptionRule('sym-key', 'test_entity')],
    ));

    // Should read plaintext without error
    $stream = $adapter->readAttachment('test_entity', 'rec-1', 'file.txt');

    expect(stream_get_contents($stream))->toBe('plain data');
});

test('rule matches entity and specific attachment name', function () {
    $adapter = new EncryptingStorageAdapter($this->inner, new EncryptionConfig(
        keys: [$this->symmetricKey],
        rules: [new EncryptionRule('sym-key', 'test_entity', attachmentNames: 'secret.txt')],
    ));

    $adapter->writeAttachment('test_entity', 'rec-1', 'secret.txt', 'encrypted');
    $adapter->writeAttachment('test_entity', 'rec-1', 'public.txt', 'not encrypted');

    // secret.txt is encrypted in storage
    $raw = stream_get_contents($this->inner->readAttachment('test_entity', 'rec-1', 'secret.txt'));
    expect(EncryptedPayload::isEncrypted($raw))->toBeTrue();

    // public.txt is plaintext in storage
    $raw = stream_get_contents($this->inner->readAttachment('test_entity', 'rec-1', 'public.txt'));
    expect($raw)->toBe('not encrypted');
});

test('rule matches entity and specific record IDs', function () {
    $this->inner->writeRecord('test_entity', 'rec-2', ['x' => 2]);

    $adapter = new EncryptingStorageAdapter($this->inner, new EncryptionConfig(
        keys: [$this->symmetricKey],
        rules: [new EncryptionRule('sym-key', 'test_entity', recordIds: ['rec-1'])],
    ));

    $adapter->writeAttachment('test_entity', 'rec-1', 'file.txt', 'for rec-1');
    $adapter->writeAttachment('test_entity', 'rec-2', 'file.txt', 'for rec-2');

    // rec-1 attachment is encrypted
    $raw = stream_get_contents($this->inner->readAttachment('test_entity', 'rec-1', 'file.txt'));
    expect(EncryptedPayload::isEncrypted($raw))->toBeTrue();

    // rec-2 attachment is plaintext
    $raw = stream_get_contents($this->inner->readAttachment('test_entity', 'rec-2', 'file.txt'));
    expect($raw)->toBe('for rec-2');
});

test('rule matches entity only — all attachments encrypted', function () {
    $adapter = new EncryptingStorageAdapter($this->inner, new EncryptionConfig(
        keys: [$this->symmetricKey],
        rules: [new EncryptionRule('sym-key', 'test_entity')],
    ));

    $adapter->writeAttachment('test_entity', 'rec-1', 'a.txt', 'data a');
    $adapter->writeAttachment('test_entity', 'rec-1', 'b.txt', 'data b');

    expect(EncryptedPayload::isEncrypted(stream_get_contents($this->inner->readAttachment('test_entity', 'rec-1', 'a.txt'))))->toBeTrue();
    expect(EncryptedPayload::isEncrypted(stream_get_contents($this->inner->readAttachment('test_entity', 'rec-1', 'b.txt'))))->toBeTrue();
});

test('stream input to writeAttachment works', function () {
    $adapter = new EncryptingStorageAdapter($this->inner, new EncryptionConfig(
        keys: [$this->symmetricKey],
        rules: [new EncryptionRule('sym-key', 'test_entity')],
    ));

    $stream = fopen('php://memory', 'r+');
    fwrite($stream, 'stream content');
    rewind($stream);

    $adapter->writeAttachment('test_entity', 'rec-1', 'file.txt', $stream);
    fclose($stream);

    $result = $adapter->readAttachment('test_entity', 'rec-1', 'file.txt');

    expect(stream_get_contents($result))->toBe('stream content');
});

test('missing key throws DecryptionException on read', function () {
    // Write with key
    $adapter = new EncryptingStorageAdapter($this->inner, new EncryptionConfig(
        keys: [$this->symmetricKey],
        rules: [new EncryptionRule('sym-key', 'test_entity')],
    ));
    $adapter->writeAttachment('test_entity', 'rec-1', 'file.txt', 'secret');

    // Read with different config (missing key)
    $adapter2 = new EncryptingStorageAdapter($this->inner, new EncryptionConfig(
        keys: [],
        rules: [],
    ));

    $adapter2->readAttachment('test_entity', 'rec-1', 'file.txt');
})->throws(DecryptionException::class);

test('other entity attachments are not affected', function () {
    $adapter = new EncryptingStorageAdapter($this->inner, new EncryptionConfig(
        keys: [$this->symmetricKey],
        rules: [new EncryptionRule('sym-key', 'test_entity')],
    ));

    $adapter->writeAttachment('other_entity', 'rec-1', 'file.txt', 'plain');

    $raw = stream_get_contents($this->inner->readAttachment('other_entity', 'rec-1', 'file.txt'));
    expect($raw)->toBe('plain');
});

test('rule with BackedEnum attachment names', function () {
    enum TestAttachment: string
    {
        case Secret = 'secret.dat';
        case Public = 'public.dat';
    }

    $adapter = new EncryptingStorageAdapter($this->inner, new EncryptionConfig(
        keys: [$this->symmetricKey],
        rules: [new EncryptionRule('sym-key', 'test_entity', attachmentNames: TestAttachment::Secret)],
    ));

    $adapter->writeAttachment('test_entity', 'rec-1', 'secret.dat', 'encrypted');
    $adapter->writeAttachment('test_entity', 'rec-1', 'public.dat', 'not encrypted');

    expect(EncryptedPayload::isEncrypted(stream_get_contents($this->inner->readAttachment('test_entity', 'rec-1', 'secret.dat'))))->toBeTrue();
    expect(stream_get_contents($this->inner->readAttachment('test_entity', 'rec-1', 'public.dat')))->toBe('not encrypted');
});
