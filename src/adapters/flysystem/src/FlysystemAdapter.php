<?php

declare(strict_types=1);

namespace KDuma\SimpleDAL\Adapter\Flysystem;

use KDuma\SimpleDAL\Adapter\Contracts\StorageAdapterInterface;
use KDuma\SimpleDAL\Contracts\EntityDefinitionInterface;
use KDuma\SimpleDAL\Contracts\Exception\AttachmentNotFoundException;
use KDuma\SimpleDAL\Contracts\Exception\RecordNotFoundException;
use League\Flysystem\FilesystemOperator;

final class FlysystemAdapter implements StorageAdapterInterface
{
    /** @var array<string, EntityDefinitionInterface> */
    private array $definitions = [];

    public function __construct(
        private readonly FilesystemOperator $filesystem,
    ) {}

    public function initializeEntity(string $entityName, EntityDefinitionInterface $definition): void
    {
        $this->definitions[$entityName] = $definition;

        if (! $this->filesystem->fileExists("{$entityName}/.gitkeep")) {
            $this->filesystem->createDirectory($entityName);
        }
    }

    public function writeRecord(string $entityName, string $recordId, array $data): void
    {
        $this->sortKeysRecursively($data);

        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

        $path = "{$entityName}/{$recordId}/data.json";
        $this->filesystem->write($path, $json);

        $this->updateIndexForRecord($entityName, $recordId, $data);
    }

    public function readRecord(string $entityName, string $recordId): array
    {
        $path = "{$entityName}/{$recordId}/data.json";

        if (! $this->filesystem->fileExists($path)) {
            throw new RecordNotFoundException("Record '{$recordId}' not found in entity '{$entityName}'.");
        }

        $contents = $this->filesystem->read($path);

        /** @var array<string, mixed> $decoded */
        $decoded = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);

