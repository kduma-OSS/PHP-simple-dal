<?php

/**
 * Selective attachment encryption with multiple keys.
 *
 * This example shows how to transparently encrypt attachments using
 * the encryption plugin, with symmetric and asymmetric keys, rule-based
 * configuration, and key rotation via the migrator.
 */

declare(strict_types=1);

require __DIR__.'/../vendor/autoload.php';

use KDuma\SimpleDAL\Adapter\Database\DatabaseAdapter;
use KDuma\SimpleDAL\DataStore;
use KDuma\SimpleDAL\Encryption\EncryptedPayload;
use KDuma\SimpleDAL\Encryption\EncryptingStorageAdapter;
use KDuma\SimpleDAL\Encryption\EncryptionConfig;
use KDuma\SimpleDAL\Encryption\EncryptionMigrator;
use KDuma\SimpleDAL\Encryption\EncryptionRule;
use KDuma\SimpleDAL\Encryption\Sodium\SealedBoxAlgorithm;
use KDuma\SimpleDAL\Encryption\Sodium\SecretBoxAlgorithm;
use KDuma\SimpleDAL\Entity\CollectionEntityDefinition;

// ── 1. Generate keys ──

$symmetricKey = new SecretBoxAlgorithm(
    id: 'master',
    key: sodium_crypto_secretbox_keygen(),
);

$keypair = sodium_crypto_box_keypair();
$asymmetricKey = new SealedBoxAlgorithm(
    id: 'sealed',
    publicKey: sodium_crypto_box_publickey($keypair),
    secretKey: sodium_crypto_box_secretkey($keypair),
);

echo "Keys generated.\n";

// ── 2. Configure encryption rules ──

$config = new EncryptionConfig(
    keys: [$symmetricKey, $asymmetricKey],
    rules: [
        // Encrypt private keys with symmetric encryption
        new EncryptionRule(
            keyId: 'master',
            entityName: 'certificates',
            attachmentNames: 'private_key.pem',
        ),
        // Encrypt all attachments in 'secrets' entity with sealed box
        new EncryptionRule(
            keyId: 'sealed',
            entityName: 'secrets',
        ),
    ],
);

// ── 3. Wrap the adapter ──

$innerAdapter = new DatabaseAdapter(new PDO('sqlite:'.__DIR__.'/demo_encryption.sqlite'));

$encryptedAdapter = new EncryptingStorageAdapter(
    inner: $innerAdapter,
    config: $config,
);

$store = new DataStore(
    adapter: $encryptedAdapter,
    entities: [
        new CollectionEntityDefinition('certificates', hasAttachments: true),
        new CollectionEntityDefinition('secrets', hasAttachments: true),
    ],
);

// ── 4. Write attachments ──

$store->collection('certificates')->create(['cn' => 'example.com'], 'cert-01');

// This attachment matches the rule → encrypted
$store->collection('certificates')
    ->attachments('cert-01')
    ->put('private_key.pem', "-----BEGIN EC PRIVATE KEY-----\nMHQC...\n-----END EC PRIVATE KEY-----\n");

// This attachment does NOT match → stored as plaintext
$store->collection('certificates')
    ->attachments('cert-01')
    ->put('certificate.pem', "-----BEGIN CERTIFICATE-----\nMIIB...\n-----END CERTIFICATE-----\n");

echo "\nAttachments written.\n";

// ── 5. Read transparently ──

$privateKey = $store->collection('certificates')
    ->attachments('cert-01')
    ->get('private_key.pem')
    ->contents();

echo 'Private key (decrypted): '.substr($privateKey, 0, 36)."...\n";

// ── 6. Verify raw storage is encrypted ──

$rawPrivateKey = stream_get_contents($innerAdapter->readAttachment('certificates', 'cert-01', 'private_key.pem'));
$rawCert = stream_get_contents($innerAdapter->readAttachment('certificates', 'cert-01', 'certificate.pem'));

echo "\nRaw private_key.pem is encrypted: ".(EncryptedPayload::isEncrypted($rawPrivateKey) ? 'yes' : 'no')."\n";
echo 'Raw certificate.pem is encrypted: '.(EncryptedPayload::isEncrypted($rawCert) ? 'yes' : 'no')."\n";

// ── 7. Key rotation with migrator ──

$newKey = new SecretBoxAlgorithm(
    id: 'master-v2',
    key: sodium_crypto_secretbox_keygen(),
);

$newConfig = new EncryptionConfig(
    keys: [
        $newKey,
        $symmetricKey,   // old key kept for decryption during migration
        $asymmetricKey,
    ],
    rules: [
        new EncryptionRule(
            keyId: 'master-v2',  // now uses the new key
            entityName: 'certificates',
            attachmentNames: 'private_key.pem',
        ),
        new EncryptionRule(
            keyId: 'sealed',
            entityName: 'secrets',
        ),
    ],
);

echo "\nRunning migrator (key rotation)...\n";

$migrator = new EncryptionMigrator($innerAdapter, $newConfig);
$migrator->migrate(['certificates', 'secrets']);

// Verify: now encrypted with new key
$rawAfter = stream_get_contents($innerAdapter->readAttachment('certificates', 'cert-01', 'private_key.pem'));
$payload = EncryptedPayload::decode($rawAfter);
echo "After migration, key ID: {$payload->keyId}\n";

// Read with new config still works
$newAdapter = new EncryptingStorageAdapter($innerAdapter, $newConfig);
$decrypted = stream_get_contents($newAdapter->readAttachment('certificates', 'cert-01', 'private_key.pem'));
echo 'Decrypted with new key: '.substr($decrypted, 0, 36)."...\n";

// Cleanup
@unlink(__DIR__.'/demo_encryption.sqlite-shm');
@unlink(__DIR__.'/demo_encryption.sqlite-wal');
unlink(__DIR__.'/demo_encryption.sqlite');
echo "\nDone.\n";
