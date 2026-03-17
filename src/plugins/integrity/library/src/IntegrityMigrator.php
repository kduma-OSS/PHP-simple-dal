<?php

declare(strict_types=1);

namespace KDuma\SimpleDAL\Integrity;

use KDuma\SimpleDAL\Adapter\Contracts\StorageAdapterInterface;

class IntegrityMigrator
{
    public function __construct(
        private readonly StorageAdapterInterface $adapter,
        private readonly IntegrityConfig $config,
    ) {}

    /**
     * Migrate all records and attachments in the given entities to match the current integrity config.
     *
     * @param  string[]  $entityNames  The entity names to process
     */
    public function migrate(array $entityNames): void
    {
        foreach ($entityNames as $entityName) {
            $this->migrateEntity($entityName);
        }
    }

    private function migrateEntity(string $entityName): void
    {
        $recordIds = $this->adapter->listRecordIds($entityName);

        foreach ($recordIds as $recordId) {
            $this->migrateRecord($entityName, $recordId);
            $this->migrateAttachments($entityName, $recordId);
        }
    }

    private function migrateRecord(string $entityName, string $recordId): void
    {
        $data = $this->adapter->readRecord($entityName, $recordId);

        // Strip existing integrity metadata
        $cleanData = $data;
        unset($cleanData['_integrity']);

        // Compute new integrity
        $canonical = $this->canonicalJson($cleanData);

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

        // Skip if unchanged
        if (isset($data['_integrity']) && $data['_integrity'] === $integrity) {
            return;
        }

        if ($integrity !== []) {
            $cleanData['_integrity'] = $integrity;
        }

        $this->adapter->writeRecord($entityName, $recordId, $cleanData);
    }

    private function migrateAttachments(string $entityName, string $recordId): void
    {
        $attachmentNames = $this->adapter->listAttachments($entityName, $recordId);

        // Filter out .sig sidecar files — we handle them as part of the main attachment
        $attachmentNames = array_values(array_filter($attachmentNames, fn (string $n) => ! str_ends_with($n, '.sig')));

        foreach ($attachmentNames as $name) {
            $this->migrateAttachment($entityName, $recordId, $name);
        }
    }

    private function migrateAttachment(string $entityName, string $recordId, string $name): void
    {
        $stream = $this->adapter->readAttachment($entityName, $recordId, $name);
        $rawData = stream_get_contents($stream);

        if (is_resource($stream)) {
            fclose($stream);
        }

        // Extract content: check inline envelope first, then detached sidecar
        $content = $rawData;
        $hadInline = false;
        $hadDetached = false;

        if (IntegrityPayload::hasIntegrity($rawData)) {
            $payload = IntegrityPayload::decode($rawData);
            $content = $payload->payload;
            $hadInline = true;
        } elseif ($this->adapter->attachmentExists($entityName, $recordId, $name.'.sig')) {
            $hadDetached = true;
            // Content is already raw — no extraction needed
        }

        // Compute new integrity
        $hash = null;
        $hashAlgorithm = null;

        if ($this->config->hasher !== null) {
            $hash = $this->config->hasher->hash($content);
            $hashAlgorithm = $this->config->hasher->algorithm;
        }

        $signingAlgorithm = null;
        $keyId = null;
        $signature = null;

        if ($this->config->signer !== null) {
            $signingAlgorithm = $this->config->signer->algorithm;
            $keyId = $this->config->signer->id;
            $signature = $this->config->signer->sign($content);
        }

        $hasIntegrity = $hash !== null || $signature !== null;

        if ($this->config->detachedAttachments) {
            // Target: detached mode
            // Write raw content (remove inline envelope if it had one)
            if ($hadInline) {
                $this->adapter->writeAttachment($entityName, $recordId, $name, $content);
            }

            if ($hasIntegrity) {
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

                $this->adapter->writeAttachment(
                    $entityName,
                    $recordId,
                    $name.'.sig',
                    json_encode($sidecar, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                );
            } elseif ($hadDetached) {
                // No integrity needed, remove old sidecar
                $this->adapter->deleteAttachment($entityName, $recordId, $name.'.sig');
            }
        } else {
            // Target: inline mode
            // Remove detached sidecar if it exists
            if ($hadDetached) {
                $this->adapter->deleteAttachment($entityName, $recordId, $name.'.sig');
            }

            if ($hasIntegrity) {
                $newData = IntegrityPayload::encode($content, $hash, $hashAlgorithm, $signingAlgorithm, $keyId, $signature);
                if ($newData !== $rawData) {
                    $this->adapter->writeAttachment($entityName, $recordId, $name, $newData);
                }
            } elseif ($hadInline) {
                // No integrity needed, write raw content
                $this->adapter->writeAttachment($entityName, $recordId, $name, $content);
            }
        }
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
