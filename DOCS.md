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
