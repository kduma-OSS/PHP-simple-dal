<?php

declare(strict_types=1);

namespace KDuma\SimpleDAL\Encryption;

use KDuma\SimpleDAL\Encryption\Contracts\EncryptionAlgorithmInterface;
use KDuma\SimpleDAL\Encryption\Contracts\Exception\DecryptionException;

class EncryptionConfig
{
    /** @var array<string, EncryptionAlgorithmInterface> */
    private readonly array $keyMap;

    /**
     * @param  EncryptionAlgorithmInterface[]  $keys  All keys (active + legacy for decryption)
     * @param  EncryptionRule[]  $rules  Rules evaluated in order; first match wins
     */
    public function __construct(
        array $keys,
        public readonly array $rules,
    ) {
        $map = [];

        foreach ($keys as $key) {
            $map[$key->id] = $key;
        }

        $this->keyMap = $map;
    }

    public function findRule(string $entityName, string $recordId, string $attachmentName): ?EncryptionRule
    {
        foreach ($this->rules as $rule) {
            if ($rule->matches($entityName, $recordId, $attachmentName)) {
                return $rule;
            }
        }

        return null;
    }

    public function getKey(string $keyId): EncryptionAlgorithmInterface
    {
        return $this->keyMap[$keyId] ?? throw new DecryptionException(
            "Encryption key '{$keyId}' not found in config.",
        );
    }
}
