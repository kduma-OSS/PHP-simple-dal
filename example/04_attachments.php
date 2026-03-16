<?php

/**
 * Working with attachments.
 *
 * Attachments are binary files associated with records.
 * They support both string content and stream resources.
 */

declare(strict_types=1);

require __DIR__.'/../vendor/autoload.php';

use KDuma\SimpleDAL\Adapter\Database\DatabaseAdapter;
use KDuma\SimpleDAL\DataStore;
use KDuma\SimpleDAL\Entity\CollectionEntityDefinition;

$store = new DataStore(
    adapter: new DatabaseAdapter(new PDO('sqlite::memory:')),
    entities: [
        new CollectionEntityDefinition('certificates'),
    ],
);

$certs = $store->collection('certificates');

// Create a record first
$cert = $certs->create([
    'subject' => ['commonName' => 'example.com'],
    'status' => 'active',
], id: 'cert-01');

// ── Store attachments from string content ──

$pemCert = "-----BEGIN CERTIFICATE-----\nMIIBxTCCAWugAwIBAgIUE...\n-----END CERTIFICATE-----\n";
$pemKey = "-----BEGIN EC PRIVATE KEY-----\nMHQCAQEEIA...\n-----END EC PRIVATE KEY-----\n";

$certs->attachments('cert-01')->put('certificate.pem', $pemCert, 'application/x-pem-file');
$certs->attachments('cert-01')->put('private_key.pem', $pemKey, 'application/x-pem-file');

echo "Stored 2 attachments.\n";

// ── Store from a stream resource ──

$stream = fopen('php://memory', 'r+');
fwrite($stream, 'DER-encoded certificate binary data...');
rewind($stream);

$certs->attachments('cert-01')->putStream('certificate.der', $stream, 'application/x-x509-ca-cert');
fclose($stream);

echo "Stored stream attachment.\n";

// ── List attachments ──

echo "\nAttachments for cert-01:\n";
foreach ($certs->attachments('cert-01')->list() as $att) {
    echo "  - {$att->name} ({$att->mimeType}, {$att->size} bytes)\n";
}

// ── Read an attachment ──

$att = $certs->attachments('cert-01')->get('certificate.pem');
echo "\nCertificate content:\n{$att->contents()}\n";

// ── Read as stream (for large files) ──

$att = $certs->attachments('cert-01')->get('certificate.der');
$resource = $att->stream();
echo 'Stream type: '.get_resource_type($resource)."\n";
echo 'Stream content: '.stream_get_contents($resource)."\n";

// ── Check existence ──

echo "\nHas certificate.pem: ".($certs->attachments('cert-01')->has('certificate.pem') ? 'yes' : 'no')."\n";
echo 'Has missing.txt: '.($certs->attachments('cert-01')->has('missing.txt') ? 'yes' : 'no')."\n";

// ── Nullable get ──

$maybe = $certs->attachments('cert-01')->getOrNull('missing.txt');
echo 'getOrNull for missing: '.($maybe === null ? 'null' : $maybe->name)."\n";

// ── Delete one attachment ──

$certs->attachments('cert-01')->delete('certificate.der');
echo "\nAfter deleting certificate.der:\n";
foreach ($certs->attachments('cert-01')->list() as $att) {
    echo "  - {$att->name}\n";
}

// ── Delete all attachments ──

$certs->attachments('cert-01')->deleteAll();
echo "\nAfter deleteAll: ".count($certs->attachments('cert-01')->list())." attachments\n";

// ── Deleting a record also deletes its attachments ──

$certs->attachments('cert-01')->put('temp.txt', 'will be gone');
$certs->delete('cert-01');
echo "Record + attachments deleted.\n";

echo "\nDone.\n";
