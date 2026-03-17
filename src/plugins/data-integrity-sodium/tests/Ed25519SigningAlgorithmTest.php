<?php

declare(strict_types=1);

use KDuma\SimpleDAL\DataIntegrity\Sodium\Ed25519SigningAlgorithm;

test('generate creates valid key, sign and verify round-trip', function () {
    $key = Ed25519SigningAlgorithm::generate('test');

    $signature = $key->sign('hello world');
    $valid = $key->verify('hello world', $signature);

    expect($valid)->toBeTrue();
});

test('verify-only mode can verify but throws on sign', function () {
    $full = Ed25519SigningAlgorithm::generate('test');
    $signature = $full->sign('message');

    $kp = sodium_crypto_sign_keypair();
    $verifyOnly = Ed25519SigningAlgorithm::verifyOnly('vo', sodium_crypto_sign_publickey($kp));

    expect($verifyOnly->verify('message', $signature))->toBeFalse();

    $verifyOnly->sign('message');
})->throws(RuntimeException::class, 'verify-only');

test('wrong key fails verification', function () {
    $key1 = Ed25519SigningAlgorithm::generate('k1');
    $key2 = Ed25519SigningAlgorithm::generate('k2');

    $signature = $key1->sign('secret');

    expect($key2->verify('secret', $signature))->toBeFalse();
});

test('algorithm constant is 1', function () {
    $key = Ed25519SigningAlgorithm::generate('test');

    expect($key->algorithm)->toBe(1);
    expect(Ed25519SigningAlgorithm::ALGORITHM)->toBe(1);
});

test('invalid key length throws InvalidArgumentException', function () {
    new Ed25519SigningAlgorithm('bad', null, 'too-short');
})->throws(InvalidArgumentException::class);
