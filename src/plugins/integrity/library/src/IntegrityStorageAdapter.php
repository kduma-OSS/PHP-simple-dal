<?php

declare(strict_types=1);

namespace KDuma\SimpleDAL\Integrity;

use KDuma\SimpleDAL\Adapter\Contracts\StorageAdapterInterface;
use KDuma\SimpleDAL\Contracts\EntityDefinitionInterface;
use KDuma\SimpleDAL\Integrity\Exception\IntegrityException;

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

    /** @codeCoverageIgnore */
    public function deleteRecord(string $entityName, string $recordId): void
    {
        $this->inner->deleteRecord($entityName, $recordId);
    }

    /** @codeCoverageIgnore */
    public function recordExists(string $entityName, string $recordId): bool
    {
        return $this->inner->recordExists($entityName, $recordId);
    }

    /** @codeCoverageIgnore */
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
            if ($raw === false) { // @codeCoverageIgnoreStart
                throw new \RuntimeException('Failed to read stream contents.');
            } // @codeCoverageIgnoreEnd
            $contents = $raw;
        }

        if (! is_string($contents)) { // @codeCoverageIgnoreStart
            throw new \RuntimeException('Attachment contents must be a string or stream resource.');
        } // @codeCoverageIgnoreEnd

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

        if ($this->config->detachedAttachments) {
            // Detached mode: write raw content + JSON sidecar .sig file
            $this->inner->writeAttachment($entityName, $recordId, $name, $contents);

            $sidecar = [];

            if ($hash !== null) {
                $sidecar['algorithm'] = $hashAlgorithm;
                $sidecar['hash'] = base64_encode($hash);
            }

            if ($signature !== null) {
                $sidecar['signing_algorithm'] = $signingAlgorithm;
                $sidecar['key_id'] = $keyId;
                $sidecar['signature'] = base64_encode($signature);
            }

            $this->inner->writeAttachment(
                $entityName,
                $recordId,
                $name.'.sig',
                json_encode($sidecar, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            );
        } else {
            // Inline mode: wrap content in integrity envelope
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
    }

    public function readAttachment(string $entityName, string $recordId, string $name): mixed
    {
        $stream = $this->inner->readAttachment($entityName, $recordId, $name);
        $raw = stream_get_contents($stream);

        if (is_resource($stream)) {
            fclose($stream);
        }

        if ($raw === false) { // @codeCoverageIgnoreStart
            throw new \RuntimeException("Failed to read attachment stream for '{$name}' on record '{$recordId}' in entity '{$entityName}'.");
        } // @codeCoverageIgnoreEnd

        $data = $raw;

        if (IntegrityPayload::hasIntegrity($data)) {
            // Inline mode: integrity envelope wraps the content
            $payload = IntegrityPayload::decode($data);
            $this->verifyAttachmentPayload($entityName, $recordId, $name, $payload->payload, $payload);
            $data = $payload->payload;
        } elseif ($this->inner->attachmentExists($entityName, $recordId, $name.'.sig')) {
            // Detached mode: JSON sidecar .sig file contains integrity metadata
            $sigStream = $this->inner->readAttachment($entityName, $recordId, $name.'.sig');
            $sigRaw = stream_get_contents($sigStream);

            if (is_resource($sigStream)) {
                fclose($sigStream);
            }

            if ($sigRaw !== false) {
                /** @var array{algorithm?: int, hash?: string, signing_algorithm?: int, key_id?: string, signature?: string} $integrity */
                $integrity = json_decode($sigRaw, true, 512, JSON_THROW_ON_ERROR);

                $this->verifyDetachedIntegrity($entityName, $recordId, $name, $data, $integrity);
            }
        } else {
            // No integrity metadata found
            if ($this->config->onMissingIntegrity === FailureMode::Throw) {
                throw new IntegrityException(
                    $entityName,
                    $recordId,
                    '',
                    '',
                    "Missing integrity metadata for attachment '{$name}' on record '{$recordId}' in entity '{$entityName}'.",
                );
            }
        }

        $result = fopen('php://memory', 'r+');
        if ($result === false) { // @codeCoverageIgnoreStart
            throw new \RuntimeException('Failed to open php://memory stream.');
        } // @codeCoverageIgnoreEnd
        fwrite($result, $data);
        rewind($result);

        return $result;
    }

    public function deleteAttachment(string $entityName, string $recordId, string $name): void
    {
        $this->inner->deleteAttachment($entityName, $recordId, $name);

        // Also delete detached sidecar if it exists
        if ($this->inner->attachmentExists($entityName, $recordId, $name.'.sig')) {
            $this->inner->deleteAttachment($entityName, $recordId, $name.'.sig');
        }
    }

    /** @codeCoverageIgnore */
    public function deleteAllAttachments(string $entityName, string $recordId): void
    {
        $this->inner->deleteAllAttachments($entityName, $recordId);
    }

    public function listAttachments(string $entityName, string $recordId): array
    {
        $names = $this->inner->listAttachments($entityName, $recordId);

        // Filter out .sig sidecar files
        return array_values(array_filter($names, fn (string $n) => ! str_ends_with($n, '.sig')));
    }

    /** @codeCoverageIgnore */
    public function attachmentExists(string $entityName, string $recordId, string $name): bool
    {
        return $this->inner->attachmentExists($entityName, $recordId, $name);
    }

    /** @codeCoverageIgnore */
    public function initializeEntity(string $entityName, EntityDefinitionInterface $definition): void
    {
        $this->inner->initializeEntity($entityName, $definition);
    }

    /** @codeCoverageIgnore */
    public function purgeEntity(string $entityName): void
    {
        $this->inner->purgeEntity($entityName);
    }

    /**
     * Verify hash and signature for an inline attachment payload.
     */
    private function verifyAttachmentPayload(string $entityName, string $recordId, string $name, string $content, IntegrityPayload $payload): void
    {
        if ($payload->hash !== null && $this->config->hasher !== null) {
            $actualHash = $this->config->hasher->hash($content);
            if (! hash_equals($payload->hash, $actualHash)) {
                if ($this->config->onChecksumFailure === FailureMode::Throw) {
                    throw new IntegrityException($entityName, $recordId, $payload->hash, $actualHash);
                }
            }
        }

        if ($payload->signature !== null && $this->config->signer !== null) {
            if (! $this->config->signer->verify($content, $payload->signature)) {
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
    }

    /**
     * Verify hash and signature from a detached JSON sidecar.
     *
     * @param  array{algorithm?: int, hash?: string, signing_algorithm?: int, key_id?: string, signature?: string}  $integrity
     */
    private function verifyDetachedIntegrity(string $entityName, string $recordId, string $name, string $content, array $integrity): void
    {
        if (isset($integrity['hash']) && $this->config->hasher !== null) {
            $expectedHash = base64_decode($integrity['hash']);
            $actualHash = $this->config->hasher->hash($content);

            if (! hash_equals($expectedHash, $actualHash)) {
                if ($this->config->onChecksumFailure === FailureMode::Throw) {
                    throw new IntegrityException($entityName, $recordId, $expectedHash, $actualHash);
                }
            }
        }

        if (isset($integrity['signature']) && $this->config->signer !== null) {
            $signature = base64_decode($integrity['signature']);
            if (! $this->config->signer->verify($content, $signature)) {
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
            if ($this->config->onMissingIntegrity === FailureMode::Throw) {
                throw new IntegrityException(
                    $entityName,
                    $recordId,
                    '',
                    '',
                    "Missing integrity metadata for record '{$recordId}' in entity '{$entityName}'.",
                );
            }

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
