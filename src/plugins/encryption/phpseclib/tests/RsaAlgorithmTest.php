<?php

declare(strict_types=1);

use KDuma\SimpleDAL\Encryption\Contracts\Exception\DecryptionException;
use KDuma\SimpleDAL\Encryption\PhpSecLib\RsaAlgorithm;
use phpseclib3\Crypt\RSA;
use phpseclib3\Crypt\RSA\PrivateKey;
use phpseclib3\Crypt\RSA\PublicKey;

test('encrypt and decrypt round-trip with RSA OAEP', function () {
    $privateKey = RSA::createKey(2048);

    $key = new RsaAlgorithm('rsa-key', $privateKey);

    $ciphertext = $key->encrypt('secret message');
    $plaintext = $key->decrypt($ciphertext);

    expect($plaintext)->toBe('secret message');
});

test('encrypt-only mode throws on decrypt', function () {
    $privateKey = RSA::createKey(2048);
    $publicKey = $privateKey->getPublicKey();
    assert($publicKey instanceof PublicKey);

    $key = new RsaAlgorithm('rsa-key', $publicKey);

    $ciphertext = $key->encrypt('sealed');
    $key->decrypt($ciphertext);
})->throws(DecryptionException::class, 'encrypt-only');

test('wrong key fails decryption', function () {
    $pk1 = RSA::createKey(2048);
    $pk2 = RSA::createKey(2048);

    $key1 = new RsaAlgorithm('k1', $pk1);
    $key2 = new RsaAlgorithm('k2', $pk2);

    $ciphertext = $key1->encrypt('secret');
    $key2->decrypt($ciphertext);
})->throws(DecryptionException::class);

test('encrypt and decrypt with PKCS1 padding', function () {
    $privateKey = RSA::createKey(2048)->withPadding(RSA::ENCRYPTION_PKCS1);
    assert($privateKey instanceof PrivateKey);

    $key = new RsaAlgorithm('rsa-pkcs1', $privateKey);

    $ciphertext = $key->encrypt('pkcs1 test');
    $plaintext = $key->decrypt($ciphertext);

    expect($plaintext)->toBe('pkcs1 test');
});

test('algorithm constant is 4', function () {
    $privateKey = RSA::createKey(2048);
    $key = new RsaAlgorithm('test', $privateKey);

    expect($key->algorithm)->toBe(4);
    expect(RsaAlgorithm::ALGORITHM)->toBe(4);
});

test('decrypt rejects payload shorter than 2 bytes', function () {
    $privateKey = RSA::createKey(2048);
    $key = new RsaAlgorithm('test', $privateKey);

    $key->decrypt("\x00");
})->throws(DecryptionException::class, 'too short');

test('decrypt rejects truncated payload', function () {
    $privateKey = RSA::createKey(2048);
    $key = new RsaAlgorithm('test', $privateKey);

    // Header says key is 256 bytes but payload is too short
    $key->decrypt(pack('n', 256).'short');
})->throws(DecryptionException::class, 'truncated');

test('encrypts large data via hybrid scheme', function () {
    $privateKey = RSA::createKey(2048);
    $key = new RsaAlgorithm('test', $privateKey);

    // Data larger than RSA key size (256 bytes for 2048-bit key)
    $largeData = str_repeat('A', 1000);

    $ciphertext = $key->encrypt($largeData);
    $plaintext = $key->decrypt($ciphertext);

    expect($plaintext)->toBe($largeData);
});
