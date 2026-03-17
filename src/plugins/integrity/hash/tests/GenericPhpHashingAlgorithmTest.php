<?php

declare(strict_types=1);

use KDuma\SimpleDAL\Integrity\Hash\Hasher\Crc32HashingAlgorithm;
use KDuma\SimpleDAL\Integrity\Hash\Hasher\GenericPhpHashingAlgorithm;
use KDuma\SimpleDAL\Integrity\Hash\Hasher\Md5HashingAlgorithm;
use KDuma\SimpleDAL\Integrity\Hash\Hasher\Sha1HashingAlgorithm;
use KDuma\SimpleDAL\Integrity\Hash\Hasher\Sha256HashingAlgorithm;
use KDuma\SimpleDAL\Integrity\Hash\Hasher\Sha3_256HashingAlgorithm;
use KDuma\SimpleDAL\Integrity\Hash\Hasher\Sha512HashingAlgorithm;

test('sha256 hash produces 32 bytes', function () {
    $hasher = new GenericPhpHashingAlgorithm('sha256', 1);

    $hash = $hasher->hash('hello world');

    expect(strlen($hash))->toBe(32);
});

test('same input produces same hash', function () {
    $hasher = new GenericPhpHashingAlgorithm('sha256', 1);

    $a = $hasher->hash('hello world');
    $b = $hasher->hash('hello world');

    expect($a)->toBe($b);
});

test('different input produces different hash', function () {
    $hasher = new GenericPhpHashingAlgorithm('sha256', 1);

    $a = $hasher->hash('hello');
    $b = $hasher->hash('world');

    expect($a)->not->toBe($b);
});

test('unknown algorithm throws InvalidArgumentException', function () {
    new GenericPhpHashingAlgorithm('not-a-real-algo', 1);
})->throws(InvalidArgumentException::class);

test('algorithm ID matches constructor parameter', function () {
    $hasher = new GenericPhpHashingAlgorithm('sha256', 42);

    expect($hasher->algorithm)->toBe(42);
});

test('binary vs hex mode works', function () {
    $binary = new GenericPhpHashingAlgorithm('sha256', 1, binary: true);
    $hex = new GenericPhpHashingAlgorithm('sha256', 1, binary: false);

    $binaryHash = $binary->hash('test');
    $hexHash = $hex->hash('test');

    expect(strlen($binaryHash))->toBe(32);
    expect(strlen($hexHash))->toBe(64);
    expect($hexHash)->toBe(hash('sha256', 'test', binary: false));
});

test('convenience class Sha256HashingAlgorithm has algorithm 131 and produces correct hash', function () {
    $hasher = new Sha256HashingAlgorithm;

    expect($hasher->algorithm)->toBe(131);
    expect($hasher->hash('test'))->toBe(hash('sha256', 'test', binary: true));
});

test('convenience class Crc32HashingAlgorithm works', function () {
    $hasher = new Crc32HashingAlgorithm;

    expect($hasher->algorithm)->toBe(128);
    expect($hasher->hash('test'))->toBe(hash('crc32', 'test', binary: true));
});

test('convenience class Md5HashingAlgorithm works', function () {
    $hasher = new Md5HashingAlgorithm;

    expect($hasher->algorithm)->toBe(129);
    expect($hasher->hash('test'))->toBe(hash('md5', 'test', binary: true));
});

test('convenience class Sha1HashingAlgorithm works', function () {
    $hasher = new Sha1HashingAlgorithm;

    expect($hasher->algorithm)->toBe(130);
    expect($hasher->hash('test'))->toBe(hash('sha1', 'test', binary: true));
});

test('convenience class Sha512HashingAlgorithm works', function () {
    $hasher = new Sha512HashingAlgorithm;

    expect($hasher->algorithm)->toBe(132);
    expect($hasher->hash('test'))->toBe(hash('sha512', 'test', binary: true));
});

test('convenience class Sha3_256HashingAlgorithm works', function () {
    $hasher = new Sha3_256HashingAlgorithm;

    expect($hasher->algorithm)->toBe(133);
    expect($hasher->hash('test'))->toBe(hash('sha3-256', 'test', binary: true));
});
