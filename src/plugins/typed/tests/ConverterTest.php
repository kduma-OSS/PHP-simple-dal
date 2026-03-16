<?php

declare(strict_types=1);

use KDuma\SimpleDAL\Typed\Converter\DateTimeConverter;
use KDuma\SimpleDAL\Typed\Converter\EnumConverter;

enum TestStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
}

enum TestPriority: int
{
    case Low = 1;
    case Medium = 2;
    case High = 3;
}

// -----------------------------------------------------------------
//  EnumConverter - string-backed
// -----------------------------------------------------------------

test('enum converter converts string-backed enum from storage', function () {
    $converter = new EnumConverter(TestStatus::class);

    $result = $converter->fromStorage('active');

    expect($result)->toBe(TestStatus::Active);
});

test('enum converter converts string-backed enum to storage', function () {
    $converter = new EnumConverter(TestStatus::class);

    $result = $converter->toStorage(TestStatus::Inactive);

    expect($result)->toBe('inactive');
});

// -----------------------------------------------------------------
//  EnumConverter - int-backed
// -----------------------------------------------------------------

test('enum converter converts int-backed enum from storage', function () {
    $converter = new EnumConverter(TestPriority::class);

    $result = $converter->fromStorage(3);

    expect($result)->toBe(TestPriority::High);
});

test('enum converter converts int-backed enum to storage', function () {
    $converter = new EnumConverter(TestPriority::class);

    $result = $converter->toStorage(TestPriority::Low);

    expect($result)->toBe(1);
});

// -----------------------------------------------------------------
//  DateTimeConverter
// -----------------------------------------------------------------

test('datetime converter converts from ISO string', function () {
    $converter = new DateTimeConverter;

    $result = $converter->fromStorage('2024-06-15T10:30:00+00:00');

    expect($result)->toBeInstanceOf(DateTimeImmutable::class);
    expect($result->format('Y-m-d'))->toBe('2024-06-15');
    expect($result->format('H:i:s'))->toBe('10:30:00');
});

test('datetime converter converts to ISO ATOM string', function () {
    $converter = new DateTimeConverter;
    $dt = new DateTimeImmutable('2024-06-15T10:30:00+00:00');

    $result = $converter->toStorage($dt);

    expect($result)->toBe('2024-06-15T10:30:00+00:00');
});

test('datetime converter roundtrips correctly', function () {
    $converter = new DateTimeConverter;
    $original = '2024-12-25T08:00:00+00:00';

    $dt = $converter->fromStorage($original);
    $back = $converter->toStorage($dt);

    expect($back)->toBe($original);
});
