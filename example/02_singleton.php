<?php

/**
 * Singleton entities — for configuration or single-instance data.
 *
 * A singleton entity stores exactly one record (no ID needed).
 */

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use KDuma\SimpleDAL\Adapter\Database\DatabaseAdapter;
use KDuma\SimpleDAL\DataStore;
use KDuma\SimpleDAL\Entity\SingletonEntityDefinition;

$store = new DataStore(
    adapter: new DatabaseAdapter(new PDO('sqlite::memory:')),
    entities: [
        new SingletonEntityDefinition('ca_configuration'),
        new SingletonEntityDefinition('ca_keypair', hasAttachments: true),
    ],
);

// ── Set the singleton for the first time ──

$config = $store->singleton('ca_configuration');

$config->set([
    'issuer' => [
        'commonName' => 'My Root CA',
        'organization' => 'My Org',
        'country' => 'US',
    ],
    'key_algorithm' => 'EC',
    'curve' => 'P-384',
    'crl_url' => 'https://ca.example.com/crl',
]);

// ── Read it back ──

$record = $config->get();
echo "CA: {$record->get('issuer.commonName')}\n";
echo "Algorithm: {$record->get('key_algorithm')}\n";

// ── Partial update (deep merge) ──

$config->update([
    'crl_url' => 'https://ca.example.com/v2/crl',
    'ocsp_url' => 'https://ca.example.com/ocsp',
]);

$record = $config->get();
echo "CRL: {$record->get('crl_url')}\n";
echo "OCSP: {$record->get('ocsp_url')}\n";
echo "Issuer still here: {$record->get('issuer.organization')}\n";

// ── Modify and save ──

$record = $config->get();
$record->set('key_algorithm', 'RSA')
       ->unset('curve')
       ->merge(['key_size' => 4096]);
$config->save($record);

echo "\nUpdated config:\n";
echo json_encode($config->get()->data, JSON_PRETTY_PRINT) . "\n";

// ── Check existence ──

echo "\nExists: " . ($config->exists() ? 'yes' : 'no') . "\n";

// ── Delete ──

$config->delete();
echo "After delete: " . ($config->exists() ? 'yes' : 'no') . "\n";

// ── Attachments on a singleton ──

$keypair = $store->singleton('ca_keypair');
$keypair->set(['algorithm' => 'EC', 'curve' => 'P-384']);

$keypair->attachments()->put('root_ca.pem', "-----BEGIN CERTIFICATE-----\n...\n-----END CERTIFICATE-----\n");
$keypair->attachments()->put('root_ca.key', "-----BEGIN EC PRIVATE KEY-----\n...\n-----END EC PRIVATE KEY-----\n");

echo "\nKeypair attachments:\n";
foreach ($keypair->attachments()->list() as $att) {
    echo "  - {$att->name} ({$att->size} bytes)\n";
}

echo "\nDone.\n";
