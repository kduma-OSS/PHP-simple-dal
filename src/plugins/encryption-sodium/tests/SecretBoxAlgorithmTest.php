<?php

declare(strict_types=1);

use KDuma\SimpleDAL\Encryption\Contracts\Exception\DecryptionException;
use KDuma\SimpleDAL\Encryption\Sodium\SecretBoxAlgorithm;

test('encrypt and decrypt round-trip', function () {
    $key = new SecretBoxAlgorithm('test', sodium_crypto_secretbox_keygen());

    $ciphertext = $key->encrypt('hello world');
    $plaintext = $key->decrypt($ciphertext);

    expect($plaintext)->toBe('hello world');
});

test('different encryptions produce different ciphertexts', function () {
    $key = new SecretBoxAlgorithm('test', sodium_crypto_secretbox_keygen());

    $a = $key->encrypt('same');
    $b = $key->encrypt('same');

    expect($a)->not->toBe($b);
});

test('wrong key fails decryption', function () {
    $key1 = new SecretBoxAlgorithm('k1', sodium_crypto_secretbox_keygen());
    $key2 = new SecretBoxAlgorithm('k2', sodium_crypto_secretbox_keygen());

    $ciphertext = $key1->encrypt('secret');
    $key2->decrypt($ciphertext);
})->throws(DecryptionException::class);

test('rejects invalid key length', function () {
    new SecretBoxAlgorithm('bad', 'too-short');
})->throws(InvalidArgumentException::class);

test('algorithm constant is 1', function () {
    $key = new SecretBoxAlgorithm('test', sodium_crypto_secretbox_keygen());

    expect($key->algorithm)->toBe(1);
    expect(SecretBoxAlgorithm::ALGORITHM)->toBe(1);
});
