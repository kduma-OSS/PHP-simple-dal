<?php

declare(strict_types=1);

namespace KDuma\SimpleDAL\DataIntegrity;

use KDuma\SimpleDAL\Adapter\Contracts\StorageAdapterInterface;
use KDuma\SimpleDAL\Contracts\EntityDefinitionInterface;
use KDuma\SimpleDAL\DataIntegrity\Exception\IntegrityException;

class IntegrityStorageAdapter implements StorageAdapterInterface
{
    public function __construct(
        private readonly StorageAdapterInterface $inner,
        private readonly IntegrityConfig $config,
    ) {}

    public function writeRecord(string $entityName, string $recordId, array $data): void
    {
        $canonical = $this->canonicalJson($data);

        $integrity = [];

        if ($this->config->hasher !== null) {
            $hash = $this->config->hasher->hash($canonical);
            $integrity['algorithm'] = $this->config->hasher->algorithm;
            $integrity['hash'] = base64_encode($hash);
        }

        if ($this->config->signer !== null) {
            $signature = $this->config->signer->sign($canonical);
            $integrity['signing_algorithm'] = $this->config->signer->algorithm;
            $integrity['key_id'] = $this->config->signer->id;
            $integrity['signature'] = base64_encode($signature);
        }

        if ($integrity !== []) {
            $data['_integrity'] = $integrity;
        }

        $this->inner->writeRecord($entityName, $recordId, $data);
    }

    public function readRecord(string $entityName, string $recordId): array
    {
        $data = $this->inner->readRecord($entityName, $recordId);

        return $this->verifyAndStripIntegrity($entityName, $recordId, $data);
    }

    public function deleteRecord(string $entityName, string $recordId): void
    {
        $this->inner->deleteRecord($entityName, $recordId);
    }

    public function recordExists(string $entityName, string $recordId): bool
    {
        return $this->inner->recordExists($entityName, $recordId);
    }

    public function listRecordIds(string $entityName): array
    {
        return $this->inner->listRecordIds($entityName);
    }

    public function findRecords(
        string $entityName,
        array $filters = [],
        array $sort = [],
        ?int $limit = null,
        int $offset = 0,
    ): array {
        $results = $this->inner->findRecords($entityName, $filters, $sort, $limit, $offset);

        $verified = [];
        foreach ($results as $recordId => $data) {
            $verified[$recordId] = $this->verifyAndStripIntegrity($entityName, $recordId, $data);
        }

        return $verified;
    }

    public function writeAttachment(
        string $entityName,
        string $recordId,
        string $name,
        mixed $contents,
    ): void {
        if (is_resource($contents)) {
            $raw = stream_get_contents($contents);
            if ($raw === false) {
                throw new \RuntimeException('Failed to read stream contents.');
            }
            $contents = $raw;
        }

        if (! is_string($contents)) {
            throw new \RuntimeException('Attachment contents must be a string or stream resource.');
        }

        $hash = null;
        $hashAlgorithm = null;

        if ($this->config->hasher !== null) {
            $hash = $this->config->hasher->hash($contents);
            $hashAlgorithm = $this->config->hasher->algorithm;
        }

        $signingAlgorithm = null;
        $keyId = null;
        $signature = null;

        if ($this->config->signer !== null) {
            $signingAlgorithm = $this->config->signer->algorithm;
            $keyId = $this->config->signer->id;
            $signature = $this->config->signer->sign($contents);
        }

        if ($hash === null && $signature === null) {
            $this->inner->writeAttachment($entityName, $recordId, $name, $contents);

            return;
        }

        $encoded = IntegrityPayload::encode(
            $contents,
            $hash,
            $hashAlgorithm,
            $signingAlgorithm,
            $keyId,
            $signature,
        );

        $this->inner->writeAttachment($entityName, $recordId, $name, $encoded);
    }

