<?php

declare(strict_types=1);

use KDuma\SimpleDAL\Adapter\Database\DatabaseAdapter;
use KDuma\SimpleDAL\Contracts\EntityDefinitionInterface;
use KDuma\SimpleDAL\Encryption\EncryptedPayload;
use KDuma\SimpleDAL\Encryption\EncryptionConfig;
use KDuma\SimpleDAL\Encryption\EncryptionMigrator;
use KDuma\SimpleDAL\Encryption\EncryptionRule;
use KDuma\SimpleDAL\Encryption\Sodium\SecretBoxAlgorithm;

beforeEach(function () {
    $this->pdo = new PDO('sqlite::memory:');
    $this->adapter = new DatabaseAdapter($this->pdo);
    $this->keyA = new SecretBoxAlgorithm('key-a', sodium_crypto_secretbox_keygen());
    $this->keyB = new SecretBoxAlgorithm('key-b', sodium_crypto_secretbox_keygen());

    $definition = new class('test_entity', false, true, false, []) implements EntityDefinitionInterface
    {
        /** @param array<string> $indexedFields */
        public function __construct(
            public readonly string $name,
            public readonly bool $isSingleton,
            public readonly bool $hasAttachments,
            public readonly bool $hasTimestamps,
            public readonly array $indexedFields,
        ) {}
    };

    $this->adapter->initializeEntity('test_entity', $definition);
    $this->adapter->writeRecord('test_entity', 'rec-1', ['x' => 1]);
});

test('migrates unencrypted to encrypted', function () {
    $this->adapter->writeAttachment('test_entity', 'rec-1', 'file.txt', 'plaintext');

    $config = new EncryptionConfig(
        keys: [$this->keyA],
        rules: [new EncryptionRule('key-a', 'test_entity')],
    );

    (new EncryptionMigrator($this->adapter, $config))->migrate(['test_entity']);

    $raw = stream_get_contents($this->adapter->readAttachment('test_entity', 'rec-1', 'file.txt'));
    expect(EncryptedPayload::isEncrypted($raw))->toBeTrue();

    $payload = EncryptedPayload::decode($raw);
    expect($payload->keyId)->toBe('key-a');
});

test('migrates key A to key B', function () {
    // Encrypt with key A
    $encrypted = $this->keyA->encrypt('secret');
    $data = EncryptedPayload::encode('key-a', $this->keyA->algorithm, $encrypted);
    $this->adapter->writeAttachment('test_entity', 'rec-1', 'file.txt', $data);

    // Migrate to key B
    $config = new EncryptionConfig(
        keys: [$this->keyA, $this->keyB],
        rules: [new EncryptionRule('key-b', 'test_entity')],
    );

    (new EncryptionMigrator($this->adapter, $config))->migrate(['test_entity']);

    $raw = stream_get_contents($this->adapter->readAttachment('test_entity', 'rec-1', 'file.txt'));
    $payload = EncryptedPayload::decode($raw);
    expect($payload->keyId)->toBe('key-b');

    // Verify content is preserved
    $plaintext = $this->keyB->decrypt($payload->payload);
    expect($plaintext)->toBe('secret');
});

test('migrates encrypted to unencrypted when rule removed', function () {
    // Encrypt with key A
    $encrypted = $this->keyA->encrypt('was secret');
    $data = EncryptedPayload::encode('key-a', $this->keyA->algorithm, $encrypted);
    $this->adapter->writeAttachment('test_entity', 'rec-1', 'file.txt', $data);

    // Migrate with no rules (= decrypt everything)
    $config = new EncryptionConfig(
        keys: [$this->keyA],
        rules: [],
    );

    (new EncryptionMigrator($this->adapter, $config))->migrate(['test_entity']);

    $raw = stream_get_contents($this->adapter->readAttachment('test_entity', 'rec-1', 'file.txt'));
    expect(EncryptedPayload::isEncrypted($raw))->toBeFalse();
    expect($raw)->toBe('was secret');
});

test('skips already correct attachments', function () {
    // Encrypt with key A
    $encrypted = $this->keyA->encrypt('already correct');
    $data = EncryptedPayload::encode('key-a', $this->keyA->algorithm, $encrypted);
    $this->adapter->writeAttachment('test_entity', 'rec-1', 'file.txt', $data);

    $config = new EncryptionConfig(
        keys: [$this->keyA],
        rules: [new EncryptionRule('key-a', 'test_entity')],
    );

    // Migrate — should be a no-op (same key)
    (new EncryptionMigrator($this->adapter, $config))->migrate(['test_entity']);

    $raw = stream_get_contents($this->adapter->readAttachment('test_entity', 'rec-1', 'file.txt'));
    $payload = EncryptedPayload::decode($raw);
    expect($payload->keyId)->toBe('key-a');

    $plaintext = $this->keyA->decrypt($payload->payload);
    expect($plaintext)->toBe('already correct');
});
