<?php

declare(strict_types=1);

use KDuma\SimpleDAL\DataIntegrity\Sodium\Ed25519SigningAlgorithm;

function createTestEd25519Key(string $id = 'test'): Ed25519SigningAlgorithm
{
    $keypair = sodium_crypto_sign_keypair();

    return new Ed25519SigningAlgorithm(
        $id,
        sodium_crypto_sign_secretkey($keypair),
        sodium_crypto_sign_publickey($keypair),
    );
}

test('sign and verify round-trip', function () {
    $key = createTestEd25519Key();

    $signature = $key->sign('hello world');
    $valid = $key->verify('hello world', $signature);

    expect($valid)->toBeTrue();
});

test('verify-only mode can verify but throws on sign', function () {
    $kp = sodium_crypto_sign_keypair();
    $verifyOnly = Ed25519SigningAlgorithm::verifyOnly('vo', sodium_crypto_sign_publickey($kp));

    $verifyOnly->sign('message');
})->throws(RuntimeException::class, 'verify-only');

test('wrong key fails verification', function () {
    $key1 = createTestEd25519Key('k1');
    $key2 = createTestEd25519Key('k2');

    $signature = $key1->sign('secret');

    expect($key2->verify('secret', $signature))->toBeFalse();
});

test('algorithm constant is 1', function () {
    $key = createTestEd25519Key();

    expect($key->algorithm)->toBe(1);
    expect(Ed25519SigningAlgorithm::ALGORITHM)->toBe(1);
});

test('invalid key length throws InvalidArgumentException', function () {
    new Ed25519SigningAlgorithm('bad', null, 'too-short');
})->throws(InvalidArgumentException::class);
