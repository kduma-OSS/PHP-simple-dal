<?php

declare(strict_types=1);

use KDuma\SimpleDAL\DataIntegrity\Hash\Signer\GenericHmacSigningAlgorithm;
use KDuma\SimpleDAL\DataIntegrity\Hash\Signer\HmacSha256SigningAlgorithm;

test('sign and verify round-trip', function () {
    $signer = new GenericHmacSigningAlgorithm('key1', 'my-secret-key');

    $signature = $signer->sign('hello world');

    expect($signer->verify('hello world', $signature))->toBeTrue();
});

test('wrong key fails verification', function () {
    $signer1 = new GenericHmacSigningAlgorithm('k1', 'secret-one');
    $signer2 = new GenericHmacSigningAlgorithm('k2', 'secret-two');

    $signature = $signer1->sign('hello world');

    expect($signer2->verify('hello world', $signature))->toBeFalse();
});

test('wrong message fails verification', function () {
    $signer = new GenericHmacSigningAlgorithm('key1', 'my-secret-key');

    $signature = $signer->sign('hello world');

    expect($signer->verify('different message', $signature))->toBeFalse();
});

test('algorithm ID matches constructor parameter', function () {
    $signer = new GenericHmacSigningAlgorithm('key1', 'secret', algorithmId: 77);

    expect($signer->algorithm)->toBe(77);
});

test('unknown HMAC algorithm throws InvalidArgumentException', function () {
    new GenericHmacSigningAlgorithm('key1', 'secret', algo: 'not-a-real-algo');
})->throws(InvalidArgumentException::class);

test('convenience class HmacSha256SigningAlgorithm works', function () {
    $signer = new HmacSha256SigningAlgorithm('key1', 'my-secret');

    expect($signer->algorithm)->toBe(129);
    expect($signer->id)->toBe('key1');

    $signature = $signer->sign('test message');

    expect($signer->verify('test message', $signature))->toBeTrue();
    expect($signer->verify('wrong message', $signature))->toBeFalse();
});
