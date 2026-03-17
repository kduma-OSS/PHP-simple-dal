<?php

declare(strict_types=1);

namespace KDuma\SimpleDAL\DataIntegrity;

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

        // Extract content from existing integrity payload if present
        $content = $rawData;
        if (IntegrityPayload::hasIntegrity($rawData)) {
            $payload = IntegrityPayload::decode($rawData);
            $content = $payload->payload;
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

        if ($hash === null && $signature === null) {
            $newData = $content;
        } else {
            $newData = IntegrityPayload::encode(
                $content,
                $hash,
                $hashAlgorithm,
                $signingAlgorithm,
                $keyId,
                $signature,
            );
        }

        // Skip if unchanged
        if ($newData === $rawData) {
            return;
        }

        $this->adapter->writeAttachment($entityName, $recordId, $name, $newData);
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
