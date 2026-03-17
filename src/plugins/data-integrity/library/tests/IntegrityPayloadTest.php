<?php

declare(strict_types=1);

use KDuma\SimpleDAL\Contracts\Exception\CorruptedDataException;
use KDuma\SimpleDAL\DataIntegrity\IntegrityPayload;

test('encode and decode round-trip with hash only', function () {
    $hash = hash('sha256', 'test content', binary: true);

    $encoded = IntegrityPayload::encode('test content', hash: $hash, hashAlgorithm: 0x01);
    $decoded = IntegrityPayload::decode($encoded);

    expect($decoded->hash)->toBe($hash);
    expect($decoded->hashAlgorithm)->toBe(0x01);
    expect($decoded->payload)->toBe('test content');
    expect($decoded->signingAlgorithm)->toBeNull();
    expect($decoded->keyId)->toBeNull();
    expect($decoded->signature)->toBeNull();
});

test('encode and decode round-trip with hash and signature', function () {
    $hash = hash('sha256', 'signed content', binary: true);
    $signature = hash('sha256', 'signature-data', binary: true);

    $encoded = IntegrityPayload::encode(
        'signed content',
        hash: $hash,
        hashAlgorithm: 0x01,
        signingAlgorithm: 0x02,
        keyId: 'my-signing-key',
        signature: $signature,
    );
    $decoded = IntegrityPayload::decode($encoded);

    expect($decoded->hash)->toBe($hash);
    expect($decoded->hashAlgorithm)->toBe(0x01);
    expect($decoded->payload)->toBe('signed content');
    expect($decoded->signingAlgorithm)->toBe(0x02);
    expect($decoded->keyId)->toBe('my-signing-key');
    expect($decoded->signature)->toBe($signature);
});

test('encode and decode round-trip with signature only (no hash)', function () {
    $signature = hash('sha256', 'sig-data', binary: true);

    $encoded = IntegrityPayload::encode(
        'content',
        signingAlgorithm: 0x10,
        keyId: 'sign-key',
        signature: $signature,
    );
    $decoded = IntegrityPayload::decode($encoded);

    expect($decoded->hash)->toBeNull();
    expect($decoded->hashAlgorithm)->toBeNull();
    expect($decoded->payload)->toBe('content');
    expect($decoded->signingAlgorithm)->toBe(0x10);
    expect($decoded->keyId)->toBe('sign-key');
    expect($decoded->signature)->toBe($signature);
});

test('hasIntegrity returns true for valid data', function () {
    $hash = hash('sha256', 'data', binary: true);
    $encoded = IntegrityPayload::encode('data', hash: $hash, hashAlgorithm: 0x01);

    expect(IntegrityPayload::hasIntegrity($encoded))->toBeTrue();
});

test('hasIntegrity returns false for random data', function () {
    expect(IntegrityPayload::hasIntegrity('hello world'))->toBeFalse();
    expect(IntegrityPayload::hasIntegrity('random binary data'))->toBeFalse();
    expect(IntegrityPayload::hasIntegrity('{"json": "data"}'))->toBeFalse();
});

test('hasIntegrity returns false for short data', function () {
    expect(IntegrityPayload::hasIntegrity(''))->toBeFalse();
    expect(IntegrityPayload::hasIntegrity('SDIC'))->toBeFalse();
    expect(IntegrityPayload::hasIntegrity("SDIC\x00"))->toBeFalse();
});

test('decode throws on data too short', function () {
    IntegrityPayload::decode('short');
})->throws(CorruptedDataException::class, 'missing magic header');

test('decode throws on wrong magic', function () {
    IntegrityPayload::decode("XXXX\x00\x01\x00\x00\x00\x00\x00");
})->throws(CorruptedDataException::class, 'missing magic header');

test('decode throws on wrong version', function () {
    $data = "SDIC\x00".chr(99).chr(0x00);
    IntegrityPayload::decode($data);
})->throws(CorruptedDataException::class, 'Unsupported integrity version');

test('decode handles long key IDs', function () {
    $hash = hash('sha256', 'content', binary: true);
    $longKeyId = str_repeat('k', 1000);
    $signature = hash('sha256', 'sig', binary: true);

    $encoded = IntegrityPayload::encode(
        'content',
        hash: $hash,
        hashAlgorithm: 0x01,
        signingAlgorithm: 0x02,
        keyId: $longKeyId,
        signature: $signature,
    );
    $decoded = IntegrityPayload::decode($encoded);

    expect($decoded->keyId)->toBe($longKeyId);
    expect($decoded->payload)->toBe('content');
});

test('decode handles empty payload', function () {
    $hash = hash('sha256', '', binary: true);

    $encoded = IntegrityPayload::encode('', hash: $hash, hashAlgorithm: 0x01);
    $decoded = IntegrityPayload::decode($encoded);

    expect($decoded->payload)->toBe('');
    expect($decoded->hash)->toBe($hash);
});
