<?php

declare(strict_types=1);

use KDuma\SimpleDAL\Integrity\PhpSecLib\DsaSigningAlgorithm;
use phpseclib3\Crypt\DSA;
use phpseclib3\Crypt\DSA\PublicKey;

test('sign and verify round-trip with DSA', function () {
    $privateKey = DSA::createKey();

    $algo = new DsaSigningAlgorithm('dsa-key', $privateKey);

    $signature = $algo->sign('hello world');
    $valid = $algo->verify('hello world', $signature);

    expect($valid)->toBeTrue();
});

test('verify-only mode throws on sign', function () {
    $privateKey = DSA::createKey();
    $publicKey = $privateKey->getPublicKey();
    assert($publicKey instanceof PublicKey);

    $algo = new DsaSigningAlgorithm('dsa-key', $publicKey);

    $algo->sign('hello');
})->throws(RuntimeException::class, 'verify-only');

test('wrong key fails verification', function () {
    $pk1 = DSA::createKey();
    $pk2 = DSA::createKey();

    $algo1 = new DsaSigningAlgorithm('k1', $pk1);
    $algo2 = new DsaSigningAlgorithm('k2', $pk2);

    $signature = $algo1->sign('secret');
    $valid = $algo2->verify('secret', $signature);

    expect($valid)->toBeFalse();
});

test('algorithm constant is 3', function () {
    $privateKey = DSA::createKey();
    $algo = new DsaSigningAlgorithm('test', $privateKey);

    expect($algo->algorithm)->toBe(3);
});