    public function readAttachment(string $entityName, string $recordId, string $name): mixed
    {
        $stream = $this->inner->readAttachment($entityName, $recordId, $name);
        $raw = stream_get_contents($stream);

        if (is_resource($stream)) {
            fclose($stream);
        }

        if ($raw === false) {
            throw new \RuntimeException("Failed to read attachment stream for '{$name}' on record '{$recordId}' in entity '{$entityName}'.");
        }

        $data = $raw;

        if (IntegrityPayload::hasIntegrity($data)) {
            $payload = IntegrityPayload::decode($data);

            // Verify checksum if present
            if ($payload->hash !== null && $this->config->hasher !== null) {
                $actualHash = $this->config->hasher->hash($payload->payload);
                if (! hash_equals($payload->hash, $actualHash)) {
                    if ($this->config->onChecksumFailure === FailureMode::Throw) {
                        throw new IntegrityException($entityName, $recordId, $payload->hash, $actualHash);
                    }
                }
            }

            // Verify signature if present
            if ($payload->signature !== null && $this->config->signer !== null) {
                if (! $this->config->signer->verify($payload->payload, $payload->signature)) {
                    if ($this->config->onSignatureFailure === FailureMode::Throw) {
                        throw new IntegrityException(
                            $entityName,
                            $recordId,
                            '',
                            '',
                            "Signature verification failed for attachment '{$name}' on record '{$recordId}' in entity '{$entityName}'.",
                        );
                    }
                }
            }

            $data = $payload->payload;
        }

        $result = fopen('php://memory', 'r+');
        if ($result === false) {
            throw new \RuntimeException('Failed to open php://memory stream.');
        }
        fwrite($result, $data);
        rewind($result);

        return $result;
    }

    public function deleteAttachment(string $entityName, string $recordId, string $name): void
    {
        $this->inner->deleteAttachment($entityName, $recordId, $name);
    }

    public function deleteAllAttachments(string $entityName, string $recordId): void
    {
        $this->inner->deleteAllAttachments($entityName, $recordId);
    }

    public function listAttachments(string $entityName, string $recordId): array
    {
        return $this->inner->listAttachments($entityName, $recordId);
    }

    public function attachmentExists(string $entityName, string $recordId, string $name): bool
    {
        return $this->inner->attachmentExists($entityName, $recordId, $name);
    }

    public function initializeEntity(string $entityName, EntityDefinitionInterface $definition): void
    {
        $this->inner->initializeEntity($entityName, $definition);
    }

    public function purgeEntity(string $entityName): void
    {
        $this->inner->purgeEntity($entityName);
    }

    /**
     * Verify integrity metadata on a record and return the data with _integrity stripped.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function verifyAndStripIntegrity(string $entityName, string $recordId, array $data): array
    {
        if (! isset($data['_integrity'])) {
            return $data;
        }

        /** @var array{algorithm?: int, hash?: string, signing_algorithm?: int, key_id?: string, signature?: string} $integrity */
        $integrity = $data['_integrity'];
        unset($data['_integrity']);

        $canonical = $this->canonicalJson($data);

        // Verify checksum if present
        if (isset($integrity['hash']) && $this->config->hasher !== null) {
            $actualHash = $this->config->hasher->hash($canonical);
            $expectedHash = base64_decode($integrity['hash']);

            if (! hash_equals($expectedHash, $actualHash)) {
                if ($this->config->onChecksumFailure === FailureMode::Throw) {
                    throw new IntegrityException($entityName, $recordId, $expectedHash, $actualHash);
                }
            }
        }

        // Verify signature if present
        if (isset($integrity['signature']) && $this->config->signer !== null) {
            $signature = base64_decode($integrity['signature']);
            if (! $this->config->signer->verify($canonical, $signature)) {
                if ($this->config->onSignatureFailure === FailureMode::Throw) {
                    throw new IntegrityException(
                        $entityName,
                        $recordId,
                        '',
                        '',
                        "Signature verification failed for record '{$recordId}' in entity '{$entityName}'.",
                    );
                }
            }
        }

        return $data;
    }

    /**
     * Produce canonical JSON: keys sorted recursively, with JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE.
     *
     * @param  array<string, mixed>  $data
     */
    private function canonicalJson(array $data): string
    {
        $data = $this->sortKeysRecursive($data);

        return json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /**
     * Sort array keys recursively for canonical representation.
     *
     * @param  array<array-key, mixed>  $data
     * @return array<array-key, mixed>
     */
    private function sortKeysRecursive(array $data): array
    {
        ksort($data);

        foreach ($data as $key => $value) {
            if (is_array($value) && ! array_is_list($value)) {
                $data[$key] = $this->sortKeysRecursive($value);
            }
        }

        return $data;
    }
}
