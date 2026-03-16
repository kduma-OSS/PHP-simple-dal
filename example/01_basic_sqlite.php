<?php

/**
 * Basic CRUD operations using the SQLite adapter.
 *
 * This example shows how to set up a DataStore with SQLite,
 * create, read, update, and delete records.
 */

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use KDuma\SimpleDAL\Adapter\Database\DatabaseAdapter;
use KDuma\SimpleDAL\DataStore;
use KDuma\SimpleDAL\Entity\CollectionEntityDefinition;
use KDuma\SimpleDAL\Entity\SingletonEntityDefinition;

// 1. Create adapter and data store
$store = new DataStore(
    adapter: new DatabaseAdapter(new PDO('sqlite:' . __DIR__ . '/demo.sqlite')),
    entities: [
        new CollectionEntityDefinition(
            name: 'certificates',
            indexedFields: ['subject.commonName', 'status'],
        ),
        new SingletonEntityDefinition('ca_configuration'),
    ],
);

// 2. Create a record
$cert = $store->collection('certificates')->create(
    data: [
        'serial_number' => '01',
        'subject' => [
            'commonName' => 'example.com',
            'organization' => 'Example Inc.',
        ],
        'not_before' => '2026-01-01T00:00:00Z',
        'not_after' => '2027-01-01T00:00:00Z',
        'status' => 'active',
    ],
    id: 'cert-01',
);

echo "Created: {$cert->id}\n";
echo "CN: {$cert->get('subject.commonName')}\n";

// 3. Read it back
$found = $store->collection('certificates')->find('cert-01');
echo "Found: {$found->get('subject.organization')}\n";

// 4. Modify and save
$found->set('status', 'revoked')
      ->set('revoked_at', '2026-03-15T12:00:00Z');
$store->collection('certificates')->save($found);

echo "Status: {$found->get('status')}\n"; // "revoked"

// 5. Shorthand update (partial merge)
$store->collection('certificates')->update('cert-01', [
    'revocation_reason' => 'key_compromise',
]);

// 6. Check existence
echo "Exists: " . ($store->collection('certificates')->has('cert-01') ? 'yes' : 'no') . "\n";

// 7. Count records
echo "Total: {$store->collection('certificates')->count()}\n";

// 8. Delete
$store->collection('certificates')->delete('cert-01');
echo "After delete: {$store->collection('certificates')->count()} records\n";

// Cleanup
@unlink(__DIR__ . '/demo.sqlite-shm');
@unlink(__DIR__ . '/demo.sqlite-wal');
unlink(__DIR__ . '/demo.sqlite');
echo "\nDone.\n";
