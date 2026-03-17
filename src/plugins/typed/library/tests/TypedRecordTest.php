<?php

declare(strict_types=1);

use KDuma\SimpleDAL\Record;
use KDuma\SimpleDAL\Typed\Contracts\Attribute\Field;
use KDuma\SimpleDAL\Typed\Contracts\TypedRecord;
use KDuma\SimpleDAL\Typed\Converter\DateTimeConverter;
use KDuma\SimpleDAL\Typed\TypedRecordHydrator;

// -----------------------------------------------------------------
//  Test fixtures
// -----------------------------------------------------------------

enum RecordTestRole: string
{
    case Admin = 'admin';
    case User = 'user';
    case Guest = 'guest';
}

class UserTypedRecord extends TypedRecord
{
    #[Field]
    public string $name;

    #[Field]
    public string $email;

    #[Field]
    public RecordTestRole $role;

    #[Field]
    public int $age;

    #[Field(converter: DateTimeConverter::class)]
    public DateTimeImmutable $registeredAt;

    #[Field]
    public ?string $bio;

    #[Field(path: 'settings.theme')]
    public string $theme;
}

// -----------------------------------------------------------------
//  TypedRecord tests
// -----------------------------------------------------------------

test('typed record hydrates all field types correctly', function () {
    TypedRecordHydrator::clearCache();

    $record = new Record(
        _id: 'user-1',
        _data: [
            'name' => 'Alice',
            'email' => 'alice@example.com',
            'role' => 'admin',
            'age' => 30,
            'registered_at' => '2024-03-15T12:00:00+00:00',
            'bio' => 'Software engineer',
            'settings' => ['theme' => 'dark'],
        ],
        _createdAt: new DateTimeImmutable('2024-03-15T12:00:00+00:00'),
        _updatedAt: new DateTimeImmutable('2024-06-01T08:00:00+00:00'),
    );

    $typed = TypedRecordHydrator::hydrateFromRecord(UserTypedRecord::class, $record);
    assert($typed instanceof UserTypedRecord);

    expect($typed)->toBeInstanceOf(UserTypedRecord::class);
    expect($typed->id)->toBe('user-1');
    expect($typed->name)->toBe('Alice');
    expect($typed->email)->toBe('alice@example.com');
    expect($typed->role)->toBe(RecordTestRole::Admin);
    expect($typed->age)->toBe(30);
    expect($typed->registeredAt)->toBeInstanceOf(DateTimeImmutable::class);
    expect($typed->registeredAt->format('Y-m-d'))->toBe('2024-03-15');
    expect($typed->bio)->toBe('Software engineer');
    expect($typed->theme)->toBe('dark');
    assert($typed->createdAt !== null);
    expect($typed->createdAt->format('Y-m-d'))->toBe('2024-03-15');
    assert($typed->updatedAt !== null);
    expect($typed->updatedAt->format('Y-m-d'))->toBe('2024-06-01');
});

test('typed record handles nullable bio as null', function () {
    TypedRecordHydrator::clearCache();

    $record = new Record(
        _id: 'user-2',
        _data: [
            'name' => 'Bob',
            'email' => 'bob@example.com',
            'role' => 'user',
            'age' => 25,
            'registered_at' => '2024-01-01T00:00:00+00:00',
            'bio' => null,
            'settings' => ['theme' => 'light'],
        ],
    );

    $typed = TypedRecordHydrator::hydrateFromRecord(UserTypedRecord::class, $record);
    assert($typed instanceof UserTypedRecord);

    expect($typed->bio)->toBeNull();
});

test('typed record dehydration produces correct array', function () {
    TypedRecordHydrator::clearCache();

    $record = new Record(
        _id: 'user-3',
        _data: [
            'name' => 'Charlie',
            'email' => 'charlie@example.com',
            'role' => 'guest',
            'age' => 22,
            'registered_at' => '2024-02-01T00:00:00+00:00',
            'bio' => null,
            'settings' => ['theme' => 'auto', 'language' => 'en'],
        ],
    );

    $typed = TypedRecordHydrator::hydrateFromRecord(UserTypedRecord::class, $record);
    assert($typed instanceof UserTypedRecord);

    // Modify fields
    $typed->name = 'Charles';
    $typed->role = RecordTestRole::Admin;

    /** @var array<string, mixed> $data */
    $data = TypedRecordHydrator::dehydrateToArray($typed);

    expect($data['name'])->toBe('Charles');
    expect($data['role'])->toBe('admin');
    expect($data['email'])->toBe('charlie@example.com');
    expect($data['age'])->toBe(22);
    $settings = $data['settings'];
    assert(is_array($settings));
    expect($settings['theme'])->toBe('auto');
    expect($settings['language'])->toBe('en');
});

test('typed record getRawField works for extra data', function () {
    TypedRecordHydrator::clearCache();

    $record = new Record(
        _id: 'user-4',
        _data: [
            'name' => 'Diana',
            'email' => 'diana@example.com',
            'role' => 'user',
            'age' => 28,
            'registered_at' => '2024-01-15T00:00:00+00:00',
            'bio' => null,
            'settings' => ['theme' => 'dark', 'language' => 'fr'],
            'metadata' => ['source' => 'api'],
        ],
    );

    $typed = TypedRecordHydrator::hydrateFromRecord(UserTypedRecord::class, $record);
    assert($typed instanceof UserTypedRecord);

    // Access getRawField via reflection (it's protected)
    $ref = new ReflectionMethod($typed, 'getRawField');

    expect($ref->invoke($typed, 'metadata.source'))->toBe('api');
    expect($ref->invoke($typed, 'settings.language'))->toBe('fr');
    expect($ref->invoke($typed, 'nonexistent', 'fallback'))->toBe('fallback');
});

test('typed record setRawField works for extra data', function () {
    TypedRecordHydrator::clearCache();

    $record = new Record(
        _id: 'user-5',
        _data: [
            'name' => 'Eve',
            'email' => 'eve@example.com',
            'role' => 'user',
            'age' => 35,
            'registered_at' => '2024-01-01T00:00:00+00:00',
            'bio' => null,
            'settings' => ['theme' => 'light'],
        ],
    );

    $typed = TypedRecordHydrator::hydrateFromRecord(UserTypedRecord::class, $record);
    assert($typed instanceof UserTypedRecord);

    // Access setRawField and getRawField via reflection (protected)
    $setRef = new ReflectionMethod($typed, 'setRawField');
    $getRef = new ReflectionMethod($typed, 'getRawField');

    $setRef->invoke($typed, 'metadata.source', 'import');

    expect($getRef->invoke($typed, 'metadata.source'))->toBe('import');
});

test('typed record preserves timestamps from null', function () {
    TypedRecordHydrator::clearCache();

    $record = new Record(
        _id: 'user-6',
        _data: [
            'name' => 'Frank',
            'email' => 'frank@example.com',
            'role' => 'guest',
            'age' => 40,
            'registered_at' => '2024-05-01T00:00:00+00:00',
            'bio' => null,
            'settings' => ['theme' => 'dark'],
        ],
        _createdAt: null,
        _updatedAt: null,
    );

    $typed = TypedRecordHydrator::hydrateFromRecord(UserTypedRecord::class, $record);
    assert($typed instanceof UserTypedRecord);

    expect($typed->createdAt)->toBeNull();
    expect($typed->updatedAt)->toBeNull();
});
