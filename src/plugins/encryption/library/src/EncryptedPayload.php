<?php

declare(strict_types=1);

namespace KDuma\SimpleDAL\Encryption;

use KDuma\BinaryTools\BinaryReader;
use KDuma\BinaryTools\BinaryString;
use KDuma\BinaryTools\BinaryWriter;
use KDuma\BinaryTools\IntType;
use KDuma\SimpleDAL\Encryption\Contracts\Exception\DecryptionException;
use RuntimeException;

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
        $writer = new BinaryWriter;
        $writer->writeBytes(BinaryString::fromString(self::MAGIC))
            ->writeByte(self::VERSION)
            ->writeByte($algorithm)
            ->writeBytesWith(BinaryString::fromString($keyId), length: IntType::UINT16)
            ->writeBytes(BinaryString::fromString($encryptedPayload));

        return $writer->getBuffer()->toString();
    }

    public static function decode(string $data): self
    {
        if (! self::isEncrypted($data)) {
            throw new DecryptionException('Data is not an encrypted payload — missing magic header.');
        }

        try {
            $reader = new BinaryReader(BinaryString::fromString($data));
            $reader->skip(strlen(self::MAGIC));

            $version = $reader->readByte();
            if ($version !== self::VERSION) { // @codeCoverageIgnoreStart
                throw new DecryptionException("Unsupported encryption version: {$version}.");
            } // @codeCoverageIgnoreEnd

            $algorithm = $reader->readByte();
            $keyId = $reader->readBytesWith(length: IntType::UINT16)->toString();
            $payload = $reader->remaining_data->toString();

            return new self($keyId, $algorithm, $payload);
        } catch (DecryptionException $e) { // @codeCoverageIgnoreStart
            throw $e;
        } catch (RuntimeException $e) {
            throw new DecryptionException('Encrypted payload is truncated — '.$e->getMessage());
        } // @codeCoverageIgnoreEnd
    }
}
