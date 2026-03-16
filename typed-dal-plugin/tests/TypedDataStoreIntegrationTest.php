<?php

declare(strict_types=1);

use KDuma\SimpleDAL\Adapter\Database\DatabaseAdapter;
use KDuma\SimpleDAL\Typed\Contracts\Attribute\Field;
use KDuma\SimpleDAL\Typed\Contracts\TypedRecord;
use KDuma\SimpleDAL\Typed\Converter\DateTimeConverter;
use KDuma\SimpleDAL\Typed\Entity\TypedCollectionDefinition;
use KDuma\SimpleDAL\Typed\Entity\TypedSingletonDefinition;
use KDuma\SimpleDAL\Typed\TypedDataStore;
use KDuma\SimpleDAL\Typed\TypedRecordHydrator;

// -----------------------------------------------------------------
//  Test fixtures
// -----------------------------------------------------------------

enum IntegrationStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
    case Archived = 'archived';
}

enum IntegrationAttachment: string
{
    case Thumbnail = 'thumbnail';
    case Document = 'document';
}

class ArticleRecord extends TypedRecord
{
    #[Field]
    public string $title;

    #[Field]
    public IntegrationStatus $status;

    #[Field]
    public ?string $body;

    #[Field(converter: DateTimeConverter::class)]
    public \DateTimeImmutable $publishedAt;

    #[Field(path: 'meta.tags')]
    public array $tags;
}

class SettingsRecord extends TypedRecord
{
    #[Field(path: 'site_name')]
    public string $siteName;

    #[Field]
    public string $locale;

    #[Field]
    public bool $maintenance;
}

// -----------------------------------------------------------------
//  Setup
// -----------------------------------------------------------------

beforeEach(function () {
    TypedRecordHydrator::clearCache();

    $pdo = new PDO('sqlite::memory:');
    $this->adapter = new DatabaseAdapter($pdo);

    $this->articlesDef = new TypedCollectionDefinition(
        name: 'articles',
        recordClass: ArticleRecord::class,
        attachmentEnum: IntegrationAttachment::class,
        hasAttachments: true,
        hasTimestamps: true,
    );

    $this->settingsDef = new TypedSingletonDefinition(
        name: 'settings',
        recordClass: SettingsRecord::class,
        hasAttachments: true,
        hasTimestamps: true,
    );

    $this->store = new TypedDataStore($this->adapter, [
        $this->articlesDef,
        $this->settingsDef,
    ]);
});

// -----------------------------------------------------------------
//  Collection CRUD
// -----------------------------------------------------------------

test('create and find a typed collection record', function () {
    $collection = $this->store->collection('articles');

    $created = $collection->create([
        'title' => 'First Article',
        'status' => 'draft',
        'body' => 'Hello world.',
        'published_at' => '2024-06-15T10:00:00+00:00',
        'meta' => ['tags' => ['php', 'dal']],
    ], 'art-1');

    expect($created)->toBeInstanceOf(ArticleRecord::class);
    expect($created->id)->toBe('art-1');
    expect($created->title)->toBe('First Article');
    expect($created->status)->toBe(IntegrationStatus::Draft);
    expect($created->body)->toBe('Hello world.');
    expect($created->tags)->toBe(['php', 'dal']);

    // Find it back
    $found = $collection->find('art-1');

    expect($found)->toBeInstanceOf(ArticleRecord::class);
    expect($found->title)->toBe('First Article');
    expect($found->status)->toBe(IntegrationStatus::Draft);
});

test('findOrNull returns null for missing record', function () {
    $collection = $this->store->collection('articles');

    expect($collection->findOrNull('nonexistent'))->toBeNull();
});

test('has checks record existence', function () {
    $collection = $this->store->collection('articles');

    $collection->create([
        'title' => 'Exists',
        'status' => 'draft',
        'body' => null,
        'published_at' => '2024-01-01T00:00:00+00:00',
        'meta' => ['tags' => []],
    ], 'art-2');

    expect($collection->has('art-2'))->toBeTrue();
    expect($collection->has('art-999'))->toBeFalse();
});

test('all returns all typed records', function () {
    $collection = $this->store->collection('articles');

    $collection->create([
        'title' => 'Article A',
        'status' => 'draft',
        'body' => null,
        'published_at' => '2024-01-01T00:00:00+00:00',
        'meta' => ['tags' => []],
    ], 'art-a');

    $collection->create([
        'title' => 'Article B',
        'status' => 'published',
        'body' => 'Content B',
        'published_at' => '2024-02-01T00:00:00+00:00',
        'meta' => ['tags' => ['news']],
    ], 'art-b');

    $all = $collection->all();

    expect($all)->toHaveCount(2);
    expect($all[0])->toBeInstanceOf(ArticleRecord::class);
    expect($all[1])->toBeInstanceOf(ArticleRecord::class);
});