        return $decoded;
    }

    public function deleteRecord(string $entityName, string $recordId): void
    {
        $dir = "{$entityName}/{$recordId}";

        if (! $this->filesystem->fileExists("{$dir}/data.json")) {
            return;
        }

        $this->removeRecordFromIndex($entityName, $recordId);

        $this->filesystem->deleteDirectory($dir);
    }

    public function recordExists(string $entityName, string $recordId): bool
    {
        return $this->filesystem->fileExists("{$entityName}/{$recordId}/data.json");
    }

    public function listRecordIds(string $entityName): array
    {
        try {
            $listing = $this->filesystem->listContents($entityName, false);
        } catch (\Throwable) { // @codeCoverageIgnore
            return []; // @codeCoverageIgnore
        }

        $ids = [];

        foreach ($listing as $item) {
            if (! $item->isDir()) {
                continue;
            }

            $basename = basename($item->path());

            if (str_starts_with($basename, '_')) {
                continue; // @codeCoverageIgnore
            }

            $ids[] = $basename;
        }

        sort($ids);

        return $ids;
    }

    public function findRecords(
        string $entityName,
        array $filters = [],
        array $sort = [],
        ?int $limit = null,
        int $offset = 0,
    ): array {
        $candidateIds = $this->resolveCandidateIds($entityName, $filters);

        $results = [];

        foreach ($candidateIds as $id) {
            try {
                $data = $this->readRecord($entityName, $id);
            } catch (RecordNotFoundException) { // @codeCoverageIgnore
                continue; // @codeCoverageIgnore
            }

            if ($this->matchesAllFilters($data, $filters)) {
                $results[$id] = $data;
            }
        }

        if ($sort !== []) {
            $results = $this->applySort($results, $sort);
        }

        if ($offset > 0 || $limit !== null) {
            $results = array_slice($results, $offset, $limit, true);
        }

        return $results;
    }

    public function writeAttachment(
        string $entityName,
        string $recordId,
        string $name,
        mixed $contents,
    ): void {
        $path = "{$entityName}/{$recordId}/{$name}";

        if (is_resource($contents)) {
            $this->filesystem->writeStream($path, $contents);
        } elseif (is_string($contents)) {
            $this->filesystem->write($path, $contents);
        } else {
            throw new \InvalidArgumentException('Attachment contents must be a string or resource.'); // @codeCoverageIgnore
        }
    }

    public function readAttachment(string $entityName, string $recordId, string $name): mixed
    {
        $path = "{$entityName}/{$recordId}/{$name}";

        if (! $this->filesystem->fileExists($path)) {
            throw new AttachmentNotFoundException("Attachment '{$name}' not found for record '{$recordId}' in entity '{$entityName}'.");
        }

        return $this->filesystem->readStream($path);
    }

    public function deleteAttachment(string $entityName, string $recordId, string $name): void
    {
        $path = "{$entityName}/{$recordId}/{$name}";
        $this->filesystem->delete($path);
    }

    public function deleteAllAttachments(string $entityName, string $recordId): void
    {
        $dir = "{$entityName}/{$recordId}";

        try {
            $listing = $this->filesystem->listContents($dir, false);
        } catch (\Throwable) { // @codeCoverageIgnore
            return; // @codeCoverageIgnore
        }

        foreach ($listing as $item) {
            if (! $item->isFile()) {
                continue; // @codeCoverageIgnore
            }

            if (basename($item->path()) === 'data.json') {
                continue;
            }

            $this->filesystem->delete($item->path());
        }
    }

    public function listAttachments(string $entityName, string $recordId): array
    {
        $dir = "{$entityName}/{$recordId}";

        try {
            $listing = $this->filesystem->listContents($dir, false);
        } catch (\Throwable) { // @codeCoverageIgnore
            return []; // @codeCoverageIgnore
        }

        $names = [];

        foreach ($listing as $item) {
            if (! $item->isFile()) {
                continue; // @codeCoverageIgnore
            }

            $basename = basename($item->path());

            if ($basename === 'data.json') {
                continue;
            }

            $names[] = $basename;
        }

        sort($names);

        return $names;
    }

    public function attachmentExists(string $entityName, string $recordId, string $name): bool
    {
        return $this->filesystem->fileExists("{$entityName}/{$recordId}/{$name}");
    }

    public function purgeEntity(string $entityName): void
    {
        $this->filesystem->deleteDirectory($entityName);
        $this->filesystem->createDirectory($entityName);
    }

    // ---------------------------------------------------------------
    //  Index management
    // ---------------------------------------------------------------

    private function indexPath(string $entityName): string
    {
        return "{$entityName}/_index.json";
    }

    /**
     * @return array<string, array<string, string[]>>
     */
    private function readIndex(string $entityName): array
    {
        $path = $this->indexPath($entityName);

        if (! $this->filesystem->fileExists($path)) {
            return [];
        }

        $contents = $this->filesystem->read($path);

        /** @var array<string, array<string, string[]>> $decoded */
        $decoded = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);

        return $decoded;
    }

    /**
     * @param  array<string, array<string, string[]>>  $index
     */
    private function writeIndex(string $entityName, array $index): void
    {
        $path = $this->indexPath($entityName);

        if ($index === []) { // @codeCoverageIgnoreStart
            if ($this->filesystem->fileExists($path)) {
                $this->filesystem->delete($path);
            }

            return;
        } // @codeCoverageIgnoreEnd

        $json = json_encode($index, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $this->filesystem->write($path, $json);
    }

    /**
     * @return string[]
     */
    private function getIndexedFields(string $entityName): array
    {
        if (! isset($this->definitions[$entityName])) {
            return []; // @codeCoverageIgnore
        }

        return $this->definitions[$entityName]->indexedFields;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function updateIndexForRecord(string $entityName, string $recordId, array $data): void
    {
        $indexedFields = $this->getIndexedFields($entityName);

        if ($indexedFields === []) {
            return; // @codeCoverageIgnore
        }

        $index = $this->readIndex($entityName);

        // Remove old entries for this record across all fields
        foreach ($index as $field => &$valueMap) {
            foreach ($valueMap as $value => &$ids) {
                $ids = array_values(array_filter($ids, fn (string $id) => $id !== $recordId));

                if ($ids === []) {
                    unset($valueMap[$value]);
                }
            }
            unset($ids);

            if ($valueMap === []) {
                unset($index[$field]);
            }
        }
        unset($valueMap);

        // Add new entries
        foreach ($indexedFields as $field) {
            $fieldValue = $this->resolveFieldValue($data, $field);

            if ($fieldValue === null) {
                continue;
            }

            if (! is_scalar($fieldValue)) {
                continue; // @codeCoverageIgnore
            }

            $stringValue = (string) $fieldValue;

            if (! isset($index[$field])) {
                $index[$field] = [];
            }

            if (! isset($index[$field][$stringValue])) {
                $index[$field][$stringValue] = [];
            }

            if (! in_array($recordId, $index[$field][$stringValue], true)) {
                $index[$field][$stringValue][] = $recordId;
            }
        }

        $this->writeIndex($entityName, $index);
    }

    /** @codeCoverageIgnore */
    private function removeRecordFromIndex(string $entityName, string $recordId): void
    {
        $indexedFields = $this->getIndexedFields($entityName);

        if ($indexedFields === []) {
            return;
        }

        $index = $this->readIndex($entityName);

        if ($index === []) {
            return;
        }

        foreach ($index as $field => &$valueMap) {
            foreach ($valueMap as $value => &$ids) {
                $ids = array_values(array_filter($ids, fn (string $id) => $id !== $recordId));

                if ($ids === []) {
                    unset($valueMap[$value]);
                }
            }
            unset($ids);

            if ($valueMap === []) {
                unset($index[$field]);
            }
        }
        unset($valueMap);

        $this->writeIndex($entityName, $index);
    }

    // ---------------------------------------------------------------
    //  Filtering helpers
    // ---------------------------------------------------------------

    /**
     * Determine the set of candidate record IDs, using the index when possible.
     *
     * @param  array<int, array<string, mixed>>  $filters
     * @return string[]
     */
    private function resolveCandidateIds(string $entityName, array $filters): array
    {
        $indexedFields = $this->getIndexedFields($entityName);
        $index = null;
        $candidateSets = [];

        foreach ($filters as $filter) {
            $filterType = isset($filter['type']) && is_string($filter['type']) ? $filter['type'] : '';
            if ($filterType !== 'and') {
                continue; // @codeCoverageIgnore
            }

            $filterOp = isset($filter['operator']) && is_string($filter['operator']) ? $filter['operator'] : '';
            if ($filterOp !== '=') {
                continue;
            }

            $field = isset($filter['field']) && is_string($filter['field']) ? $filter['field'] : '';

            if (! in_array($field, $indexedFields, true)) {
                continue; // @codeCoverageIgnore
            }

            if ($index === null) {
                $index = $this->readIndex($entityName);
            }

            $rawValue = $filter['value'] ?? '';
            $value = is_scalar($rawValue) ? (string) $rawValue : '';

            $candidateSets[] = $index[$field][$value] ?? [];
        }

        if ($candidateSets !== []) {
            // Intersect all candidate sets from indexed equality filters
            $candidates = array_shift($candidateSets);

            foreach ($candidateSets as $set) { // @codeCoverageIgnoreStart
                $candidates = array_intersect($candidates, $set);
            } // @codeCoverageIgnoreEnd

            return array_values($candidates);
        }

        return $this->listRecordIds($entityName);
    }

    /**
     * Check if a record's data matches all filter conditions.
     *
     * @param  array<string, mixed>  $data
     * @param  array<int, array<string, mixed>>  $filters
     */
    private function matchesAllFilters(array $data, array $filters): bool
    {
        foreach ($filters as $filter) {
            $field = isset($filter['field']) && is_string($filter['field']) ? $filter['field'] : '';
            $operator = isset($filter['operator']) && is_string($filter['operator']) ? $filter['operator'] : '=';
            $value = $filter['value'] ?? null;

            $fieldValue = $this->resolveFieldValue($data, $field);

            $matches = match ($operator) {
                '=' => $fieldValue === $value,
                '!=' => $fieldValue !== $value,
                '<' => $fieldValue < $value,
                '>' => $fieldValue > $value,
                '<=' => $fieldValue <= $value,
                '>=' => $fieldValue >= $value,
                'contains' => is_string($fieldValue) && is_string($value) && mb_stripos($fieldValue, $value) !== false,
                'starts_with' => is_string($fieldValue) && is_string($value) && mb_stripos($fieldValue, $value) === 0,
                'ends_with' => is_string($fieldValue) && is_string($value) && str_ends_with(mb_strtolower($fieldValue), mb_strtolower($value)),
                'in' => is_array($value) && in_array($fieldValue, $value, true),
                'not_in' => is_array($value) && ! in_array($fieldValue, $value, true),
                default => true, // @codeCoverageIgnore
            };

            if (! $matches) {
                return false;
            }
        }

        return true;
    }

    /**
     * Resolve a field value from data using dot notation.
     *
     * @param  array<string, mixed>  $data
     */
    private function resolveFieldValue(array $data, string $field): mixed
    {
        $segments = explode('.', $field);
        $current = $data;

        foreach ($segments as $segment) {
            if (! is_array($current) || ! array_key_exists($segment, $current)) {
                return null;
            }

            $current = $current[$segment];
        }

        return $current;
    }

    // ---------------------------------------------------------------
    //  Sorting
    // ---------------------------------------------------------------

    /**
     * @param  array<string, array<string, mixed>>  $results
     * @param  array<int, array<string, mixed>>  $sort
     * @return array<string, array<string, mixed>>
     */
    private function applySort(array $results, array $sort): array
    {
        /** @var array<int, array{string, array<string, mixed>}> $pairs */
        $pairs = [];
        foreach ($results as $key => $value) {
            $pairs[] = [$key, $value];
        }

        usort($pairs, function (array $a, array $b) use ($sort): int {
            foreach ($sort as $sortDescriptor) {
                $field = isset($sortDescriptor['field']) && is_string($sortDescriptor['field']) ? $sortDescriptor['field'] : '';
                $direction = isset($sortDescriptor['direction']) && is_string($sortDescriptor['direction']) ? $sortDescriptor['direction'] : 'asc';

                $aVal = $this->resolveFieldValue($a[1], $field);
                $bVal = $this->resolveFieldValue($b[1], $field);

                $cmp = $aVal <=> $bVal;

                if ($cmp !== 0) {
                    return $direction === 'desc' ? -$cmp : $cmp;
                }
            }

            return 0; // @codeCoverageIgnore
        });

        $sorted = [];

        foreach ($pairs as [$key, $value]) {
            $sorted[$key] = $value;
        }

        return $sorted;
    }

    // ---------------------------------------------------------------
    //  Utilities
    // ---------------------------------------------------------------

    /**
     * @param  array<string, mixed>  $array
     */
    private function sortKeysRecursively(array &$array): void
    {
        ksort($array);

        foreach ($array as $key => &$value) {
            if (is_array($value) && ! array_is_list($value)) {
                /** @var array<string, mixed> $child */
                $child = $value;
                $this->sortKeysRecursively($child);
                $array[$key] = $child;
            }
        }
    }
}
