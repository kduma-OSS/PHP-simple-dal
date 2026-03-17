<?php

declare(strict_types=1);

use KDuma\SimpleDAL\Encryption\PhpSecLib\AesAlgorithm;
use phpseclib3\Crypt\AES;

test('encrypt and decrypt round-trip with AES-CTR', function () {
    $cipher = new AES('ctr');
    $cipher->setKey(random_bytes(32));

    $key = new AesAlgorithm('aes-key', $cipher);

    $ciphertext = $key->encrypt('hello world');
    $plaintext = $key->decrypt($ciphertext);

    expect($plaintext)->toBe('hello world');
});

test('different encryptions produce different ciphertexts', function () {
    $cipher = new AES('ctr');
    $cipher->setKey(random_bytes(32));

    $key = new AesAlgorithm('aes-key', $cipher);

    $a = $key->encrypt('same');
    $b = $key->encrypt('same');

    expect($a)->not->toBe($b);
});

test('encrypt and decrypt with AES-CBC', function () {
    $cipher = new AES('cbc');
    $cipher->setKey(random_bytes(32));

    $key = new AesAlgorithm('aes-cbc', $cipher);

    $ciphertext = $key->encrypt('cbc mode test');
    $plaintext = $key->decrypt($ciphertext);

    expect($plaintext)->toBe('cbc mode test');
});

test('algorithm constant is 3', function () {
    $cipher = new AES('ctr');
    $cipher->setKey(random_bytes(32));

    $key = new AesAlgorithm('test', $cipher);

    expect($key->algorithm)->toBe(3);
    expect(AesAlgorithm::ALGORITHM)->toBe(3);
});
