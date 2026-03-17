<?php

/**
 * Data integrity: checksums and signatures for tamper detection.
 *
 * This example shows how to transparently protect records and attachments
 * using the data integrity plugin, with configurable hashing, signing,
 * failure modes, tamper detection, and migration of existing data.
 */

declare(strict_types=1);

require __DIR__.'/../vendor/autoload.php';

use KDuma\SimpleDAL\Adapter\Database\DatabaseAdapter;
use KDuma\SimpleDAL\DataStore;
use KDuma\SimpleDAL\Entity\CollectionEntityDefinition;
use KDuma\SimpleDAL\Integrity\Exception\IntegrityException;
use KDuma\SimpleDAL\Integrity\FailureMode;
use KDuma\SimpleDAL\Integrity\IntegrityConfig;
use KDuma\SimpleDAL\Integrity\IntegrityMigrator;
use KDuma\SimpleDAL\Integrity\IntegrityPayload;
use KDuma\SimpleDAL\Integrity\IntegrityStorageAdapter;
use KDuma\SimpleDAL\Integrity\Sodium\Blake2bHashingAlgorithm;
use KDuma\SimpleDAL\Integrity\Sodium\Ed25519SigningAlgorithm;

// ── 1. Create hashers and signers ──

$hasher = new Blake2bHashingAlgorithm;

$keypair = sodium_crypto_sign_keypair();
$signer = new Ed25519SigningAlgorithm(
    id: 'signing-key-01',
    secretKey: sodium_crypto_sign_secretkey($keypair),
    publicKey: sodium_crypto_sign_publickey($keypair),
);

echo "Blake2b hasher and Ed25519 signing key created.\n";

// ── 2. Configure integrity ──

$config = new IntegrityConfig(
    hasher: $hasher,
    signer: $signer,
    onChecksumFailure: FailureMode::Throw,
    onSignatureFailure: FailureMode::Throw,
);

// ── 3. Wrap the adapter ──

$innerAdapter = new DatabaseAdapter(new PDO('sqlite:'.__DIR__.'/demo_integrity.sqlite'));

$integrityAdapter = new IntegrityStorageAdapter(
    inner: $innerAdapter,
    config: $config,
);

$store = new DataStore(
    adapter: $integrityAdapter,
    entities: [
        new CollectionEntityDefinition('documents', hasAttachments: true),
    ],
);

// ── 4. Write records and attachments ──

$store->collection('documents')->create([
    'title' => 'Project Proposal',
    'author' => 'Alice',
    'status' => 'draft',
], 'doc-01');

$store->collection('documents')
    ->attachments('doc-01')
    ->put('proposal.pdf', "%%PDF-1.4 fake-pdf-content-for-demo\n");

$store->collection('documents')
    ->attachments('doc-01')
    ->put('notes.txt', "Review deadline: 2026-04-01\n");

echo "\nRecord and attachments written with integrity protection.\n";

// ── 5. Read back — verification is transparent ──

$record = $store->collection('documents')->find('doc-01');
echo 'Title: '.$record->get('title')."\n";
echo 'Author: '.$record->get('author')."\n";

$proposal = $store->collection('documents')
    ->attachments('doc-01')
    ->get('proposal.pdf')
    ->contents();

echo 'Attachment (first 34 chars): '.substr($proposal, 0, 34)."...\n";

// ── 6. Verify raw storage has integrity metadata ──

$rawData = $innerAdapter->readRecord('documents', 'doc-01');
echo "\nRecord has _integrity field: ".(isset($rawData['_integrity']) ? 'yes' : 'no')."\n";
echo 'Hash algorithm: '.$rawData['_integrity']['algorithm']."\n";
echo 'Has signature: '.(isset($rawData['_integrity']['signature']) ? 'yes' : 'no')."\n";

$rawAttachment = stream_get_contents($innerAdapter->readAttachment('documents', 'doc-01', 'proposal.pdf'));
echo 'Attachment has integrity header: '.(IntegrityPayload::hasIntegrity($rawAttachment) ? 'yes' : 'no')."\n";

// ── 7. Tamper detection — modify raw data, then read through integrity adapter ──

echo "\nTampering with raw record data...\n";

$tamperedData = $rawData;
$tamperedData['title'] = 'TAMPERED TITLE';
// Keep the old _integrity — the hash will now mismatch
$innerAdapter->writeRecord('documents', 'doc-01', $tamperedData);

try {
    $integrityAdapter->readRecord('documents', 'doc-01');
    echo "ERROR: Expected IntegrityException was not thrown!\n";
} catch (IntegrityException $e) {
    echo 'Caught IntegrityException: '.$e->getMessage()."\n";
}

// Restore original data by writing through the integrity adapter
$store->collection('documents')->update('doc-01', [
    'title' => 'Project Proposal',
    'author' => 'Alice',
    'status' => 'draft',
]);

echo "\nTampering with raw attachment data...\n";

$innerAdapter->writeAttachment(
    'documents',
    'doc-01',
    'notes.txt',
    // Overwrite with a modified integrity payload — replace content but keep the old header
    IntegrityPayload::encode(
        IntegrityPayload::decode($rawAttachment)->hash,
        IntegrityPayload::decode($rawAttachment)->hashAlgorithm,
        "TAMPERED CONTENT\n",
    ),
);

try {
    stream_get_contents($integrityAdapter->readAttachment('documents', 'doc-01', 'notes.txt'));
    echo "ERROR: Expected IntegrityException was not thrown!\n";
} catch (IntegrityException $e) {
    echo 'Caught IntegrityException: '.$e->getMessage()."\n";
}

// ── 8. Migrating existing data with IntegrityMigrator ──

echo "\nCreating unprotected record via inner adapter...\n";

// Write directly to the inner adapter (no integrity)
$innerAdapter->writeRecord('documents', 'doc-02', [
    'title' => 'Legacy Document',
    'author' => 'Bob',
]);
$innerAdapter->writeAttachment('documents', 'doc-02', 'legacy.txt', 'old unprotected content');

$rawBefore = $innerAdapter->readRecord('documents', 'doc-02');
echo 'Before migration — has _integrity: '.(isset($rawBefore['_integrity']) ? 'yes' : 'no')."\n";

echo "Running IntegrityMigrator...\n";

$migrator = new IntegrityMigrator($innerAdapter, $config);
$migrator->migrate(['documents']);

$rawAfter = $innerAdapter->readRecord('documents', 'doc-02');
echo 'After migration — has _integrity: '.(isset($rawAfter['_integrity']) ? 'yes' : 'no')."\n";
echo 'Hash algorithm: '.$rawAfter['_integrity']['algorithm']."\n";
echo 'Has signature: '.(isset($rawAfter['_integrity']['signature']) ? 'yes' : 'no')."\n";

// Now reading through the integrity adapter works without errors
$record = $store->collection('documents')->find('doc-02');
echo 'Migrated record title: '.$record->get('title')."\n";

// Cleanup
@unlink(__DIR__.'/demo_integrity.sqlite-shm');
@unlink(__DIR__.'/demo_integrity.sqlite-wal');
unlink(__DIR__.'/demo_integrity.sqlite');
echo "\nDone.\n";
