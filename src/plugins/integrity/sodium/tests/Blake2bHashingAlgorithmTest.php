<?php

declare(strict_types=1);

use KDuma\SimpleDAL\Integrity\Sodium\Blake2bHashingAlgorithm;

test('hash returns 32 bytes', function () {
    $algo = new Blake2bHashingAlgorithm;

    $hash = $algo->hash('hello world');

    expect(strlen($hash))->toBe(SODIUM_CRYPTO_GENERICHASH_BYTES);
});

test('same input produces same hash', function () {
    $algo = new Blake2bHashingAlgorithm;

    $a = $algo->hash('deterministic');
    $b = $algo->hash('deterministic');

    expect($a)->toBe($b);
});

test('different input produces different hash', function () {
    $algo = new Blake2bHashingAlgorithm;

    $a = $algo->hash('input one');
    $b = $algo->hash('input two');

    expect($a)->not->toBe($b);
});

test('algorithm constant is 1', function () {
    $algo = new Blake2bHashingAlgorithm;

    expect($algo->algorithm)->toBe(1);
    expect(Blake2bHashingAlgorithm::ALGORITHM)->toBe(1);
});