test('save persists modified typed record', function () {
    $collection = $this->store->collection('articles');

    $created = $collection->create([
        'title' => 'Original Title',
        'status' => 'draft',
        'body' => null,
        'published_at' => '2024-01-01T00:00:00+00:00',
        'meta' => ['tags' => []],
    ], 'art-save');

    $created->title = 'Updated Title';
    $created->status = IntegrationStatus::Published;

    $saved = $collection->save($created);

    expect($saved->title)->toBe('Updated Title');
    expect($saved->status)->toBe(IntegrationStatus::Published);

    // Re-fetch to confirm persistence
    $refetched = $collection->find('art-save');

    expect($refetched->title)->toBe('Updated Title');
    expect($refetched->status)->toBe(IntegrationStatus::Published);
});

test('update merges partial data into existing record', function () {
    $collection = $this->store->collection('articles');

    $collection->create([
        'title' => 'Update Me',
        'status' => 'draft',
        'body' => 'Old body',
        'published_at' => '2024-01-01T00:00:00+00:00',
        'meta' => ['tags' => ['old']],
    ], 'art-update');

    $updated = $collection->update('art-update', ['body' => 'New body']);

    expect($updated)->toBeInstanceOf(ArticleRecord::class);
    expect($updated->title)->toBe('Update Me');
    expect($updated->body)->toBe('New body');
});

test('replace overwrites all data', function () {
    $collection = $this->store->collection('articles');

    $collection->create([
        'title' => 'Replace Me',
        'status' => 'draft',
        'body' => 'Old body',
        'published_at' => '2024-01-01T00:00:00+00:00',
        'meta' => ['tags' => ['old']],
    ], 'art-replace');

    $replaced = $collection->replace('art-replace', [
        'title' => 'Replaced',
        'status' => 'published',
        'body' => 'New body',
        'published_at' => '2024-06-01T00:00:00+00:00',
        'meta' => ['tags' => ['new']],
    ]);

    expect($replaced)->toBeInstanceOf(ArticleRecord::class);
    expect($replaced->title)->toBe('Replaced');
    expect($replaced->status)->toBe(IntegrationStatus::Published);
});

test('delete removes a record', function () {
    $collection = $this->store->collection('articles');

    $collection->create([
        'title' => 'Delete Me',
        'status' => 'draft',
        'body' => null,
        'published_at' => '2024-01-01T00:00:00+00:00',
        'meta' => ['tags' => []],
    ], 'art-del');

    expect($collection->has('art-del'))->toBeTrue();

    $collection->delete('art-del');

    expect($collection->has('art-del'))->toBeFalse();
});

test('count returns the number of records', function () {
    $collection = $this->store->collection('articles');

    expect($collection->count())->toBe(0);

    $collection->create([
        'title' => 'A',
        'status' => 'draft',
        'body' => null,
        'published_at' => '2024-01-01T00:00:00+00:00',
        'meta' => ['tags' => []],
    ], 'art-c1');

    $collection->create([
        'title' => 'B',
        'status' => 'draft',
        'body' => null,
        'published_at' => '2024-01-01T00:00:00+00:00',
        'meta' => ['tags' => []],
    ], 'art-c2');

    expect($collection->count())->toBe(2);
});

// -----------------------------------------------------------------
//  Collection name property
// -----------------------------------------------------------------

test('collection entity exposes name', function () {
    $collection = $this->store->collection('articles');

    expect($collection->name)->toBe('articles');
});

// -----------------------------------------------------------------
//  Typed attachments
// -----------------------------------------------------------------

test('typed attachments store via enum', function () {
    $collection = $this->store->collection('articles');

    $collection->create([
        'title' => 'With Attachment',
        'status' => 'draft',
        'body' => null,
        'published_at' => '2024-01-01T00:00:00+00:00',
        'meta' => ['tags' => []],
    ], 'art-att');

    $attachments = $collection->attachments('art-att');

    $attachment = $attachments->put(IntegrationAttachment::Thumbnail, 'image data', 'image/png');

    expect($attachment->name)->toBe('thumbnail');
    expect($attachments->has(IntegrationAttachment::Thumbnail))->toBeTrue();
    expect($attachments->has(IntegrationAttachment::Document))->toBeFalse();

    $retrieved = $attachments->get(IntegrationAttachment::Thumbnail);

    expect($retrieved->contents())->toBe('image data');
});

