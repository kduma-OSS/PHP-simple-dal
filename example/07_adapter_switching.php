<?php

/**
 * Adapter switching — same business logic, different storage.
 *
 * Because everything goes through DataStoreInterface,
 * switching from SQLite to directory to ZIP changes exactly one
 * line of setup code. Business logic is completely decoupled.
 */

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use KDuma\SimpleDAL\Adapter\Database\DatabaseAdapter;
use KDuma\SimpleDAL\Adapter\Directory\DirectoryAdapter;
use KDuma\SimpleDAL\Adapter\Zip\ZipAdapter;
use KDuma\SimpleDAL\Contracts\DataStoreInterface;
use KDuma\SimpleDAL\DataStore;
use KDuma\SimpleDAL\Entity\CollectionEntityDefinition;
use KDuma\SimpleDAL\Query\Filter;
use League\Flysystem\Filesystem;
use League\Flysystem\Local\LocalFilesystemAdapter;
use League\Flysystem\ZipArchive\FilesystemZipArchiveProvider;
use League\Flysystem\ZipArchive\ZipArchiveAdapter;

// ── Business logic that doesn't care about storage ──

function seedAndQuery(DataStoreInterface $store): void
{
    $certs = $store->collection('certificates');

    $certs->create(['domain' => 'example.com', 'status' => 'active'], id: 'c1');
    $certs->create(['domain' => 'test.com',    'status' => 'active'], id: 'c2');
    $certs->create(['domain' => 'old.com',     'status' => 'expired'], id: 'c3');

    $active = $certs->filter(Filter::where('status', '=', 'active'));

    echo "  Active: " . count($active) . " certificates\n";
    foreach ($active as $r) {
        echo "    - {$r->id}: {$r->get('domain')}\n";
    }
}

$entities = [new CollectionEntityDefinition('certificates')];

// ── SQLite adapter ──

echo "=== SQLite ===\n";
seedAndQuery(new DataStore(
    adapter: new DatabaseAdapter(new PDO('sqlite::memory:')),
    entities: $entities,
));

// ── Directory adapter ──

echo "\n=== Directory ===\n";
$dir = __DIR__ . '/adapter-demo';
@mkdir($dir, 0777, true);

seedAndQuery(new DataStore(
    adapter: new DirectoryAdapter(new Filesystem(new LocalFilesystemAdapter($dir))),
    entities: $entities,
));

// Cleanup directory
$it = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
    RecursiveIteratorIterator::CHILD_FIRST,
);
foreach ($it as $f) { $f->isDir() ? rmdir($f->getPathname()) : unlink($f->getPathname()); }
rmdir($dir);

// ── ZIP adapter ──

echo "\n=== ZIP ===\n";
$zipPath = __DIR__ . '/adapter-demo.zip';

seedAndQuery(new DataStore(
    adapter: new ZipAdapter(
        new Filesystem(new ZipArchiveAdapter(new FilesystemZipArchiveProvider($zipPath))),
    ),
    entities: $entities,
));

unlink($zipPath);

echo "\nSame code, three backends. Done.\n";
