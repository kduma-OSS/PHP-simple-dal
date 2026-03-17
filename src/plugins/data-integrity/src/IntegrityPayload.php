<?php

declare(strict_types=1);

namespace KDuma\SimpleDAL\DataIntegrity;

use KDuma\SimpleDAL\Contracts\Exception\CorruptedDataException;

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

        $result = self::MAGIC
            .chr(self::VERSION)
            .chr($flags);

        if ($hasHash) {
            $result .= chr($hashAlgorithm)
                .pack('n', strlen($hash))
                .$hash;
        }

        if ($hasSig) {
            $result .= chr($signingAlgorithm)
                .pack('n', strlen($keyId))
                .$keyId
                .pack('n', strlen($signature))
                .$signature;
        }

        $result .= $content;

        return $result;
    }

    public static function decode(string $data): self
    {
        if (! self::hasIntegrity($data)) {
            throw new CorruptedDataException('Data is not an integrity payload — missing magic header.');
        }

        $offset = strlen(self::MAGIC);

        $version = ord($data[$offset++]);
        if ($version !== self::VERSION) {
            throw new CorruptedDataException("Unsupported integrity version: {$version}.");
        }

        $flags = ord($data[$offset++]);
        $hasHash = ($flags & 0x01) !== 0;
        $hasSig = ($flags & 0x02) !== 0;

        $hash = null;
        $hashAlgorithm = null;

        if ($hasHash) {
            if (strlen($data) < $offset + 1) {
                throw new CorruptedDataException('Integrity payload is truncated — missing hash algorithm.');
            }

            $hashAlgorithm = ord($data[$offset++]);

            if (strlen($data) < $offset + 2) {
                throw new CorruptedDataException('Integrity payload is truncated — missing hash length.');
            }

            $hashLenUnpacked = unpack('n', substr($data, $offset, 2));
            if ($hashLenUnpacked === false) {
                throw new CorruptedDataException('Integrity payload is truncated — cannot read hash length.');
            }
            /** @var int $hashLen */
            $hashLen = $hashLenUnpacked[1];
            $offset += 2;

            if (strlen($data) < $offset + $hashLen) {
                throw new CorruptedDataException('Integrity payload is truncated — hash extends beyond data.');
            }

            $hash = substr($data, $offset, $hashLen);
            $offset += $hashLen;
        }

        $signingAlgorithm = null;
        $keyId = null;
        $signature = null;

        if ($hasSig) {
            if (strlen($data) < $offset + 1) {
                throw new CorruptedDataException('Integrity payload is truncated — missing signing algorithm.');
            }

            $signingAlgorithm = ord($data[$offset++]);

            if (strlen($data) < $offset + 2) {
                throw new CorruptedDataException('Integrity payload is truncated — missing key ID length.');
            }

            $keyIdLenUnpacked = unpack('n', substr($data, $offset, 2));
            if ($keyIdLenUnpacked === false) {
                throw new CorruptedDataException('Integrity payload is truncated — cannot read key ID length.');
            }
            /** @var int $keyIdLen */
            $keyIdLen = $keyIdLenUnpacked[1];
            $offset += 2;

            if (strlen($data) < $offset + $keyIdLen) {
                throw new CorruptedDataException('Integrity payload is truncated — key ID extends beyond data.');
            }

            $keyId = substr($data, $offset, $keyIdLen);
            $offset += $keyIdLen;

            if (strlen($data) < $offset + 2) {
                throw new CorruptedDataException('Integrity payload is truncated — missing signature length.');
            }

            $signatureLenUnpacked = unpack('n', substr($data, $offset, 2));
            if ($signatureLenUnpacked === false) {
                throw new CorruptedDataException('Integrity payload is truncated — cannot read signature length.');
            }
            /** @var int $signatureLen */
            $signatureLen = $signatureLenUnpacked[1];
            $offset += 2;

            if (strlen($data) < $offset + $signatureLen) {
                throw new CorruptedDataException('Integrity payload is truncated — signature extends beyond data.');
            }

            $signature = substr($data, $offset, $signatureLen);
            $offset += $signatureLen;
        }

        $payload = substr($data, $offset);

        return new self($hash, $hashAlgorithm, $signingAlgorithm, $keyId, $signature, $payload);
    }
}
