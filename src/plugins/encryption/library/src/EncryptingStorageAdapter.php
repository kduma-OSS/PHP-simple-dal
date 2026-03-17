<?php

declare(strict_types=1);

namespace KDuma\SimpleDAL\Encryption;

use KDuma\SimpleDAL\Adapter\Contracts\StorageAdapterInterface;
use KDuma\SimpleDAL\Contracts\EntityDefinitionInterface;

class EncryptingStorageAdapter implements StorageAdapterInterface
{
    public function __construct(
        private readonly StorageAdapterInterface $inner,
        private readonly EncryptionConfig $config,
    ) {}

    public function writeRecord(string $entityName, string $recordId, array $data): void
    {
        $this->inner->writeRecord($entityName, $recordId, $data);
    }

    public function readRecord(string $entityName, string $recordId): array
    {
        return $this->inner->readRecord($entityName, $recordId);
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
        return $this->inner->findRecords($entityName, $filters, $sort, $limit, $offset);
    }

    public function writeAttachment(
        string $entityName,
        string $recordId,
        string $name,
        mixed $contents,
    ): void {
        $rule = $this->config->findRule($entityName, $recordId, $name);

        if ($rule !== null) {
            if (is_resource($contents)) {
                $raw = stream_get_contents($contents);
                if ($raw === false) {
                    throw new \RuntimeException('Failed to read stream contents for encryption.');
                }
                $contents = $raw;
            }

            assert(is_string($contents));

            $key = $this->config->getKey($rule->keyId);
            $encrypted = $key->encrypt($contents);
            $contents = EncryptedPayload::encode($key->id, $key->algorithm, $encrypted);
        }

        $this->inner->writeAttachment($entityName, $recordId, $name, $contents);
    }

    public function readAttachment(string $entityName, string $recordId, string $name): mixed
    {
        $stream = $this->inner->readAttachment($entityName, $recordId, $name);

        assert(is_resource($stream));
        $data = stream_get_contents($stream);
        fclose($stream);

        if ($data === false) {
            throw new \RuntimeException('Failed to read attachment stream contents.');
        }

        if (EncryptedPayload::isEncrypted($data)) {
            $payload = EncryptedPayload::decode($data);
            $key = $this->config->getKey($payload->keyId);
            $data = $key->decrypt($payload->payload);
        }

        $result = fopen('php://memory', 'r+');
        assert($result !== false);
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
}
