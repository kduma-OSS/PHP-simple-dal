<?php

declare(strict_types=1);

use KDuma\SimpleDAL\Record;
use KDuma\SimpleDAL\Typed\Contracts\Attribute\Field;
use KDuma\SimpleDAL\Typed\Contracts\TypedRecord;
use KDuma\SimpleDAL\Typed\Converter\DateTimeConverter;
use KDuma\SimpleDAL\Typed\Converter\EnumConverter;
use KDuma\SimpleDAL\Typed\TypedRecordHydrator;

// -----------------------------------------------------------------
//  Test fixtures
// -----------------------------------------------------------------

enum HydratorTestStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
}

class HydratorTestRecord extends TypedRecord
{
    #[Field]
    public string $title;

    #[Field]
    public HydratorTestStatus $status;

    #[Field(path: 'author_name')]
    public string $authorName;

    #[Field(converter: DateTimeConverter::class)]
    public DateTimeImmutable $publishedAt;

    #[Field]
    public ?string $description;
}

class HydratorNestedRecord extends TypedRecord
{
    /** @var array<string> */
    #[Field(path: 'meta.tags')]
    public array $tags;

    #[Field(path: 'meta.priority')]
    public int $priority;
}

// -----------------------------------------------------------------
//  camelToSnake
// -----------------------------------------------------------------

test('camelToSnake converts simple names', function () {
    expect(TypedRecordHydrator::camelToSnake('firstName'))->toBe('first_name');
    expect(TypedRecordHydrator::camelToSnake('lastName'))->toBe('last_name');
    expect(TypedRecordHydrator::camelToSnake('title'))->toBe('title');
    expect(TypedRecordHydrator::camelToSnake('createdAt'))->toBe('created_at');
    expect(TypedRecordHydrator::camelToSnake('myURLParser'))->toBe('my_u_r_l_parser');
});

// -----------------------------------------------------------------
//  discoverFields
// -----------------------------------------------------------------

test('discoverFields finds all Field-attributed properties', function () {
    TypedRecordHydrator::clearCache();

    $mappings = TypedRecordHydrator::discoverFields(HydratorTestRecord::class);

    expect($mappings)->toHaveCount(5);

    $names = array_map(fn ($m) => $m->propertyName, $mappings);
    expect($names)->toContain('title', 'status', 'authorName', 'publishedAt', 'description');
});

test('discoverFields resolves default path via camelToSnake', function () {
    TypedRecordHydrator::clearCache();

    $mappings = TypedRecordHydrator::discoverFields(HydratorTestRecord::class);
    $titleMapping = array_values(array_filter($mappings, fn ($m) => $m->propertyName === 'title'))[0];

    expect($titleMapping->dataPath)->toBe('title');
});

test('discoverFields uses explicit path when provided', function () {
    TypedRecordHydrator::clearCache();

    $mappings = TypedRecordHydrator::discoverFields(HydratorTestRecord::class);
    $authorMapping = array_values(array_filter($mappings, fn ($m) => $m->propertyName === 'authorName'))[0];

    expect($authorMapping->dataPath)->toBe('author_name');
});

test('discoverFields auto-detects backed enum converter', function () {
    TypedRecordHydrator::clearCache();

    $mappings = TypedRecordHydrator::discoverFields(HydratorTestRecord::class);
    $statusMapping = array_values(array_filter($mappings, fn ($m) => $m->propertyName === 'status'))[0];

    expect($statusMapping->converter)->toBeInstanceOf(EnumConverter::class);
});

test('discoverFields uses explicit converter when provided', function () {
    TypedRecordHydrator::clearCache();

    $mappings = TypedRecordHydrator::discoverFields(HydratorTestRecord::class);
    $publishedMapping = array_values(array_filter($mappings, fn ($m) => $m->propertyName === 'publishedAt'))[0];

    expect($publishedMapping->converter)->toBeInstanceOf(DateTimeConverter::class);
});

test('discoverFields detects nullable property', function () {
    TypedRecordHydrator::clearCache();

    $mappings = TypedRecordHydrator::discoverFields(HydratorTestRecord::class);
    $descMapping = array_values(array_filter($mappings, fn ($m) => $m->propertyName === 'description'))[0];
    $titleMapping = array_values(array_filter($mappings, fn ($m) => $m->propertyName === 'title'))[0];

    expect($descMapping->isNullable)->toBeTrue();
    expect($titleMapping->isNullable)->toBeFalse();
});

// -----------------------------------------------------------------
//  hydration
// -----------------------------------------------------------------

test('hydrateFromRecord populates typed properties', function () {
    TypedRecordHydrator::clearCache();

    $record = new Record(
        _id: 'rec-1',
        _data: [
            'title' => 'Hello World',
            'status' => 'published',
            'author_name' => 'John Doe',
            'published_at' => '2024-06-15T10:00:00+00:00',
            'description' => 'A test post',
        ],
        _createdAt: new DateTimeImmutable('2024-01-01'),
        _updatedAt: new DateTimeImmutable('2024-06-15'),
    );

    $typed = TypedRecordHydrator::hydrateFromRecord(HydratorTestRecord::class, $record);
    assert($typed instanceof HydratorTestRecord);

    expect($typed)->toBeInstanceOf(HydratorTestRecord::class);
    expect($typed->id)->toBe('rec-1');
    expect($typed->title)->toBe('Hello World');
    expect($typed->status)->toBe(HydratorTestStatus::Published);
    expect($typed->authorName)->toBe('John Doe');
    expect($typed->publishedAt)->toBeInstanceOf(DateTimeImmutable::class);
    expect($typed->publishedAt->format('Y-m-d'))->toBe('2024-06-15');
    expect($typed->description)->toBe('A test post');
    expect($typed->createdAt)->toBeInstanceOf(DateTimeImmutable::class);
    expect($typed->updatedAt)->toBeInstanceOf(DateTimeImmutable::class);
});

