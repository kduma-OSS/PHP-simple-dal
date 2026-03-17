<?php

declare(strict_types=1);

use KDuma\SimpleDAL\Encryption\Contracts\Exception\DecryptionException;
use KDuma\SimpleDAL\Encryption\EncryptedPayload;
use KDuma\SimpleDAL\Encryption\Sodium\SymmetricKey;

test('encode and decode round-trip with secretbox algorithm', function () {
    $encoded = EncryptedPayload::encode('my-key', SymmetricKey::ALGORITHM, 'encrypted-data');
    $decoded = EncryptedPayload::decode($encoded);

    expect($decoded->keyId)->toBe('my-key');
    expect($decoded->algorithm)->toBe(SymmetricKey::ALGORITHM);
    expect($decoded->payload)->toBe('encrypted-data');
});

test('encode and decode round-trip with sealed box algorithm', function () {
    $encoded = EncryptedPayload::encode('sealed-key', 2, 'sealed-data');
    $decoded = EncryptedPayload::decode($encoded);

    expect($decoded->keyId)->toBe('sealed-key');
    expect($decoded->algorithm)->toBe(2);
    expect($decoded->payload)->toBe('sealed-data');
});

test('isEncrypted returns true for encrypted data', function () {
    $encoded = EncryptedPayload::encode('key', SymmetricKey::ALGORITHM, 'data');

    expect(EncryptedPayload::isEncrypted($encoded))->toBeTrue();
});

test('isEncrypted returns false for plain data', function () {
    expect(EncryptedPayload::isEncrypted('hello world'))->toBeFalse();
    expect(EncryptedPayload::isEncrypted('-----BEGIN CERTIFICATE-----'))->toBeFalse();
});

test('isEncrypted returns false for short data', function () {
    expect(EncryptedPayload::isEncrypted(''))->toBeFalse();
    expect(EncryptedPayload::isEncrypted('SDAL'))->toBeFalse();
});

test('decode throws on non-encrypted data', function () {
    EncryptedPayload::decode('not encrypted');
})->throws(DecryptionException::class);

test('decode handles long key IDs', function () {
    $longKeyId = str_repeat('k', 1000);
    $encoded = EncryptedPayload::encode($longKeyId, SymmetricKey::ALGORITHM, 'data');
    $decoded = EncryptedPayload::decode($encoded);

    expect($decoded->keyId)->toBe($longKeyId);
});
