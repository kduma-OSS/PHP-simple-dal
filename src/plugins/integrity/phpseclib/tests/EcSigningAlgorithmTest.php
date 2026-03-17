<?php

declare(strict_types=1);

use KDuma\SimpleDAL\Integrity\PhpSecLib\EcSigningAlgorithm;
use phpseclib3\Crypt\EC;
use phpseclib3\Crypt\EC\PublicKey;

test('sign and verify round-trip with Ed25519', function () {
    $privateKey = EC::createKey('Ed25519');

    $algo = new EcSigningAlgorithm('ed25519-key', $privateKey);

    $signature = $algo->sign('hello world');
    $valid = $algo->verify('hello world', $signature);

    expect($valid)->toBeTrue();
});

test('sign and verify round-trip with P-256 (ECDSA)', function () {
    $privateKey = EC::createKey('secp256r1');

    $algo = new EcSigningAlgorithm('p256-key', $privateKey);

    $signature = $algo->sign('ecdsa test');
    $valid = $algo->verify('ecdsa test', $signature);

    expect($valid)->toBeTrue();
});

test('verify-only mode throws on sign', function () {
    $privateKey = EC::createKey('Ed25519');
    $publicKey = $privateKey->getPublicKey();
    assert($publicKey instanceof PublicKey);

    $algo = new EcSigningAlgorithm('ec-key', $publicKey);

    $algo->sign('hello');
})->throws(RuntimeException::class, 'verify-only');

test('algorithm constant is 4', function () {
    $privateKey = EC::createKey('Ed25519');
    $algo = new EcSigningAlgorithm('test', $privateKey);

    expect($algo->algorithm)->toBe(4);
});
