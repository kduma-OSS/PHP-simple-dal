<?php

declare(strict_types=1);

namespace KDuma\SimpleDAL\Encryption;

use KDuma\SimpleDAL\Adapter\Contracts\StorageAdapterInterface;

class EncryptionMigrator
{
    public function __construct(
        private readonly StorageAdapterInterface $adapter,
        private readonly EncryptionConfig $config,
    ) {}

    /**
     * Migrate all attachments in the given entities to match the current encryption config.
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
        }
    }

    private function migrateRecord(string $entityName, string $recordId): void
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

        // Determine current state
        $currentKeyId = null;

        if (EncryptedPayload::isEncrypted($rawData)) {
            $payload = EncryptedPayload::decode($rawData);
            $currentKeyId = $payload->keyId;
        }

        // Determine desired state
        $rule = $this->config->findRule($entityName, $recordId, $name);
        $desiredKeyId = $rule?->keyId;

        // Already in desired state
        if ($currentKeyId === $desiredKeyId) {
            return;
        }

        // Decrypt current data if encrypted
        $plaintext = $rawData;

        if ($currentKeyId !== null) {
            $payload = EncryptedPayload::decode($rawData);
            $key = $this->config->getKey($currentKeyId);
            $plaintext = $key->decrypt($payload->payload);
        }

        // Re-encrypt or write plaintext
        if ($desiredKeyId !== null) {
            $key = $this->config->getKey($desiredKeyId);
            $encrypted = $key->encrypt($plaintext);
            $newData = EncryptedPayload::encode($key->id, $key->algorithm, $encrypted);
        } else {
            $newData = $plaintext;
        }

        $this->adapter->writeAttachment($entityName, $recordId, $name, $newData);
    }
}
