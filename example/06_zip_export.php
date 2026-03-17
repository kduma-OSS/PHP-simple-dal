<?php

/**
 * ZIP adapter — export/import data as a ZIP archive.
 *
 * The ZIP adapter uses the same file layout as the directory adapter,
 * but stores everything inside a ZIP file. Useful for backups and
 * data transfer between systems.
 */

declare(strict_types=1);

require __DIR__.'/../vendor/autoload.php';

use KDuma\SimpleDAL\Adapter\Database\DatabaseAdapter;
use KDuma\SimpleDAL\Adapter\Flysystem\FlysystemAdapter;
use KDuma\SimpleDAL\DataStore;
use KDuma\SimpleDAL\Entity\CollectionEntityDefinition;
use KDuma\SimpleDAL\Entity\SingletonEntityDefinition;
use League\Flysystem\Filesystem;
use League\Flysystem\ZipArchive\FilesystemZipArchiveProvider;
use League\Flysystem\ZipArchive\ZipArchiveAdapter;

// ── Step 1: Create data in SQLite ──

$entities = [
    new CollectionEntityDefinition('certificates'),
    new SingletonEntityDefinition('ca_config'),
];

$sqliteStore = new DataStore(
    adapter: new DatabaseAdapter(new PDO('sqlite::memory:')),
    entities: $entities,
);

$sqliteStore->singleton('ca_config')->set([
    'issuer' => ['commonName' => 'My Root CA'],
    'key_algorithm' => 'EC',
]);

$sqliteStore->collection('certificates')->create([
    'subject' => ['commonName' => 'example.com'],
    'status' => 'active',
], id: 'cert-01');

$sqliteStore->collection('certificates')->attachments('cert-01')->put(
    'certificate.pem',
    "-----BEGIN CERTIFICATE-----\nMIIB...\n-----END CERTIFICATE-----\n",
);

echo "Created data in SQLite.\n";

// ── Step 2: Export to ZIP ──

$zipPath = __DIR__.'/export.zip';

$zipStore = new DataStore(
    adapter: new FlysystemAdapter(
        new Filesystem(new ZipArchiveAdapter(new FilesystemZipArchiveProvider($zipPath))),
    ),
    entities: $entities,
);

// Copy singleton
$caConfig = $sqliteStore->singleton('ca_config')->get();
$zipStore->singleton('ca_config')->set($caConfig->data);

// Copy collection records + attachments
foreach ($sqliteStore->collection('certificates')->all() as $record) {
    $zipStore->collection('certificates')->create($record->data, id: $record->id);

    foreach ($sqliteStore->collection('certificates')->attachments($record->id)->list() as $att) {
        $zipStore->collection('certificates')->attachments($record->id)->put(
            $att->name,
            $att->contents(),
            $att->mimeType,
        );
    }
}

echo "Exported to {$zipPath}\n";
echo 'ZIP size: '.filesize($zipPath)." bytes\n";

// ── Step 3: Read back from ZIP ──

$readStore = new DataStore(
    adapter: new FlysystemAdapter(
        new Filesystem(new ZipArchiveAdapter(new FilesystemZipArchiveProvider($zipPath))),
    ),
    entities: $entities,
);

$config = $readStore->singleton('ca_config')->get();
echo "\nCA from ZIP: {$config->get('issuer.commonName')}\n";

$cert = $readStore->collection('certificates')->find('cert-01');
echo "Cert from ZIP: {$cert->get('subject.commonName')}\n";

$pem = $readStore->collection('certificates')->attachments('cert-01')->get('certificate.pem');
echo "Attachment from ZIP: {$pem->name} ({$pem->size} bytes)\n";

// ── Cleanup ──

unlink($zipPath);
echo "\nDone.\n";