test('hydrateFromRecord handles null for nullable fields', function () {
    TypedRecordHydrator::clearCache();

    $record = new Record(
        _id: 'rec-2',
        _data: [
            'title' => 'No Description',
            'status' => 'draft',
            'author_name' => 'Jane Doe',
            'published_at' => '2024-01-01T00:00:00+00:00',
            'description' => null,
        ],
    );

    $typed = TypedRecordHydrator::hydrateFromRecord(HydratorTestRecord::class, $record);
    assert($typed instanceof HydratorTestRecord);

    expect($typed->description)->toBeNull();
});

test('hydrateFromRecord preserves extra data', function () {
    TypedRecordHydrator::clearCache();

    $record = new Record(
        _id: 'rec-3',
        _data: [
            'title' => 'With Extra',
            'status' => 'draft',
            'author_name' => 'Admin',
            'published_at' => '2024-01-01T00:00:00+00:00',
            'description' => null,
            'extra_field' => 'extra_value',
            'nested' => ['key' => 'val'],
        ],
    );

    $typed = TypedRecordHydrator::hydrateFromRecord(HydratorTestRecord::class, $record);

    expect($typed->_getExtraData())->toBe([
        'extra_field' => 'extra_value',
        'nested' => ['key' => 'val'],
    ]);
});

test('hydrateFromRecord handles nested dot-notation paths', function () {
    TypedRecordHydrator::clearCache();

    $record = new Record(
        _id: 'rec-4',
        _data: [
            'meta' => [
                'tags' => ['php', 'dal'],
                'priority' => 5,
            ],
        ],
    );

    $typed = TypedRecordHydrator::hydrateFromRecord(HydratorNestedRecord::class, $record);
    assert($typed instanceof HydratorNestedRecord);

    expect($typed->tags)->toBe(['php', 'dal']);
    expect($typed->priority)->toBe(5);
});

// -----------------------------------------------------------------
//  dehydration
// -----------------------------------------------------------------

test('dehydrateToArray produces correct array', function () {
    TypedRecordHydrator::clearCache();

    $record = new Record(
        _id: 'rec-5',
        _data: [
            'title' => 'Original',
            'status' => 'draft',
            'author_name' => 'Author',
            'published_at' => '2024-01-01T00:00:00+00:00',
            'description' => 'Desc',
            'extra' => 'kept',
        ],
    );

    $typed = TypedRecordHydrator::hydrateFromRecord(HydratorTestRecord::class, $record);
    assert($typed instanceof HydratorTestRecord);

    // Modify a field
    $typed->title = 'Updated Title';

    /** @var array<string, mixed> $data */
    $data = TypedRecordHydrator::dehydrateToArray($typed);

    expect($data['title'])->toBe('Updated Title');
    expect($data['status'])->toBe('draft');
    expect($data['author_name'])->toBe('Author');
    expect($data['published_at'])->toBe('2024-01-01T00:00:00+00:00');
    expect($data['description'])->toBe('Desc');
    expect($data['extra'])->toBe('kept');
});

test('dehydrateToArray handles nested paths', function () {
    TypedRecordHydrator::clearCache();

    $record = new Record(
        _id: 'rec-6',
        _data: [
            'meta' => [
                'tags' => ['a'],
                'priority' => 1,
                'other' => 'preserved',
            ],
        ],
    );

    $typed = TypedRecordHydrator::hydrateFromRecord(HydratorNestedRecord::class, $record);
    assert($typed instanceof HydratorNestedRecord);
    $typed->tags = ['a', 'b', 'c'];

    /** @var array<string, mixed> $data */
    $data = TypedRecordHydrator::dehydrateToArray($typed);

    $meta = $data['meta'];
    assert(is_array($meta));
    expect($meta['tags'])->toBe(['a', 'b', 'c']);
    expect($meta['priority'])->toBe(1);
    expect($meta['other'])->toBe('preserved');
});

test('dehydrateToArray converts enum back to storage value', function () {
    TypedRecordHydrator::clearCache();

    $record = new Record(
        _id: 'rec-7',
        _data: [
            'title' => 'Enum Test',
            'status' => 'draft',
            'author_name' => 'Author',
            'published_at' => '2024-01-01T00:00:00+00:00',
            'description' => null,
        ],
    );

    $typed = TypedRecordHydrator::hydrateFromRecord(HydratorTestRecord::class, $record);
    assert($typed instanceof HydratorTestRecord);
    $typed->status = HydratorTestStatus::Published;

    /** @var array<string, mixed> $data */
    $data = TypedRecordHydrator::dehydrateToArray($typed);

    expect($data['status'])->toBe('published');
});

// -----------------------------------------------------------------
//  getRawField
// -----------------------------------------------------------------

test('getRawField accesses extra data', function () {
    TypedRecordHydrator::clearCache();

    $record = new Record(
        _id: 'rec-8',
        _data: [
            'title' => 'Raw Test',
            'status' => 'draft',
            'author_name' => 'Author',
            'published_at' => '2024-01-01T00:00:00+00:00',
            'description' => null,
            'nested' => ['deep' => ['value' => 42]],
        ],
    );

    $typed = TypedRecordHydrator::hydrateFromRecord(HydratorTestRecord::class, $record);
    assert($typed instanceof HydratorTestRecord);

    // getRawField is protected, so we test via reflection
    $ref = new ReflectionMethod($typed, 'getRawField');

    expect($ref->invoke($typed, 'nested.deep.value'))->toBe(42);
    expect($ref->invoke($typed, 'nonexistent', 'default'))->toBe('default');
});
