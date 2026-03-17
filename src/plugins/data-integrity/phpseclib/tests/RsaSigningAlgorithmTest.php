<?php

declare(strict_types=1);

use KDuma\SimpleDAL\DataIntegrity\PhpSecLib\RsaSigningAlgorithm;
use phpseclib3\Crypt\RSA;

test('sign and verify round-trip with RSA', function () {
    $privateKey = RSA::createKey(2048);

    $algo = new RsaSigningAlgorithm('rsa-key', $privateKey);

    $signature = $algo->sign('hello world');
    $valid = $algo->verify('hello world', $signature);

    expect($valid)->toBeTrue();
});

test('verify-only mode throws on sign', function () {
    $privateKey = RSA::createKey(2048);
    $publicKey = $privateKey->getPublicKey();

    $algo = new RsaSigningAlgorithm('rsa-key', $publicKey);

    $algo->sign('hello');
})->throws(RuntimeException::class, 'verify-only');

test('wrong key fails verification', function () {
    $pk1 = RSA::createKey(2048);
    $pk2 = RSA::createKey(2048);

    $algo1 = new RsaSigningAlgorithm('k1', $pk1);
    $algo2 = new RsaSigningAlgorithm('k2', $pk2);

    $signature = $algo1->sign('secret');
    $valid = $algo2->verify('secret', $signature);

    expect($valid)->toBeFalse();
});

test('algorithm constant is 2', function () {
    $privateKey = RSA::createKey(2048);
    $algo = new RsaSigningAlgorithm('test', $privateKey);

    expect($algo->algorithm)->toBe(2);
});

test('works with PSS padding', function () {
    $privateKey = RSA::createKey(2048)->withPadding(RSA::SIGNATURE_PSS);

    $algo = new RsaSigningAlgorithm('rsa-pss', $privateKey);

    $signature = $algo->sign('pss test');
    $valid = $algo->verify('pss test', $signature);

    expect($valid)->toBeTrue();
});
