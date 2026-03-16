<?php

/**
 * Directory adapter — git-friendly file storage.
 *
 * Each record is stored as a self-contained directory:
 *   {base}/{entity}/{id}/data.json      (pretty-printed, sorted keys)
 *   {base}/{entity}/{id}/cert.pem       (attachment)
 *
 * This makes every change a clean, reviewable git diff.
 */

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use KDuma\SimpleDAL\Adapter\Directory\DirectoryAdapter;
use KDuma\SimpleDAL\DataStore;
use KDuma\SimpleDAL\Entity\CollectionEntityDefinition;
use KDuma\SimpleDAL\Entity\SingletonEntityDefinition;
use League\Flysystem\Filesystem;
use League\Flysystem\Local\LocalFilesystemAdapter;

// ── Set up the directory adapter ──

$dataDir = __DIR__ . '/data';
@mkdir($dataDir, 0777, true);

$filesystem = new Filesystem(new LocalFilesystemAdapter($dataDir));

$store = new DataStore(
    adapter: new DirectoryAdapter($filesystem),
    entities: [
        new CollectionEntityDefinition(
            name: 'certificates',
            indexedFields: ['status'],
        ),
        new SingletonEntityDefinition('ca_config'),
    ],
);

// ── Create records ──

$store->collection('certificates')->create([
    'serial_number' => '01',
    'subject' => ['commonName' => 'example.com', 'organization' => 'Example Inc.'],
    'status' => 'active',
], id: 'cert-01');

$store->collection('certificates')->create([
    'serial_number' => '02',
    'subject' => ['commonName' => 'test.com', 'organization' => 'Test LLC'],
    'status' => 'active',
], id: 'cert-02');

// Add an attachment
$store->collection('certificates')->attachments('cert-01')->put(
    'certificate.pem',
    "-----BEGIN CERTIFICATE-----\nMIIBxTCCAWug...\n-----END CERTIFICATE-----\n",
);

// Set singleton config
$store->singleton('ca_config')->set([
    'issuer' => ['commonName' => 'My Root CA'],
    'key_algorithm' => 'EC',
    'curve' => 'P-384',
]);

// ── Show resulting file structure ──

echo "File structure in {$dataDir}:\n";
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($dataDir, RecursiveDirectoryIterator::SKIP_DOTS),
    RecursiveIteratorIterator::SELF_FIRST,
);

foreach ($iterator as $file) {
    $relativePath = str_replace($dataDir . '/', '', $file->getPathname());
    $indent = str_repeat('  ', $iterator->getDepth());
    $prefix = $file->isDir() ? '/' : '';
    echo "  {$indent}{$prefix}{$file->getFilename()}\n";
}

// ── Show that JSON is pretty-printed with sorted keys ──

echo "\nContents of certificates/cert-01/data.json:\n";
echo file_get_contents($dataDir . '/certificates/cert-01/data.json') . "\n";

// ── Show the index file ──

if (file_exists($dataDir . '/certificates/_index.json')) {
    echo "Contents of certificates/_index.json:\n";
    echo file_get_contents($dataDir . '/certificates/_index.json') . "\n";
}

// ── Cleanup ──

$cleanup = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($dataDir, RecursiveDirectoryIterator::SKIP_DOTS),
    RecursiveIteratorIterator::CHILD_FIRST,
);
foreach ($cleanup as $file) {
    $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
}
rmdir($dataDir);

echo "Done.\n";
