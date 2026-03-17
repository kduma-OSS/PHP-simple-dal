<?php

declare(strict_types=1);

use KDuma\SimpleDAL\Encryption\Contracts\Exception\DecryptionException;
use KDuma\SimpleDAL\Encryption\PhpSecLib\RsaAlgorithm;
use phpseclib3\Crypt\RSA;

test('encrypt and decrypt round-trip with RSA OAEP', function () {
    $privateKey = RSA::createKey(2048);
    $publicKey = $privateKey->getPublicKey();

    $key = new RsaAlgorithm('rsa-key', $publicKey, $privateKey);

    $ciphertext = $key->encrypt('secret message');
    $plaintext = $key->decrypt($ciphertext);

    expect($plaintext)->toBe('secret message');
});

test('encrypt-only mode throws on decrypt', function () {
    $privateKey = RSA::createKey(2048);
    $publicKey = $privateKey->getPublicKey();

    $key = new RsaAlgorithm('rsa-key', $publicKey);

    $ciphertext = $key->encrypt('sealed');
    $key->decrypt($ciphertext);
})->throws(DecryptionException::class, 'encrypt-only');

test('wrong key fails decryption', function () {
    $pk1 = RSA::createKey(2048);
    $pk2 = RSA::createKey(2048);

    $key1 = new RsaAlgorithm('k1', $pk1->getPublicKey(), $pk1);
    $key2 = new RsaAlgorithm('k2', $pk2->getPublicKey(), $pk2);

    $ciphertext = $key1->encrypt('secret');
    $key2->decrypt($ciphertext);
})->throws(DecryptionException::class);

test('encrypt and decrypt with PKCS1 padding', function () {
    $privateKey = RSA::createKey(2048)->withPadding(RSA::ENCRYPTION_PKCS1);
    $publicKey = $privateKey->getPublicKey()->withPadding(RSA::ENCRYPTION_PKCS1);

    $key = new RsaAlgorithm('rsa-pkcs1', $publicKey, $privateKey);

    $ciphertext = $key->encrypt('pkcs1 test');
    $plaintext = $key->decrypt($ciphertext);

    expect($plaintext)->toBe('pkcs1 test');
});

test('algorithm constant is 4', function () {
    $privateKey = RSA::createKey(2048);
    $key = new RsaAlgorithm('test', $privateKey->getPublicKey(), $privateKey);

    expect($key->algorithm)->toBe(4);
    expect(RsaAlgorithm::ALGORITHM)->toBe(4);
});
