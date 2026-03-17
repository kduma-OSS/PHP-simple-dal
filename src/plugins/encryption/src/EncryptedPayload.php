<?php

declare(strict_types=1);

namespace KDuma\SimpleDAL\Encryption;

use KDuma\SimpleDAL\Encryption\Contracts\Exception\DecryptionException;

class EncryptedPayload
{
    public const string MAGIC = "SDAL\x00";

    public const int VERSION = 1;

    private const int HEADER_MIN_SIZE = 9; // 5 magic + 1 version + 1 algo + 2 keyIdLen

    public function __construct(
        public readonly string $keyId,
        public readonly int $algorithm,
        public readonly string $payload,
    ) {}

    public static function isEncrypted(string $data): bool
    {
        return strlen($data) >= self::HEADER_MIN_SIZE
            && str_starts_with($data, self::MAGIC);
    }

    public static function encode(string $keyId, int $algorithm, string $encryptedPayload): string
    {
        $keyIdBytes = $keyId;
        $keyIdLen = strlen($keyIdBytes);

        return self::MAGIC
            .chr(self::VERSION)
            .chr($algorithm)
            .pack('n', $keyIdLen)
            .$keyIdBytes
            .$encryptedPayload;
    }

    public static function decode(string $data): self
    {
        if (! self::isEncrypted($data)) {
            throw new DecryptionException('Data is not an encrypted payload — missing magic header.');
        }

        $offset = strlen(self::MAGIC);

        $version = ord($data[$offset++]);
        if ($version !== self::VERSION) {
            throw new DecryptionException("Unsupported encryption version: {$version}.");
        }

        $algorithm = ord($data[$offset++]);

        $keyIdLen = unpack('n', substr($data, $offset, 2))[1];
        $offset += 2;

        if (strlen($data) < $offset + $keyIdLen) {
            throw new DecryptionException('Encrypted payload is truncated — key ID extends beyond data.');
        }

        $keyId = substr($data, $offset, $keyIdLen);
        $offset += $keyIdLen;

        $payload = substr($data, $offset);

        return new self($keyId, $algorithm, $payload);
    }
}
