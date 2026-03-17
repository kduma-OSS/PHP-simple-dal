# Simple DAL

A PHP 8.4 Data Access Layer for storing JSON documents and binary attachments with swappable storage backends.

**Requirements:** PHP 8.4+

## Installation

Install the core library and the adapter(s) you need:

```bash
composer require kduma/simple-dal kduma/simple-dal-db-adapter          # SQLite
# or
composer require kduma/simple-dal kduma/simple-dal-flysystem-adapter  # Flysystem
```

## Quick Start

```php
use KDuma\SimpleDAL\DataStore;
use KDuma\SimpleDAL\Adapter\Database\DatabaseAdapter;
use KDuma\SimpleDAL\Entity\CollectionEntityDefinition;
use KDuma\SimpleDAL\Query\Filter;

$store = new DataStore(
    adapter: new DatabaseAdapter(new PDO('sqlite:data.sqlite')),
    entities: [
        new CollectionEntityDefinition('certificates'),
    ],
);

// Create
$cert = $store->collection('certificates')->create([
    'subject' => ['commonName' => 'example.com'],
    'status' => 'active',
], id: 'cert-01');

// Search
$active = $store->collection('certificates')->filter(
    Filter::where('status', '=', 'active'),
);

// Modify and save
$cert->set('status', 'revoked');
$store->collection('certificates')->save($cert);
```

## Packages

### Core

| Package | Description |
|---------|-------------|
| `kduma/simple-dal` | Core library |
| `kduma/simple-dal-contracts` | Contracts and interfaces |
| `kduma/simple-dal-adapter-contracts` | Adapter SPI contracts and conformance tests |

### Adapters

| Package | Description |
|---------|-------------|
| `kduma/simple-dal-db-adapter` | SQLite database adapter |
| `kduma/simple-dal-flysystem-adapter` | Flysystem adapter (local, ZIP, S3, etc.) |

## Plugins

### Typed Records

Adds strongly-typed PHP record classes to Simple DAL. Define a class with `#[Field]` attributes and PHP 8.4 property hooks, and the plugin handles hydration and dehydration automatically. Supports auto camelCase-to-snake_case path mapping, dot-notation for nested fields, built-in converters for `BackedEnum` and `DateTimeImmutable`, custom converters, and typed attachments via string-backed enums.

```bash
composer require kduma/simple-dal-typed
```

| Package | Description |
|---------|-------------|
| `kduma/simple-dal-typed` | Typed records with auto-mapping and converters |
| `kduma/simple-dal-typed-contracts` | Typed records contracts and interfaces |

### Encryption

Adds transparent, selective encryption of attachments using configurable rules. Attachments are encrypted on write and decrypted on read based on per-entity, per-attachment, and per-record rules. Supports multiple keys, key rotation via `EncryptionMigrator`, and both symmetric and asymmetric encryption. Cipher implementations are pluggable -- install the sodium or phpseclib provider, or write your own.

```bash
# Core + libsodium (SecretBox, SealedBox)
composer require kduma/simple-dal-encryption kduma/simple-dal-encryption-sodium

# Or with phpseclib (RSA, AES)
composer require kduma/simple-dal-encryption kduma/simple-dal-encryption-phpseclib
```

| Package | Description |
|---------|-------------|
| `kduma/simple-dal-encryption` | Selective attachment encryption |
| `kduma/simple-dal-encryption-contracts` | Encryption algorithm contracts |
| `kduma/simple-dal-encryption-sodium` | Libsodium implementations (SecretBox, SealedBox) |
| `kduma/simple-dal-encryption-phpseclib` | phpseclib3 implementations (RSA, AES) |

### Integrity

Adds transparent checksum and signature verification to records and attachments. Every write computes a hash (and optionally a cryptographic signature); every read verifies it. Tamper detection is automatic -- if data has been modified outside the integrity adapter, an `IntegrityException` is thrown. Hashing and signing implementations are pluggable.

```bash
# Core + libsodium (Blake2b, Ed25519)
composer require kduma/simple-dal-integrity kduma/simple-dal-integrity-sodium

# Or with PHP hash/HMAC
composer require kduma/simple-dal-integrity kduma/simple-dal-integrity-hash

# Or with phpseclib (RSA, EC, DSA signing)
composer require kduma/simple-dal-integrity kduma/simple-dal-integrity-phpseclib
```

| Package | Description |
|---------|-------------|
| `kduma/simple-dal-integrity` | Record and attachment integrity checksums and signatures |
| `kduma/simple-dal-integrity-contracts` | Hashing and signing algorithm contracts |
| `kduma/simple-dal-integrity-sodium` | Libsodium implementations (Blake2b, Ed25519) |
| `kduma/simple-dal-integrity-hash` | PHP hash/HMAC implementations (SHA-256, HMAC-SHA256, etc.) |
| `kduma/simple-dal-integrity-phpseclib` | phpseclib3 implementations (RSA, EC, DSA signing) |

## Examples

| File | Description |
|------|-------------|
| [01_basic_sqlite.php](example/01_basic_sqlite.php) | Basic CRUD with SQLite |
| [02_singleton.php](example/02_singleton.php) | Singleton entities |
| [03_filtering.php](example/03_filtering.php) | Filtering and searching |
| [04_attachments.php](example/04_attachments.php) | Binary attachments |
| [05_flysystem_adapter.php](example/05_flysystem_adapter.php) | Flysystem adapter (local directory) |
| [06_zip_export.php](example/06_zip_export.php) | ZIP export/import |
| [07_adapter_switching.php](example/07_adapter_switching.php) | Switching adapters |
| [08_typed_records.php](example/08_typed_records.php) | Typed records plugin |
| [09_encryption.php](example/09_encryption.php) | Selective encryption |
| [10_data_integrity.php](example/10_data_integrity.php) | Data integrity checksums and signatures |

## Documentation

See the [full documentation](https://opensource.duma.sh/libraries/php/simple-dal).

## License

MIT
