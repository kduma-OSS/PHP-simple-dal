# Simple DAL Documentation

A PHP 8.4 Data Access Layer for storing JSON documents and binary attachments with swappable storage backends.

## Table of Contents

- [Installation](#installation)
- [Quick Start](#quick-start)
- [Adapters](#adapters)
  - [SQLite](#sqlite-adapter)
  - [Directory (git-friendly)](#directory-adapter)
  - [ZIP Archive](#zip-adapter)
- [Entity Definitions](#entity-definitions)
  - [Collection Entities](#collection-entities)
  - [Singleton Entities](#singleton-entities)
- [Working with Records](#working-with-records)
  - [Creating](#creating-records)
  - [Reading](#reading-records)
  - [Modifying and Saving](#modifying-and-saving)
  - [Shorthand Update and Replace](#shorthand-update-and-replace)
  - [Deleting](#deleting-records)
- [Filtering and Searching](#filtering-and-searching)
  - [Filter Builder](#filter-builder)
  - [Operators](#operators)
  - [Sorting](#sorting)
  - [Pagination](#pagination)
  - [Counting](#counting)
- [Attachments](#attachments)
- [Error Handling](#error-handling)
- [Switching Adapters](#switching-adapters)
- [Typed Records Plugin](#typed-records-plugin)
  - [Defining Typed Record Classes](#defining-typed-record-classes)
  - [Field Converters](#field-converters)
  - [Typed Entity Definitions](#typed-entity-definitions)
  - [TypedDataStore](#typeddatastore)
  - [Working with Typed Records](#working-with-typed-records)
  - [Typed Attachments](#typed-attachments)
- [Encryption Plugin](#encryption-plugin)
  - [Key Types](#key-types)
  - [Encryption Rules](#encryption-rules)
  - [EncryptingStorageAdapter](#encryptingstorageadapter)
  - [Key Rotation](#key-rotation)
- [Data Integrity Plugin](#data-integrity-plugin)
  - [Hashing Algorithms](#hashing-algorithms)
  - [Signing Algorithms](#signing-algorithms)
  - [IntegrityConfig](#integrityconfig)
  - [IntegrityStorageAdapter](#integritystorageadapter)
  - [Tamper Detection (FailureMode)](#tamper-detection-failuremode)
  - [Migrating Existing Data](#migrating-existing-data)
  - [Stacking with Encryption](#stacking-with-encryption)

---

## Installation

Install the core library and the adapter(s) you need:

```bash
# Core (always required)
composer require kduma/simple-dal

# Pick one or more adapters:
composer require kduma/simple-dal-db-adapter          # SQLite
composer require kduma/simple-dal-directory-adapter    # Directory/files
composer require kduma/simple-dal-zip-adapter          # ZIP archive
```

**Requirements:** PHP 8.4+

The directory and ZIP adapters depend on `league/flysystem ^3.0` (pulled in automatically). The ZIP adapter also pulls in `league/flysystem-ziparchive ^3.0`.

---

## Quick Start

```php
use KDuma\SimpleDAL\DataStore;
use KDuma\SimpleDAL\Adapter\Database\DatabaseAdapter;
use KDuma\SimpleDAL\Entity\CollectionEntityDefinition;
use KDuma\SimpleDAL\Entity\SingletonEntityDefinition;
use KDuma\SimpleDAL\Query\Filter;

// 1. Create a data store
$store = new DataStore(
    adapter: new DatabaseAdapter(new PDO('sqlite:data.sqlite')),
    entities: [
        new CollectionEntityDefinition('certificates'),
        new SingletonEntityDefinition('ca_configuration'),
    ],
);

// 2. Store data
$cert = $store->collection('certificates')->create([
    'subject' => ['commonName' => 'example.com'],
    'status' => 'active',
], id: 'cert-01');

// 3. Attach a file
$store->collection('certificates')
    ->attachments('cert-01')
    ->put('certificate.pem', $pemContents);

// 4. Search
$active = $store->collection('certificates')->filter(
    Filter::where('status', '=', 'active'),
);

// 5. Modify and save
$cert->set('status', 'revoked');
$store->collection('certificates')->save($cert);
```

---

## Adapters

All adapters implement the same interface. Your business logic never touches the adapter directly -- it works through `DataStoreInterface`.

### SQLite Adapter

Stores records as JSON in SQLite tables, attachments as BLOBs. Single-file deployment.

```php
use KDuma\SimpleDAL\Adapter\Database\DatabaseAdapter;

$adapter = new DatabaseAdapter(new PDO('sqlite:/path/to/data.sqlite'));
```

- One table per entity for records, one for attachments
- Filters translated to SQL `WHERE` clauses using `json_extract()`
- Expression indexes created for declared `indexedFields`
- Requires `ext-pdo`

### Directory Adapter

Stores each record as a self-contained directory with `data.json` and attachment files alongside it. Designed for git-friendly workflows.

```php
use KDuma\SimpleDAL\Adapter\Directory\DirectoryAdapter;
use League\Flysystem\Filesystem;
use League\Flysystem\Local\LocalFilesystemAdapter;

$adapter = new DirectoryAdapter(
    new Filesystem(new LocalFilesystemAdapter('/path/to/data')),
);
```

**File layout on disk:**

```
data/
└── certificates/
    ├── cert-01/
    │   ├── data.json          # record data (pretty-printed, sorted keys)
    │   ├── certificate.pem    # attachment
    │   └── private_key.pem    # attachment
    ├── cert-02/
    │   └── data.json
    └── _index.json            # sidecar index for indexed fields
```

- JSON is pretty-printed with recursively sorted keys for clean git diffs
- Sidecar `_index.json` maps indexed field values to record IDs for faster equality lookups
- Non-indexed filters scan all records in memory
- Requires `league/flysystem ^3.0`

### ZIP Adapter

Same layout as the directory adapter, but inside a ZIP archive. Useful for export, import, and backups.

```php
use KDuma\SimpleDAL\Adapter\Zip\ZipAdapter;
use League\Flysystem\Filesystem;
use League\Flysystem\ZipArchive\FilesystemZipArchiveProvider;
use League\Flysystem\ZipArchive\ZipArchiveAdapter;

$adapter = new ZipAdapter(
    new Filesystem(
        new ZipArchiveAdapter(new FilesystemZipArchiveProvider('/path/to/archive.zip')),
    ),
);
```

- Internally delegates to `DirectoryAdapter` -- identical file layout inside the ZIP
- Full read-write support
- Not recommended for concurrent access (ZIP rewrites the entire archive on close)
- Requires `league/flysystem ^3.0` and `league/flysystem-ziparchive ^3.0`

---

## Entity Definitions

Entities are defined when creating the `DataStore`. There are two types:

### Collection Entities

Hold zero or more records, each identified by a unique string ID.

```php
use KDuma\SimpleDAL\Entity\CollectionEntityDefinition;

new CollectionEntityDefinition(
    name: 'certificates',
    hasAttachments: true,    // default: true
    hasTimestamps: true,     // default: true (tracks createdAt/updatedAt)
    idField: null,           // default: null (auto-extract ID from a data field)
    indexedFields: ['status', 'subject.commonName'],  // default: []
);
```

**`idField`** -- If set, the record ID is extracted from this data field on `create()` instead of being auto-generated. Supports dot-notation:

```php
new CollectionEntityDefinition(
    name: 'certificates',
    idField: 'serial_number',  // create(['serial_number' => '01', ...]) → ID is '01'
);
```

**`indexedFields`** -- Fields to index for faster filtering. Dot-notation supported. The effect depends on the adapter:
- SQLite: creates expression indexes on `json_extract()`
- Directory/ZIP: maintains a sidecar `_index.json` for equality lookups

### Singleton Entities

Hold exactly one record (no ID needed). Useful for configuration or settings.

```php
use KDuma\SimpleDAL\Entity\SingletonEntityDefinition;

new SingletonEntityDefinition(
    name: 'ca_configuration',
    hasAttachments: true,    // default: true
    hasTimestamps: true,     // default: true
);
```

### Accessing Entities

```php
$certs  = $store->collection('certificates');     // → CollectionEntityInterface
$config = $store->singleton('ca_configuration');  // → SingletonEntityInterface

// Introspection
$store->hasEntity('certificates');  // true
$store->entities();                 // ['certificates' => ..., 'ca_configuration' => ...]
```

Calling `collection()` on a singleton (or vice versa) throws `EntityNotFoundException` with a helpful message.

---

## Working with Records

Records are mutable data containers. Changes are in-memory until you explicitly persist them via the entity store.

### Creating Records

```php
$certs = $store->collection('certificates');

// Auto-generated UUID v7 ID
$record = $certs->create([
    'subject' => ['commonName' => 'example.com'],
    'status' => 'active',
]);
echo $record->id; // e.g. "0195e7a3-..."

// Explicit ID
$record = $certs->create(
    data: ['subject' => ['commonName' => 'example.com']],
    id: 'cert-01',
);
```

**Record IDs** must match `[a-zA-Z0-9._-]+` (filesystem-safe). Invalid IDs are rejected with an exception.

### Reading Records

```php
// Throws RecordNotFoundException if not found
$record = $certs->find('cert-01');

// Returns null if not found
$record = $certs->findOrNull('cert-01');

// Check existence
$certs->has('cert-01'); // bool

// Get all records
$all = $certs->all(); // RecordInterface[]
```

### Record Properties and Fields

```php
$record->id;        // string
$record->data;      // array -- the full data
$record->createdAt; // ?DateTimeImmutable
$record->updatedAt; // ?DateTimeImmutable

// Dot-notation field access
$record->get('subject.commonName');           // "example.com"
$record->get('missing.field', 'default');     // "default"
$record->has('subject.commonName');           // true

// JSON export
$record->toJson();                            // compact JSON
$record->toJson(JSON_PRETTY_PRINT);           // pretty JSON
```

### Modifying and Saving

Mutate a record in memory, then persist it through the entity store:

```php
$record = $certs->find('cert-01');

// Fluent setters (chainable)
$record->set('status', 'revoked')
       ->set('revoked_at', '2026-03-15')
       ->set('subject.commonName', 'new.example.com');  // dot-notation

// Remove a field
$record->unset('temporary_field');

// Deep merge
$record->merge([
    'subject' => ['organization' => 'New Org'],  // other subject fields preserved
    'new_field' => 'value',
]);

// Persist
$certs->save($record);
```

### Shorthand Update and Replace

For simple cases where you don't need to read the record first:

```php
// Partial update (deep merge by ID) -- reads, merges, writes
$certs->update('cert-01', ['status' => 'revoked']);

// Full overwrite -- replaces all data
$certs->replace('cert-01', ['completely' => 'new data']);
```

### Deleting Records

```php
$certs->delete('cert-01');  // also deletes all attachments
```

### Singleton Operations

Singletons have a simplified API with no ID parameter:

```php
$config = $store->singleton('ca_configuration');

// Create or full replace
$config->set([
    'issuer' => ['commonName' => 'My Root CA'],
    'key_algorithm' => 'EC',
]);

// Read
$record = $config->get();        // throws if not set
$record = $config->getOrNull();  // null if not set
$config->exists();               // bool

// Partial update (deep merge)
$config->update(['key_algorithm' => 'RSA', 'key_size' => 4096]);

// Modify and save
$record = $config->get();
$record->set('key_algorithm', 'EC');
$config->save($record);

// Delete
$config->delete();
```

---

## Filtering and Searching

### Filter Builder

Build queries with a fluent API. Filters work identically across all adapters.

```php
use KDuma\SimpleDAL\Query\Filter;

$results = $certs->filter(
    Filter::where('status', '=', 'active')
        ->andWhere('not_after', '>', '2026-01-01')
        ->orderBy('not_after', SortDirection::Asc)
        ->limit(10),
);
```

**Methods:**

| Method | Description |
|--------|-------------|
| `Filter::where($field, $op, $value)` | Static entry point -- creates a filter with the first condition |
| `->andWhere($field, $op, $value)` | Add an AND condition |
| `->orWhere($field, $op, $value)` | Add an OR condition |
| `->orderBy($field, $direction)` | Add a sort (can chain multiple) |
| `->limit($n)` | Limit number of results |
| `->offset($n)` | Skip first N results |

You can also start with an empty filter for sort/limit-only queries:

```php
$results = $certs->filter(
    (new Filter)->orderBy('priority', SortDirection::Desc)->limit(5),
);
```

### Operators

All operators work across all three adapters.

| Operator | Example | Description |
|----------|---------|-------------|
| `=` | `'status', '=', 'active'` | Equals |
| `!=` | `'status', '!=', 'revoked'` | Not equals |
| `<` | `'age', '<', 30` | Less than |
| `>` | `'age', '>', 30` | Greater than |
| `<=` | `'age', '<=', 30` | Less than or equal |
| `>=` | `'age', '>=', 30` | Greater than or equal |
| `contains` | `'email', 'contains', 'example'` | String contains (case-insensitive) |
| `starts_with` | `'name', 'starts_with', 'Al'` | Starts with (case-insensitive) |
| `ends_with` | `'email', 'ends_with', '.org'` | Ends with (case-insensitive) |
| `in` | `'status', 'in', ['active', 'pending']` | Value in array |
| `not_in` | `'status', 'not_in', ['revoked']` | Value not in array |

Operators can be passed as strings (shown above) or as `FilterOperator` enum values:

```php
use KDuma\SimpleDAL\Contracts\Query\FilterOperator;

Filter::where('status', FilterOperator::Equals, 'active');
```

**Dot-notation** for nested fields:

```php
Filter::where('subject.commonName', '=', 'example.com');
```

### Sorting

```php
use KDuma\SimpleDAL\Contracts\Query\SortDirection;

// Single sort
Filter::where('status', '=', 'active')
    ->orderBy('not_after', SortDirection::Asc);

// Multiple sort fields (chained)
(new Filter)
    ->orderBy('status', SortDirection::Asc)
    ->orderBy('not_after', SortDirection::Desc);
```

### Pagination

```php
// Page 1: first 10 results
$page1 = $certs->filter(
    (new Filter)->orderBy('created_at', SortDirection::Desc)->limit(10),
);

// Page 2: next 10
$page2 = $certs->filter(
    (new Filter)->orderBy('created_at', SortDirection::Desc)->limit(10)->offset(10),
);
```

### Counting

```php
// Count all
$total = $certs->count();

// Count with filter
$activeCount = $certs->count(Filter::where('status', '=', 'active'));
```

---

## Attachments

Attachments are binary files associated with a record. Each attachment has a name, MIME type, and content.

### Collection Entity Attachments

Scoped to a specific record by ID:

```php
$attachments = $certs->attachments('cert-01');
```

### Singleton Entity Attachments

No record ID needed:

```php
$attachments = $store->singleton('ca_keypair')->attachments();
```

### Storing

```php
// From string content
$attachments->put('certificate.pem', $pemContent, 'application/x-pem-file');

// From stream resource (for large files)
$stream = fopen('/path/to/large-file.bin', 'r');
$attachments->putStream('backup.bin', $stream, 'application/octet-stream');
fclose($stream);
```

The MIME type defaults to `application/octet-stream` if omitted.

### Reading

```php
$att = $attachments->get('certificate.pem');  // throws AttachmentNotFoundException
$att = $attachments->getOrNull('missing');    // null

$att->name;       // "certificate.pem"
$att->mimeType;   // "application/x-pem-file"
$att->size;       // byte count (may be null)

// Read into memory
$content = $att->contents();

// Read as stream (for large files)
$stream = $att->stream();
stream_copy_to_stream($stream, $output);
```

### Listing and Checking

```php
$all = $attachments->list();            // AttachmentInterface[]
$exists = $attachments->has('cert.pem'); // bool
```

### Deleting

```php
$attachments->delete('certificate.pem');  // delete one
$attachments->deleteAll();                // delete all for this record
```

Deleting a record also deletes all its attachments automatically.

---

## Error Handling

All exceptions implement `DataStoreExceptionInterface`, enabling broad or narrow catches:

```php
use KDuma\SimpleDAL\Contracts\Exception\DataStoreExceptionInterface;
use KDuma\SimpleDAL\Contracts\Exception\RecordNotFoundException;

// Broad catch
try {
    $store->collection('certificates')->find('missing');
} catch (DataStoreExceptionInterface $e) {
    // Any DAL error
}

// Narrow catch
try {
    $store->collection('certificates')->find('missing');
} catch (RecordNotFoundException $e) {
    // Specifically missing record
}
```

**Exception classes:**

| Exception | Extends | When |
|-----------|---------|------|
| `EntityNotFoundException` | `InvalidArgumentException` | Unknown entity name, or wrong type (collection vs singleton) |
| `RecordNotFoundException` | `RuntimeException` | Record ID does not exist |
| `DuplicateRecordException` | `RuntimeException` | Creating a record with an ID that already exists |
| `AttachmentNotFoundException` | `RuntimeException` | Attachment name does not exist |
| `CorruptedDataException` | `RuntimeException` | Stored data cannot be decoded |
| `InvalidFilterException` | `InvalidArgumentException` | Unsupported filter configuration |
| `ReadOnlyException` | `RuntimeException` | Write operation on a read-only store |

---

## Switching Adapters

The adapter is a constructor concern. Business logic depends only on `DataStoreInterface` and never touches the adapter:

```php
use KDuma\SimpleDAL\Contracts\DataStoreInterface;

function generateReport(DataStoreInterface $store): string
{
    // This code works identically with SQLite, directory, or ZIP
    $certs = $store->collection('certificates')->all();
    // ...
}
```

To switch storage, change only the adapter instantiation:

```php
// SQLite
$store = new DataStore(
    adapter: new DatabaseAdapter(new PDO('sqlite:data.sqlite')),
    entities: $entities,
);

// Directory
$store = new DataStore(
    adapter: new DirectoryAdapter(new Filesystem(new LocalFilesystemAdapter('/data'))),
    entities: $entities,
);

// ZIP
$store = new DataStore(
    adapter: new ZipAdapter(new Filesystem(new ZipArchiveAdapter(new FilesystemZipArchiveProvider('data.zip')))),
    entities: $entities,
);
```

### Copying Data Between Adapters

```php
// Export SQLite → ZIP
foreach ($source->collection('certificates')->all() as $record) {
    $target->collection('certificates')->create($record->data, id: $record->id);

    foreach ($source->collection('certificates')->attachments($record->id)->list() as $att) {
        $target->collection('certificates')
            ->attachments($record->id)
            ->put($att->name, $att->contents(), $att->mimeType);
    }
}
```

---

## Typed Records Plugin

The typed records plugin adds strongly-typed PHP record classes to Simple DAL. Instead of accessing data via `$record->get('field')`, you define a class with typed properties and `#[Field]` attributes. The plugin handles hydration and dehydration automatically.

```bash
# Install the plugin (pulls in simple-dal automatically)
composer require kduma/simple-dal-typed
```

**Requirements:** PHP 8.4+ (uses property hooks)

### Defining Typed Record Classes

Extend `TypedRecord` and mark properties with the `#[Field]` attribute:

```php
use KDuma\SimpleDAL\Typed\Contracts\TypedRecord;
use KDuma\SimpleDAL\Typed\Contracts\Attribute\Field;

class CertificateRecord extends TypedRecord
{
    #[Field]
    public string $serialNumber;          // maps to "serial_number" (auto snake_case)

    #[Field(path: 'subject.common_name')]
    public string $commonName;            // maps to nested "subject.common_name"

    #[Field]
    public CertificateStatus $status;     // BackedEnum -- auto converter

    #[Field]
    public ?string $revocationReason;     // nullable
}
```

**Path mapping rules:**

- No `path:` argument -- property name is converted from camelCase to snake_case (e.g. `$serialNumber` → `serial_number`)
- Explicit `path:` -- used as-is, supports dot-notation for nested fields (e.g. `subject.common_name`)

### Field Converters

Converters transform values between PHP types and storage format.

**Automatic converters:**

- `BackedEnum`-typed properties are automatically converted using `EnumConverter` (no configuration needed)

**Built-in converters:**

```php
use KDuma\SimpleDAL\Typed\Converter\DateTimeConverter;

class CertificateRecord extends TypedRecord
{
    #[Field(converter: DateTimeConverter::class)]
    public \DateTimeImmutable $notAfter;    // stored as ISO 8601 string
}
```

**Custom converters** implement `FieldConverterInterface`:

```php
use KDuma\SimpleDAL\Typed\Contracts\Converter\FieldConverterInterface;

class MoneyConverter implements FieldConverterInterface
{
    public function fromStorage(mixed $value): mixed
    {
        return new Money($value);
    }

    public function toStorage(mixed $value): mixed
    {
        return $value->cents;
    }
}
```

### Typed Entity Definitions

Use `TypedCollectionDefinition` and `TypedSingletonDefinition` instead of the untyped variants. They accept the same parameters plus `recordClass` and `attachmentEnum`:

```php
use KDuma\SimpleDAL\Typed\Entity\TypedCollectionDefinition;
use KDuma\SimpleDAL\Typed\Entity\TypedSingletonDefinition;

// Collection with typed records and typed attachments
new TypedCollectionDefinition(
    name: 'certificates',
    recordClass: CertificateRecord::class,
    attachmentEnum: CertificateAttachment::class,  // optional
    indexedFields: ['status', 'subject.common_name'],
);

// Singleton with typed records
new TypedSingletonDefinition(
    name: 'ca_config',
    recordClass: CaConfigRecord::class,
);
```

### TypedDataStore

`TypedDataStore` wraps `DataStore` and returns typed records instead of generic `RecordInterface`:

```php
use KDuma\SimpleDAL\Adapter\Database\DatabaseAdapter;
use KDuma\SimpleDAL\Typed\TypedDataStore;

$store = new TypedDataStore(
    adapter: new DatabaseAdapter(new PDO('sqlite:data.sqlite')),
    entities: [
        new TypedCollectionDefinition(
            name: 'certificates',
            recordClass: CertificateRecord::class,
        ),
        new TypedSingletonDefinition(
            name: 'ca_config',
            recordClass: CaConfigRecord::class,
        ),
    ],
);

$certs  = $store->collection('certificates');  // → TypedCollectionEntity
$config = $store->singleton('ca_config');       // → TypedSingletonEntity
```

### Working with Typed Records

Use `make()` to create a blank typed record, set its properties using native PHP types, then persist with `create()` or `set()`:

```php
$certs = $store->collection('certificates');

// Create a blank record and populate it
$cert = $certs->make();
$cert->serialNumber = '01';
$cert->commonName = 'example.com';
$cert->status = CertificateStatus::Active;           // enum, not string
$cert->notAfter = new DateTimeImmutable('2027-01-01'); // DateTimeImmutable, not string
$cert->revocationReason = null;

// Persist
$cert = $certs->create($cert, id: 'cert-01');

// Access typed properties
echo $cert->commonName;              // "example.com"
echo $cert->status->value;           // "active"
echo $cert->notAfter->format('Y');   // "2027"

// Find and modify
$cert = $certs->find('cert-01');
$cert->status = CertificateStatus::Revoked;
$cert->revocationReason = 'key_compromise';
$cert = $certs->save($cert);

// Other operations
$cert = $certs->find('cert-01');     // CertificateRecord
$all  = $certs->all();               // CertificateRecord[]
```

**Singletons** work the same way:

```php
$config = $store->singleton('ca_config');

// Create via make() + set()
$record = $config->make();
$record->issuerName = 'My Root CA';
$record->keyAlgorithm = 'EC';
$record->curve = 'P-384';
$config->set($record);

// Read
$record = $config->get();       // CaConfigRecord
echo $record->issuerName;       // "My Root CA"
echo $record->keyAlgorithm;     // "EC"

// Modify and save
$record->keyAlgorithm = 'RSA';
$record->curve = null;
$config->save($record);
```

### Typed Attachments

Define a string-backed enum for attachment names, then use it instead of raw strings:

```php
enum CertificateAttachment: string
{
    case Certificate = 'certificate.pem';
    case PrivateKey = 'private_key.pem';
}

// Pass the enum in TypedCollectionDefinition
new TypedCollectionDefinition(
    name: 'certificates',
    recordClass: CertificateRecord::class,
    attachmentEnum: CertificateAttachment::class,
);

// Use enum values instead of strings
$attachments = $store->collection('certificates')->attachments('cert-01');

$attachments->put(CertificateAttachment::Certificate, $pemContent);
$attachments->has(CertificateAttachment::PrivateKey);     // false
$att = $attachments->get(CertificateAttachment::Certificate);
echo $att->contents();
$attachments->delete(CertificateAttachment::Certificate);
```

---

## Encryption Plugin

The encryption plugin adds transparent, selective encryption of attachments using libsodium. Attachments are encrypted on write and decrypted on read, based on configurable rules. Multiple keys, key rotation, and both symmetric and asymmetric encryption are supported.

```bash
# Core + libsodium keys
composer require kduma/simple-dal-encryption kduma/simple-dal-encryption-sodium

# Or with phpseclib keys (RSA, AES)
composer require kduma/simple-dal-encryption kduma/simple-dal-encryption-phpseclib
```

**Requirements:** PHP 8.4+. Sodium keys need `ext-sodium`. PhpSecLib keys need `phpseclib/phpseclib ^3.0`.

### Key Types

**Symmetric** (`SymmetricKey`) -- uses `sodium_crypto_secretbox` (XSalsa20-Poly1305). Same key for encrypt and decrypt.

```php
use KDuma\SimpleDAL\Encryption\Sodium\SecretBoxAlgorithm;

$key = new SecretBoxAlgorithm(
    id: 'master',
    key: sodium_crypto_secretbox_keygen(),  // 32 bytes
);
```

**Asymmetric** (`KeyPair`) -- uses `sodium_crypto_box_seal` (X25519+XSalsa20-Poly1305). Encrypt with public key only; decrypt requires the secret key.

```php
use KDuma\SimpleDAL\Encryption\Sodium\SealedBoxAlgorithm;

$keypair = sodium_crypto_box_keypair();

$key = new SealedBoxAlgorithm(
    id: 'sealed',
    publicKey: sodium_crypto_box_publickey($keypair),
    secretKey: sodium_crypto_box_secretkey($keypair),  // optional — omit for encrypt-only
);
```

### Encryption Rules

Rules define which attachments to encrypt and with which key. Rules are evaluated in order; the first match wins.

```php
use KDuma\SimpleDAL\Encryption\EncryptionConfig;
use KDuma\SimpleDAL\Encryption\EncryptionRule;

$config = new EncryptionConfig(
    keys: [$masterKey, $sealedKey],
    rules: [
        // Encrypt specific attachment by name
        new EncryptionRule(
            keyId: 'master',
            entityName: 'certificates',
            attachmentNames: 'private_key.pem',
        ),

        // Encrypt multiple attachments (accepts BackedEnum values)
        new EncryptionRule(
            keyId: 'master',
            entityName: 'certificates',
            attachmentNames: [CertAttachment::PrivateKey, CertAttachment::Certificate],
        ),

        // Encrypt all attachments in an entity
        new EncryptionRule(
            keyId: 'sealed',
            entityName: 'secrets',
        ),

        // Encrypt only for specific record IDs
        new EncryptionRule(
            keyId: 'master',
            entityName: 'users',
            attachmentNames: 'avatar.png',
            recordIds: ['user-1', 'user-2'],
        ),
    ],
);
```

### EncryptingStorageAdapter

Wrap any adapter with `EncryptingStorageAdapter` for transparent encryption:

```php
use KDuma\SimpleDAL\Encryption\EncryptingStorageAdapter;

$adapter = new EncryptingStorageAdapter(
    inner: new DatabaseAdapter(new PDO('sqlite:data.sqlite')),
    config: $config,
);

// Use $adapter as the adapter for DataStore — everything else is unchanged
$store = new DataStore(adapter: $adapter, entities: [...]);

// Attachments matching rules are encrypted/decrypted transparently
$store->collection('certificates')
    ->attachments('cert-01')
    ->put('private_key.pem', $pemContent);  // encrypted in storage

$content = $store->collection('certificates')
    ->attachments('cert-01')
    ->get('private_key.pem')
    ->contents();  // decrypted on read
```

Non-matching attachments pass through as plaintext. Listing records, reading record data, and other operations are never affected by encryption.

If an attachment is encrypted but the required key is missing from the config, a `DecryptionException` is thrown on `readAttachment()`.

### Key Rotation

Use `EncryptionMigrator` to re-encrypt attachments after changing keys or rules:

```php
use KDuma\SimpleDAL\Encryption\EncryptionMigrator;

$newConfig = new EncryptionConfig(
    keys: [
        $newKey,       // active key
        $oldKey,       // kept for decrypting existing data
    ],
    rules: [
        new EncryptionRule(keyId: 'new-key', entityName: 'certificates'),
    ],
);

$migrator = new EncryptionMigrator($innerAdapter, $newConfig);
$migrator->migrate(['certificates', 'secrets']);
```

The migrator handles all transitions:
- Unencrypted to encrypted (new rule added)
- Key A to key B (rule changed)
- Encrypted to unencrypted (rule removed)
- Already correct (skipped)

### PhpSecLib Keys

The `kduma/simple-dal-encryption-phpseclib` package provides key implementations using [phpseclib3](https://phpseclib.com/):

**RSA encryption** (OAEP or PKCS1 padding):

```php
use KDuma\SimpleDAL\Encryption\PhpSecLib\RsaAlgorithm;
use phpseclib3\Crypt\RSA;

// Pass a PrivateKey for encrypt + decrypt
$privateKey = RSA::createKey(2048)->withPadding(RSA::ENCRYPTION_OAEP)->withHash('sha256');
$key = new RsaAlgorithm(id: 'rsa-key', key: $privateKey);

// Or pass a PublicKey for encrypt-only
$key = new RsaAlgorithm(id: 'rsa-key', key: $privateKey->getPublicKey());
```

**Symmetric encryption** (AES, ChaCha20, etc.):

```php
use KDuma\SimpleDAL\Encryption\PhpSecLib\AesAlgorithm;
use phpseclib3\Crypt\AES;

$cipher = new AES('ctr');
$cipher->setKey($key32bytes);

$key = new AesAlgorithm(id: 'aes-key', cipher: $cipher);
```

Both key types work interchangeably with sodium keys in `EncryptionConfig`.

---

## Data Integrity Plugin

The data integrity plugin adds transparent checksum and signature verification to records and attachments. Every write computes a hash (and optionally a cryptographic signature); every read verifies it. If data has been tampered with, an `IntegrityException` is thrown.

```bash
# Core plugin (always required)
composer require kduma/simple-dal-data-integrity

# Pick a hashing / signing provider:
composer require kduma/simple-dal-data-integrity-sodium    # Blake2b + Ed25519
composer require kduma/simple-dal-data-integrity-hash      # PHP hash() / hash_hmac()
composer require kduma/simple-dal-data-integrity-phpseclib  # RSA, EC, DSA via phpseclib3
```

**Requirements:** PHP 8.4+. Sodium algorithms need `ext-sodium`. PhpSecLib algorithms need `phpseclib/phpseclib ^3.0`.

### Hashing Algorithms

Hashing algorithms implement `HashingAlgorithmInterface` and compute a checksum for each record and attachment.

**Libsodium -- Blake2b** (`kduma/simple-dal-data-integrity-sodium`):

```php
use KDuma\SimpleDAL\DataIntegrity\Sodium\Blake2bHashingAlgorithm;

$hasher = new Blake2bHashingAlgorithm();
```

Uses `sodium_crypto_generichash()` (BLAKE2b, 32-byte output). No configuration needed.

**PHP hash() extensions** (`kduma/simple-dal-data-integrity-hash`):

```php
use KDuma\SimpleDAL\DataIntegrity\Hash\Hasher\Sha256HashingAlgorithm;
use KDuma\SimpleDAL\DataIntegrity\Hash\Hasher\Sha512HashingAlgorithm;
use KDuma\SimpleDAL\DataIntegrity\Hash\Hasher\Sha3_256HashingAlgorithm;
use KDuma\SimpleDAL\DataIntegrity\Hash\Hasher\Sha1HashingAlgorithm;
use KDuma\SimpleDAL\DataIntegrity\Hash\Hasher\Md5HashingAlgorithm;
use KDuma\SimpleDAL\DataIntegrity\Hash\Hasher\Crc32HashingAlgorithm;

$hasher = new Sha256HashingAlgorithm();   // recommended default
```

Convenience classes for common algorithms. All use PHP's built-in `hash()` function. For custom algorithms, use `GenericPhpHashingAlgorithm` directly:

```php
use KDuma\SimpleDAL\DataIntegrity\Hash\Hasher\GenericPhpHashingAlgorithm;

$hasher = new GenericPhpHashingAlgorithm('sha384', algorithmId: 200);
```

### Signing Algorithms

Signing algorithms implement `SigningAlgorithmInterface` and add a cryptographic signature alongside the hash. Signing is optional -- you can use hashing alone for checksum-only integrity.

**Ed25519** (`kduma/simple-dal-data-integrity-sodium`):

```php
use KDuma\SimpleDAL\DataIntegrity\Sodium\Ed25519SigningAlgorithm;

// Construct from existing keys
$signer = new Ed25519SigningAlgorithm(
    id: 'signing-key-01',
    secretKey: $secretKeyBytes,   // 64 bytes, null for verify-only
    publicKey: $publicKeyBytes,   // 32 bytes
);

// Verify-only (no secret key — cannot sign, only verify)
$verifier = Ed25519SigningAlgorithm::verifyOnly(id: 'signing-key-01', publicKey: $publicKeyBytes);
```

**HMAC** (`kduma/simple-dal-data-integrity-hash`):

```php
use KDuma\SimpleDAL\DataIntegrity\Hash\Signer\HmacSha256SigningAlgorithm;
use KDuma\SimpleDAL\DataIntegrity\Hash\Signer\HmacSha512SigningAlgorithm;
use KDuma\SimpleDAL\DataIntegrity\Hash\Signer\HmacSha1SigningAlgorithm;

$signer = new HmacSha256SigningAlgorithm(id: 'hmac-key', secret: $sharedSecret);
```

For custom HMAC algorithms, use `GenericHmacSigningAlgorithm`:

```php
use KDuma\SimpleDAL\DataIntegrity\Hash\Signer\GenericHmacSigningAlgorithm;

$signer = new GenericHmacSigningAlgorithm(
    id: 'custom-hmac',
    secret: $sharedSecret,
    algo: 'sha384',
    algorithmId: 200,
);
```

**phpseclib3 (RSA, EC, DSA)** (`kduma/simple-dal-data-integrity-phpseclib`):

```php
use KDuma\SimpleDAL\DataIntegrity\PhpSecLib\RsaSigningAlgorithm;
use KDuma\SimpleDAL\DataIntegrity\PhpSecLib\EcSigningAlgorithm;
use KDuma\SimpleDAL\DataIntegrity\PhpSecLib\DsaSigningAlgorithm;
use phpseclib3\Crypt\RSA;
use phpseclib3\Crypt\EC;

// RSA signing
$rsaKey = RSA::createKey(2048);
$signer = new RsaSigningAlgorithm(id: 'rsa-sign', key: $rsaKey);

// EC signing (ECDSA)
$ecKey = EC::createKey('secp256r1');
$signer = new EcSigningAlgorithm(id: 'ec-sign', key: $ecKey);

// Verify-only — pass a PublicKey
$verifier = new RsaSigningAlgorithm(id: 'rsa-sign', key: $rsaKey->getPublicKey());
```

### IntegrityConfig

`IntegrityConfig` holds the hashing algorithm, optional signing algorithm, and failure modes:

```php
use KDuma\SimpleDAL\DataIntegrity\IntegrityConfig;
use KDuma\SimpleDAL\DataIntegrity\FailureMode;

$config = new IntegrityConfig(
    hasher: $hasher,                              // optional — null for sign-only
    signer: $signer,                              // optional — null for hash-only
    onChecksumFailure: FailureMode::Throw,        // default: Throw
    onSignatureFailure: FailureMode::Throw,       // default: Throw
);
```

### IntegrityStorageAdapter

Wrap any adapter with `IntegrityStorageAdapter` for transparent integrity protection:

```php
use KDuma\SimpleDAL\DataIntegrity\IntegrityStorageAdapter;

$adapter = new IntegrityStorageAdapter(
    inner: new DatabaseAdapter(new PDO('sqlite:data.sqlite')),
    config: $config,
);

// Use $adapter as the adapter for DataStore — everything else is unchanged
$store = new DataStore(adapter: $adapter, entities: [...]);

// Records get an _integrity metadata field (stripped on read)
$store->collection('documents')->create(['title' => 'Report'], 'doc-01');

// Attachments are wrapped in an integrity envelope (unwrapped on read)
$store->collection('documents')
    ->attachments('doc-01')
    ->put('report.pdf', $pdfContent);

// Reading verifies integrity transparently
$record = $store->collection('documents')->find('doc-01');
$content = $store->collection('documents')
    ->attachments('doc-01')
    ->get('report.pdf')
    ->contents();
```

Records are protected by embedding an `_integrity` field in the stored JSON (hash, algorithm, and optional signature). This field is automatically stripped when reading. Attachments are protected by wrapping the binary content in an integrity envelope with a magic header.

### Tamper Detection (FailureMode)

`FailureMode` controls what happens when verification fails:

| Mode | Behavior |
|------|----------|
| `FailureMode::Throw` | Throws `IntegrityException` (default) |
| `FailureMode::Ignore` | Silently returns the data as-is |

Checksum and signature failures are configured independently:

```php
$config = new IntegrityConfig(
    hasher: $hasher,
    signer: $signer,
    onChecksumFailure: FailureMode::Throw,   // hash mismatch → exception
    onSignatureFailure: FailureMode::Ignore,  // bad signature → return data anyway
);
```

`IntegrityException` extends `CorruptedDataException` and exposes `entityName`, `recordId`, `expectedHash`, and `actualHash` properties for programmatic inspection:

```php
use KDuma\SimpleDAL\DataIntegrity\Exception\IntegrityException;

try {
    $record = $store->collection('documents')->find('doc-01');
} catch (IntegrityException $e) {
    echo "Tampered: {$e->entityName}/{$e->recordId}";
    echo "Expected: ".bin2hex($e->expectedHash);
    echo "Got: ".bin2hex($e->actualHash);
}
```

### Migrating Existing Data

Use `IntegrityMigrator` to add integrity protection to existing unprotected data, or to re-hash/re-sign after changing algorithms:

```php
use KDuma\SimpleDAL\DataIntegrity\IntegrityMigrator;

$migrator = new IntegrityMigrator($innerAdapter, $config);
$migrator->migrate(['documents', 'certificates']);
```

The migrator processes every record and attachment in the listed entities:
- **Unprotected data** -- adds integrity metadata (hash + optional signature)
- **Already protected** -- re-computes with the current config (algorithm change, key rotation)
- **Unchanged** -- skipped (no unnecessary writes)

### Stacking with Encryption

The integrity and encryption adapters can be stacked. Apply integrity first (inner), then encryption (outer), so that integrity protects the plaintext and encryption protects everything:

```php
use KDuma\SimpleDAL\DataIntegrity\IntegrityStorageAdapter;
use KDuma\SimpleDAL\Encryption\EncryptingStorageAdapter;

$innerAdapter = new DatabaseAdapter(new PDO('sqlite:data.sqlite'));

// 1. Integrity wraps the raw adapter
$integrityAdapter = new IntegrityStorageAdapter($innerAdapter, $integrityConfig);

// 2. Encryption wraps the integrity adapter
$encryptedAdapter = new EncryptingStorageAdapter($integrityAdapter, $encryptionConfig);

$store = new DataStore(adapter: $encryptedAdapter, entities: [...]);
```

With this ordering, reads flow: storage -> decrypt -> verify integrity -> application. Writes flow: application -> compute integrity -> encrypt -> storage.
