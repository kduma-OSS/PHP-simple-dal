<?php

declare(strict_types=1);

use KDuma\SimpleDAL\Encryption\Contracts\Exception\DecryptionException;
use KDuma\SimpleDAL\Encryption\Sodium\KeyPair;

test('encrypt and decrypt round-trip', function () {
    $kp = sodium_crypto_box_keypair();
    $key = new KeyPair('test', sodium_crypto_box_publickey($kp), sodium_crypto_box_secretkey($kp));

    $ciphertext = $key->encrypt('sealed message');
    $plaintext = $key->decrypt($ciphertext);

    expect($plaintext)->toBe('sealed message');
});

test('encrypt-only mode throws on decrypt', function () {
    $kp = sodium_crypto_box_keypair();
    $key = new KeyPair('test', sodium_crypto_box_publickey($kp));

    $ciphertext = $key->encrypt('sealed');
    $key->decrypt($ciphertext);
})->throws(DecryptionException::class, 'encrypt-only');

test('wrong key fails decryption', function () {
    $kp1 = sodium_crypto_box_keypair();
    $kp2 = sodium_crypto_box_keypair();

    $key1 = new KeyPair('k1', sodium_crypto_box_publickey($kp1), sodium_crypto_box_secretkey($kp1));
    $key2 = new KeyPair('k2', sodium_crypto_box_publickey($kp2), sodium_crypto_box_secretkey($kp2));

    $ciphertext = $key1->encrypt('secret');
    $key2->decrypt($ciphertext);
})->throws(DecryptionException::class);

test('algorithm constant is 2', function () {
    $kp = sodium_crypto_box_keypair();
    $key = new KeyPair('test', sodium_crypto_box_publickey($kp));

    expect($key->algorithm)->toBe(2);
    expect(KeyPair::ALGORITHM)->toBe(2);
});
