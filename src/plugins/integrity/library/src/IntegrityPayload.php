<?php

declare(strict_types=1);

namespace KDuma\SimpleDAL\Integrity;

use KDuma\BinaryTools\BinaryReader;
use KDuma\BinaryTools\BinaryString;
use KDuma\BinaryTools\BinaryWriter;
use KDuma\BinaryTools\IntType;
use KDuma\SimpleDAL\Contracts\Exception\CorruptedDataException;
use RuntimeException;

class IntegrityPayload
{
    public const string MAGIC = "SDIC\x00";

    public const int VERSION = 1;

    private const int HEADER_MIN_SIZE = 7; // 5 magic + 1 version + 1 flags

    public function __construct(
        public readonly ?string $hash,
        public readonly ?int $hashAlgorithm,
        public readonly ?int $signingAlgorithm,
        public readonly ?string $keyId,
        public readonly ?string $signature,
        public readonly string $payload,
    ) {}

    public static function hasIntegrity(string $data): bool
    {
        return strlen($data) >= self::HEADER_MIN_SIZE
            && str_starts_with($data, self::MAGIC);
    }

    public static function encode(
        string $content,
        ?string $hash = null,
        ?int $hashAlgorithm = null,
        ?int $signingAlgorithm = null,
        ?string $keyId = null,
        ?string $signature = null,
    ): string {
        $hasHash = $hash !== null && $hashAlgorithm !== null;
        $hasSig = $signingAlgorithm !== null && $keyId !== null && $signature !== null;
        $flags = ($hasHash ? 0x01 : 0x00) | ($hasSig ? 0x02 : 0x00);

        $writer = new BinaryWriter;
        $writer->writeBytes(BinaryString::fromString(self::MAGIC))
            ->writeByte(self::VERSION)
            ->writeByte($flags);

        if ($hasHash) {
            $writer->writeByte($hashAlgorithm)
                ->writeBytesWith(BinaryString::fromString($hash), length: IntType::UINT16);
        }

        if ($hasSig) {
            $writer->writeByte($signingAlgorithm)
                ->writeBytesWith(BinaryString::fromString($keyId), length: IntType::UINT16)
                ->writeBytesWith(BinaryString::fromString($signature), length: IntType::UINT16);
        }

        $writer->writeBytes(BinaryString::fromString($content));

        return $writer->getBuffer()->toString();
    }

    public static function decode(string $data): self
    {
        if (! self::hasIntegrity($data)) {
            throw new CorruptedDataException('Data is not an integrity payload — missing magic header.');
        }

        try {
            $reader = new BinaryReader(BinaryString::fromString($data));
            $reader->skip(strlen(self::MAGIC));

            $version = $reader->readByte();
            if ($version !== self::VERSION) {
                throw new CorruptedDataException("Unsupported integrity version: {$version}.");
            }

            $flags = $reader->readByte();
            $hasHash = ($flags & 0x01) !== 0;
            $hasSig = ($flags & 0x02) !== 0;

            $hash = null;
            $hashAlgorithm = null;

            if ($hasHash) {
                $hashAlgorithm = $reader->readByte();
                $hash = $reader->readBytesWith(length: IntType::UINT16)->toString();
            }

            $signingAlgorithm = null;
            $keyId = null;
            $signature = null;

            if ($hasSig) {
                $signingAlgorithm = $reader->readByte();
                $keyId = $reader->readBytesWith(length: IntType::UINT16)->toString();
                $signature = $reader->readBytesWith(length: IntType::UINT16)->toString();
            }

            $payload = $reader->remaining_data->toString();

            return new self($hash, $hashAlgorithm, $signingAlgorithm, $keyId, $signature, $payload);
        } catch (CorruptedDataException $e) {
            throw $e;
        } catch (RuntimeException $e) {
            throw new CorruptedDataException('Integrity payload is truncated — '.$e->getMessage());
        }
    }
}