test('typed attachments list and deleteAll', function () {
    $collection = $this->store->collection('articles');

    $collection->create([
        'title' => 'Multi Attachment',
        'status' => 'draft',
        'body' => null,
        'published_at' => '2024-01-01T00:00:00+00:00',
        'meta' => ['tags' => []],
    ], 'art-multi');

    $attachments = $collection->attachments('art-multi');
    $attachments->put(IntegrationAttachment::Thumbnail, 'thumb data');
    $attachments->put(IntegrationAttachment::Document, 'doc data');

    expect($attachments->list())->toHaveCount(2);

    $attachments->deleteAll();

    expect($attachments->list())->toHaveCount(0);
});

test('typed attachments delete single', function () {
    $collection = $this->store->collection('articles');

    $collection->create([
        'title' => 'Delete Attachment',
        'status' => 'draft',
        'body' => null,
        'published_at' => '2024-01-01T00:00:00+00:00',
        'meta' => ['tags' => []],
    ], 'art-delatt');

    $attachments = $collection->attachments('art-delatt');
    $attachments->put(IntegrationAttachment::Thumbnail, 'thumb');
    $attachments->put(IntegrationAttachment::Document, 'doc');

    $attachments->delete(IntegrationAttachment::Thumbnail);

    expect($attachments->has(IntegrationAttachment::Thumbnail))->toBeFalse();
    expect($attachments->has(IntegrationAttachment::Document))->toBeTrue();
});

test('typed attachments getOrNull returns null for missing', function () {
    $collection = $this->store->collection('articles');

    $collection->create([
        'title' => 'No Attachment',
        'status' => 'draft',
        'body' => null,
        'published_at' => '2024-01-01T00:00:00+00:00',
        'meta' => ['tags' => []],
    ], 'art-noatt');

    $attachments = $collection->attachments('art-noatt');

    expect($attachments->getOrNull(IntegrationAttachment::Thumbnail))->toBeNull();
});

// -----------------------------------------------------------------
//  Singleton CRUD
// -----------------------------------------------------------------

test('singleton set and get', function () {
    $singleton = $this->store->singleton('settings');

    expect($singleton->exists())->toBeFalse();

    $created = $singleton->set([
        'site_name' => 'My Site',
        'locale' => 'en',
        'maintenance' => false,
    ]);

    expect($created)->toBeInstanceOf(SettingsRecord::class);
    expect($created->siteName)->toBe('My Site');
    expect($created->locale)->toBe('en');
    expect($created->maintenance)->toBeFalse();
    expect($singleton->exists())->toBeTrue();

    $fetched = $singleton->get();

    expect($fetched)->toBeInstanceOf(SettingsRecord::class);
    expect($fetched->siteName)->toBe('My Site');
});

test('singleton getOrNull returns null when not set', function () {
    $singleton = $this->store->singleton('settings');

    expect($singleton->getOrNull())->toBeNull();
});

test('singleton save persists modifications', function () {
    $singleton = $this->store->singleton('settings');

    $singleton->set([
        'site_name' => 'Original',
        'locale' => 'en',
        'maintenance' => false,
    ]);

    $record = $singleton->get();
    $record->siteName = 'Updated Site';
    $record->maintenance = true;

    $saved = $singleton->save($record);

    expect($saved->siteName)->toBe('Updated Site');
    expect($saved->maintenance)->toBeTrue();

    // Re-fetch to confirm persistence
    $refetched = $singleton->get();

    expect($refetched->siteName)->toBe('Updated Site');
    expect($refetched->maintenance)->toBeTrue();
});

test('singleton update merges partial data', function () {
    $singleton = $this->store->singleton('settings');

    $singleton->set([
        'site_name' => 'My Site',
        'locale' => 'en',
        'maintenance' => false,
    ]);

    $updated = $singleton->update(['locale' => 'fr']);

    expect($updated)->toBeInstanceOf(SettingsRecord::class);
    expect($updated->siteName)->toBe('My Site');
    expect($updated->locale)->toBe('fr');
});

test('singleton delete removes the record', function () {
    $singleton = $this->store->singleton('settings');

    $singleton->set([
        'site_name' => 'Delete Me',
        'locale' => 'en',
        'maintenance' => false,
    ]);

    expect($singleton->exists())->toBeTrue();

    $singleton->delete();

    expect($singleton->exists())->toBeFalse();
});

test('singleton name property', function () {
    $singleton = $this->store->singleton('settings');

    expect($singleton->name)->toBe('settings');
});

// -----------------------------------------------------------------
//  TypedDataStore meta
// -----------------------------------------------------------------

test('entities returns registered entity definitions', function () {
    $entities = $this->store->entities();

    expect($entities)->toHaveCount(2);
    expect($this->store->hasEntity('articles'))->toBeTrue();
    expect($this->store->hasEntity('settings'))->toBeTrue();
    expect($this->store->hasEntity('nonexistent'))->toBeFalse();
});
