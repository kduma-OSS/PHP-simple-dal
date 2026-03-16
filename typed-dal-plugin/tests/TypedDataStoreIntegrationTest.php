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
//  Helpers
// -----------------------------------------------------------------

function makeArticle(TypedDataStore $store, string $title = 'Test', IntegrationStatus $status = IntegrationStatus::Draft, ?string $body = null, ?DateTimeImmutable $publishedAt = null, array $tags = []): ArticleRecord
{
    $record = $store->collection('articles')->make();
    $record->title = $title;
    $record->status = $status;
    $record->body = $body;
    $record->publishedAt = $publishedAt ?? new DateTimeImmutable('2024-01-01T00:00:00+00:00');
    $record->tags = $tags;

    return $record;
}

function makeSettings(TypedDataStore $store, string $siteName = 'My Site', string $locale = 'en', bool $maintenance = false): SettingsRecord
{
    $record = $store->singleton('settings')->make();
    $record->siteName = $siteName;
    $record->locale = $locale;
    $record->maintenance = $maintenance;

    return $record;
}

// -----------------------------------------------------------------
//  make()
// -----------------------------------------------------------------

test('make returns correct typed record class for collection', function () {
    $record = $this->store->collection('articles')->make();

    expect($record)->toBeInstanceOf(ArticleRecord::class);
});

test('make returns correct typed record class for singleton', function () {
    $record = $this->store->singleton('settings')->make();

    expect($record)->toBeInstanceOf(SettingsRecord::class);
});

// -----------------------------------------------------------------
//  Collection CRUD
// -----------------------------------------------------------------

test('create and find a typed collection record', function () {
    $collection = $this->store->collection('articles');

    $record = makeArticle($this->store, 'First Article', IntegrationStatus::Draft, 'Hello world.', new DateTimeImmutable('2024-06-15T10:00:00+00:00'), ['php', 'dal']);
    $created = $collection->create($record, 'art-1');

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

    $collection->create(makeArticle($this->store, 'Exists'), 'art-2');

    expect($collection->has('art-2'))->toBeTrue();
    expect($collection->has('art-999'))->toBeFalse();
});

test('all returns all typed records', function () {
    $collection = $this->store->collection('articles');

    $collection->create(makeArticle($this->store, 'Article A'), 'art-a');
    $collection->create(makeArticle($this->store, 'Article B', IntegrationStatus::Published, 'Content B', new DateTimeImmutable('2024-02-01T00:00:00+00:00'), ['news']), 'art-b');

    $all = $collection->all();

    expect($all)->toHaveCount(2);
    expect($all[0])->toBeInstanceOf(ArticleRecord::class);
    expect($all[1])->toBeInstanceOf(ArticleRecord::class);
});

test('save persists modified typed record', function () {
    $collection = $this->store->collection('articles');

    $created = $collection->create(makeArticle($this->store, 'Original Title'), 'art-save');

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

test('save replaces data for existing record', function () {
    $collection = $this->store->collection('articles');

    $collection->create(makeArticle($this->store, 'Update Me', IntegrationStatus::Draft, 'Old body', new DateTimeImmutable('2024-01-01T00:00:00+00:00'), ['old']), 'art-update');

    $record = $collection->find('art-update');
    $record->body = 'New body';

    $saved = $collection->save($record);

    expect($saved)->toBeInstanceOf(ArticleRecord::class);
    expect($saved->title)->toBe('Update Me');
    expect($saved->body)->toBe('New body');
});

test('delete removes a record', function () {
    $collection = $this->store->collection('articles');

    $collection->create(makeArticle($this->store, 'Delete Me'), 'art-del');

    expect($collection->has('art-del'))->toBeTrue();

    $collection->delete('art-del');

    expect($collection->has('art-del'))->toBeFalse();
});

test('count returns the number of records', function () {
    $collection = $this->store->collection('articles');

    expect($collection->count())->toBe(0);

    $collection->create(makeArticle($this->store, 'A'), 'art-c1');
    $collection->create(makeArticle($this->store, 'B'), 'art-c2');

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

    $collection->create(makeArticle($this->store, 'With Attachment'), 'art-att');

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

    $collection->create(makeArticle($this->store, 'Multi Attachment'), 'art-multi');

    $attachments = $collection->attachments('art-multi');
    $attachments->put(IntegrationAttachment::Thumbnail, 'thumb data');
    $attachments->put(IntegrationAttachment::Document, 'doc data');

    expect($attachments->list())->toHaveCount(2);

    $attachments->deleteAll();

    expect($attachments->list())->toHaveCount(0);
});

test('typed attachments delete single', function () {
    $collection = $this->store->collection('articles');

    $collection->create(makeArticle($this->store, 'Delete Attachment'), 'art-delatt');

    $attachments = $collection->attachments('art-delatt');
    $attachments->put(IntegrationAttachment::Thumbnail, 'thumb');
    $attachments->put(IntegrationAttachment::Document, 'doc');

    $attachments->delete(IntegrationAttachment::Thumbnail);

    expect($attachments->has(IntegrationAttachment::Thumbnail))->toBeFalse();
    expect($attachments->has(IntegrationAttachment::Document))->toBeTrue();
});

test('typed attachments getOrNull returns null for missing', function () {
    $collection = $this->store->collection('articles');

    $collection->create(makeArticle($this->store, 'No Attachment'), 'art-noatt');

    $attachments = $collection->attachments('art-noatt');

    expect($attachments->getOrNull(IntegrationAttachment::Thumbnail))->toBeNull();
});

// -----------------------------------------------------------------
//  Singleton CRUD
// -----------------------------------------------------------------

test('singleton set and get', function () {
    $singleton = $this->store->singleton('settings');

    expect($singleton->exists())->toBeFalse();

    $created = $singleton->set(makeSettings($this->store));

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

    $singleton->set(makeSettings($this->store, 'Original'));

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

test('singleton save updates partial data', function () {
    $singleton = $this->store->singleton('settings');

    $singleton->set(makeSettings($this->store));

    $record = $singleton->get();
    $record->locale = 'fr';

    $saved = $singleton->save($record);

    expect($saved)->toBeInstanceOf(SettingsRecord::class);
    expect($saved->siteName)->toBe('My Site');
    expect($saved->locale)->toBe('fr');
});

test('singleton delete removes the record', function () {
    $singleton = $this->store->singleton('settings');

    $singleton->set(makeSettings($this->store, 'Delete Me'));

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
