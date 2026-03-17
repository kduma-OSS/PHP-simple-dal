<?php

declare(strict_types=1);

namespace KDuma\SimpleDAL\Integrity\PhpSecLib;

use KDuma\SimpleDAL\Integrity\Contracts\SigningAlgorithmInterface;
use phpseclib3\Crypt\Common\PrivateKey;
use phpseclib3\Crypt\Common\PublicKey;

class GenericPhpSecLibSigningAlgorithm implements SigningAlgorithmInterface
{
    public int $algorithm {
        get => $this->algorithmId;
    }

    private readonly PublicKey $publicKey;

    private readonly ?PrivateKey $privateKey;

    /**
     * @param  string  $id  Key identifier
     * @param  PublicKey|PrivateKey  $key  Pre-configured key. If PrivateKey, public key is extracted
     *                                     via getPublicKey(). If PublicKey, verify-only mode.
     * @param  int  $algorithmId  Unique algorithm identifier for the binary header.
     */
    public function __construct(
        public readonly string $id,
        PublicKey|PrivateKey $key,
        private readonly int $algorithmId = 2,
    ) {
        if ($key instanceof PrivateKey) {
            $this->privateKey = $key;
            $publicKey = $key->getPublicKey();
            if (! $publicKey instanceof PublicKey) {
                throw new \RuntimeException("Failed to extract public key from private key '{$this->id}'.");
            }
            $this->publicKey = $publicKey;
        } else {
            $this->privateKey = null;
            $this->publicKey = $key;
        }
    }

    public function sign(string $message): string
    {
        if ($this->privateKey === null) {
            throw new \RuntimeException("Cannot sign with key '{$this->id}' — no private key provided (verify-only mode).");
        }

        $signature = $this->privateKey->sign($message);
        if (! is_string($signature)) {
            throw new \RuntimeException("Expected string signature from key '{$this->id}', got ".get_debug_type($signature).'.');
        }

        return $signature;
    }

    public function verify(string $message, string $signature): bool
    {
        return (bool) $this->publicKey->verify($message, $signature);
    }
}
