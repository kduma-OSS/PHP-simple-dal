<?php

/**
 * Typed records -- strongly-typed PHP classes mapped to DAL records.
 *
 * This example shows how to use the typed-dal-plugin to define
 * record classes with #[Field] attributes, enum converters,
 * DateTime converters, and typed attachments.
 */

declare(strict_types=1);

require __DIR__.'/../vendor/autoload.php';

use KDuma\SimpleDAL\Adapter\Database\DatabaseAdapter;
use KDuma\SimpleDAL\Typed\Contracts\Attribute\Field;
use KDuma\SimpleDAL\Typed\Contracts\TypedRecord;
use KDuma\SimpleDAL\Typed\Converter\DateTimeConverter;
use KDuma\SimpleDAL\Typed\Entity\TypedCollectionDefinition;
use KDuma\SimpleDAL\Typed\Entity\TypedSingletonDefinition;
use KDuma\SimpleDAL\Typed\TypedDataStore;

// ── 1. Define enums ──

enum CertificateStatus: string
{
    case Active = 'active';
    case Revoked = 'revoked';
    case Expired = 'expired';
}

enum CertificateAttachment: string
{
    case Certificate = 'certificate.pem';
    case PrivateKey = 'private_key.pem';
}

// ── 2. Define a typed record class ──

class CertificateRecord extends TypedRecord
{
    #[Field]
    public string $serialNumber;

    #[Field(path: 'subject.common_name')]
    public string $commonName;

    #[Field(path: 'subject.organization')]
    public string $organization;

    #[Field]
    public CertificateStatus $status;

    #[Field(converter: DateTimeConverter::class)]
    public DateTimeImmutable $notBefore;

    #[Field(converter: DateTimeConverter::class)]
    public DateTimeImmutable $notAfter;

    #[Field]
    public ?string $revocationReason;
}

// ── 3. Define a typed singleton record class ──

class CaConfigRecord extends TypedRecord
{
    #[Field(path: 'issuer.common_name')]
    public string $issuerName;

    #[Field]
    public string $keyAlgorithm;

    #[Field]
    public ?string $curve;
}

// ── 4. Create TypedDataStore ──

$store = new TypedDataStore(
    adapter: new DatabaseAdapter(new PDO('sqlite:'.__DIR__.'/demo_typed.sqlite')),
    entities: [
        new TypedCollectionDefinition(
            name: 'certificates',
            recordClass: CertificateRecord::class,
            attachmentEnum: CertificateAttachment::class,
            indexedFields: ['subject.common_name', 'status'],
        ),
        new TypedSingletonDefinition(
            name: 'ca_config',
            recordClass: CaConfigRecord::class,
        ),
    ],
);

// ── 5. Create a typed collection record ──
//
// Use make() to get a blank typed record, set properties using
// native PHP types (enums, DateTimeImmutable), then persist with create().

$cert = $store->collection('certificates')->make();
$cert->serialNumber = '01';
$cert->commonName = 'example.com';
$cert->organization = 'Example Inc.';
$cert->status = CertificateStatus::Active;
$cert->notBefore = new DateTimeImmutable('2026-01-01');
$cert->notAfter = new DateTimeImmutable('2027-01-01');
$cert->revocationReason = null;

$cert = $store->collection('certificates')->create($cert, id: 'cert-01');

echo "Created: {$cert->id}\n";
echo "CN: {$cert->commonName}\n";
echo "Org: {$cert->organization}\n";
echo "Status: {$cert->status->value}\n";
echo "Not After: {$cert->notAfter->format('Y-m-d')}\n";
echo 'Revocation Reason: '.($cert->revocationReason ?? '(none)')."\n";

// ── 6. Find and modify ──

$cert = $store->collection('certificates')->find('cert-01');

$cert->status = CertificateStatus::Revoked;
$cert->revocationReason = 'key_compromise';

$cert = $store->collection('certificates')->save($cert);

echo "\nAfter revocation:\n";
echo "Status: {$cert->status->value}\n";
echo "Reason: {$cert->revocationReason}\n";

// ── 7. Typed attachments ──

$attachments = $store->collection('certificates')->attachments('cert-01');

$attachments->put(
    CertificateAttachment::Certificate,
    "-----BEGIN CERTIFICATE-----\nMIIB...\n-----END CERTIFICATE-----\n",
    'application/x-pem-file',
);

echo "\nAttachments:\n";
echo 'Has certificate: '.($attachments->has(CertificateAttachment::Certificate) ? 'yes' : 'no')."\n";
echo 'Has private key: '.($attachments->has(CertificateAttachment::PrivateKey) ? 'yes' : 'no')."\n";

$pem = $attachments->get(CertificateAttachment::Certificate);
echo 'Certificate content: '.strlen($pem->contents())." bytes\n";

// ── 8. Typed singleton ──

$config = $store->singleton('ca_config');

$record = $config->make();
$record->issuerName = 'My Root CA';
$record->keyAlgorithm = 'EC';
$record->curve = 'P-384';
$config->set($record);

$record = $config->get();
echo "\nCA Config:\n";
echo "Issuer: {$record->issuerName}\n";
echo "Algorithm: {$record->keyAlgorithm}\n";
echo "Curve: {$record->curve}\n";

// Modify and save
$record->keyAlgorithm = 'RSA';
$record->curve = null;
$config->save($record);

$record = $config->get();
echo "\nUpdated CA Config:\n";
echo "Algorithm: {$record->keyAlgorithm}\n";
echo 'Curve: '.($record->curve ?? '(none)')."\n";

// Cleanup
@unlink(__DIR__.'/demo_typed.sqlite-shm');
@unlink(__DIR__.'/demo_typed.sqlite-wal');
unlink(__DIR__.'/demo_typed.sqlite');
echo "\nDone.\n";
