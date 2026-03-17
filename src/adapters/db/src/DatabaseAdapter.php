<?php

declare(strict_types=1);

namespace KDuma\SimpleDAL\Adapter\Database;

use KDuma\SimpleDAL\Adapter\Contracts\StorageAdapterInterface;
use KDuma\SimpleDAL\Adapter\Database\Schema\SchemaManager;
use KDuma\SimpleDAL\Contracts\EntityDefinitionInterface;
use KDuma\SimpleDAL\Contracts\Exception\AttachmentNotFoundException;
use KDuma\SimpleDAL\Contracts\Exception\RecordNotFoundException;

final class DatabaseAdapter implements StorageAdapterInterface
{
    private readonly SchemaManager $schema;

    public function __construct(
        private readonly \PDO $pdo,
    ) {
        $this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $this->pdo->exec('PRAGMA foreign_keys = ON');

        // Enable WAL mode for file-based databases (skip for :memory:).
        try {
            $pragmaStmt = $this->pdo->query('PRAGMA database_list');
            if ($pragmaStmt !== false) {
                $row = $pragmaStmt->fetch(\PDO::FETCH_ASSOC);
                $dbFile = is_array($row) && is_string($row['file'] ?? null) ? $row['file'] : '';
                if ($dbFile !== '' && $dbFile !== ':memory:') {
                    $this->pdo->exec('PRAGMA journal_mode = WAL');
                }
            }
        } catch (\Throwable) {
            // If we cannot determine the database file, skip WAL.
        }

        $this->schema = new SchemaManager($this->pdo);
    }

    // -----------------------------------------------------------------
    //  Entity lifecycle
    // -----------------------------------------------------------------

    public function initializeEntity(string $entityName, EntityDefinitionInterface $definition): void
    {
        $table = $this->sanitizeTableName($entityName);
        $this->schema->createEntityTables($table, $definition);
    }

    public function purgeEntity(string $entityName): void
    {
        $table = $this->sanitizeTableName($entityName);
        $this->schema->dropEntityTables($table);
    }

    // -----------------------------------------------------------------
    //  Record CRUD
    // -----------------------------------------------------------------

    public function writeRecord(string $entityName, string $recordId, array $data): void
    {
        $table = $this->sanitizeTableName($entityName);
        $now = (new \DateTimeImmutable)->format('Y-m-d\TH:i:s.uP');

        $stmt = $this->pdo->prepare(<<<SQL
            INSERT INTO {$table} (id, data, created_at, updated_at)
            VALUES (:id, :data, :created_at, :updated_at)
            ON CONFLICT(id) DO UPDATE SET
                data = excluded.data,
                updated_at = excluded.updated_at
        SQL);

        if ($stmt === false) {
            throw new \RuntimeException('Failed to prepare INSERT statement.');
        }

        $stmt->execute([
            ':id' => $recordId,
            ':data' => json_encode($data, JSON_THROW_ON_ERROR),
            ':created_at' => $now,
            ':updated_at' => $now,
        ]);
    }

    public function readRecord(string $entityName, string $recordId): array
    {
        $table = $this->sanitizeTableName($entityName);

        $stmt = $this->pdo->prepare("SELECT data FROM {$table} WHERE id = :id");

        if ($stmt === false) {
            throw new \RuntimeException('Failed to prepare SELECT statement.');
        }

        $stmt->execute([':id' => $recordId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (! is_array($row)) {
            throw new RecordNotFoundException("Record '{$recordId}' not found in entity '{$entityName}'.");
        }

        $json = $row['data'];

        if (! is_string($json)) {
            throw new \RuntimeException("Expected string data for record '{$recordId}'.");
        }

        /** @var array<string, mixed> $decoded */
        $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

        return $decoded;
    }

    public function deleteRecord(string $entityName, string $recordId): void
    {
        $table = $this->sanitizeTableName($entityName);

        $stmt = $this->pdo->prepare("DELETE FROM {$table} WHERE id = :id");

        if ($stmt === false) {
            throw new \RuntimeException('Failed to prepare DELETE statement.');
        }

        $stmt->execute([':id' => $recordId]);
    }

    public function recordExists(string $entityName, string $recordId): bool
    {
        $table = $this->sanitizeTableName($entityName);

        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM {$table} WHERE id = :id");

        if ($stmt === false) {
            throw new \RuntimeException('Failed to prepare SELECT COUNT statement.');
        }

        $stmt->execute([':id' => $recordId]);

        return (int) $stmt->fetchColumn() > 0;
    }

    public function listRecordIds(string $entityName): array
    {
        $table = $this->sanitizeTableName($entityName);

        if (! $this->tableExists($table)) {
            return [];
        }

        $stmt = $this->pdo->query("SELECT id FROM {$table}");

        if ($stmt === false) {
            throw new \RuntimeException('Failed to execute SELECT id query.');
        }

        /** @var list<string> */
        return $stmt->fetchAll(\PDO::FETCH_COLUMN);
    }

    public function findRecords(
        string $entityName,
        array $filters = [],
        array $sort = [],
        ?int $limit = null,
        int $offset = 0,
    ): array {
        $table = $this->sanitizeTableName($entityName);

        if (! $this->tableExists($table)) {
            return [];
        }

        $sql = "SELECT id, data FROM {$table}";
        $params = [];

        // ---- WHERE ----
        if ($filters !== []) {
            $clauses = [];
            foreach ($filters as $index => $filter) {
                [$clause, $filterParams] = $this->buildFilterClause($filter, $index);
                $type = isset($filter['type']) && is_string($filter['type']) ? $filter['type'] : 'and';
                $clauses[] = ['type' => $type, 'clause' => $clause];
                $params = array_merge($params, $filterParams);
            }

            $whereSql = '';
            foreach ($clauses as $i => $entry) {
                if ($i === 0) {
                    $whereSql = $entry['clause'];
                } else {
                    $connector = strtoupper($entry['type']);
                    $whereSql .= " {$connector} ".$entry['clause'];
                }
            }

            $sql .= " WHERE {$whereSql}";
        }

        // ---- ORDER BY ----
        if ($sort !== []) {
            $orderParts = [];
            foreach ($sort as $sortDescriptor) {
                $field = isset($sortDescriptor['field']) && is_string($sortDescriptor['field']) ? $sortDescriptor['field'] : '';
                $jsonPath = '$.'.$field;
                $dir = isset($sortDescriptor['direction']) && is_string($sortDescriptor['direction']) ? $sortDescriptor['direction'] : 'asc';
                $direction = strtoupper($dir);
                $orderParts[] = "json_extract(data, '{$jsonPath}') {$direction}";
            }
            $sql .= ' ORDER BY '.implode(', ', $orderParts);
        }

        // ---- LIMIT / OFFSET ----
        if ($limit !== null) {
            $sql .= ' LIMIT :limit OFFSET :offset';
            $params[':limit'] = $limit;
            $params[':offset'] = $offset;
        }

        $stmt = $this->pdo->prepare($sql);

        if ($stmt === false) {
            throw new \RuntimeException('Failed to prepare findRecords query.');
        }

        $stmt->execute($params);

        /** @var array<string, array<string, mixed>> $results */
        $results = [];
        while (($row = $stmt->fetch(\PDO::FETCH_ASSOC)) !== false) {
            if (! is_array($row)) {
                continue;
            }

            $id = $row['id'];
            $data = $row['data'];

            if (! is_string($id) || ! is_string($data)) {
                continue;
            }

            /** @var array<string, mixed> $decoded */
            $decoded = json_decode($data, true, 512, JSON_THROW_ON_ERROR);
            $results[$id] = $decoded;
        }

        return $results;
    }

    // -----------------------------------------------------------------
    //  Attachment operations
    // -----------------------------------------------------------------

    public function writeAttachment(
        string $entityName,
        string $recordId,
        string $name,
        mixed $contents,
    ): void {
        $table = $this->sanitizeTableName($entityName);

        if (is_resource($contents)) {
            $raw = stream_get_contents($contents);
            $contents = $raw !== false ? $raw : '';
        }

        if (! is_string($contents)) {
            throw new \InvalidArgumentException('Attachment contents must be a string or resource.');
        }

        $size = strlen($contents);

        $stmt = $this->pdo->prepare(<<<SQL
            INSERT INTO {$table}__attachments (record_id, name, content, size)
            VALUES (:record_id, :name, :content, :size)
            ON CONFLICT(record_id, name) DO UPDATE SET
                content = excluded.content,
                size = excluded.size
        SQL);

        if ($stmt === false) {
            throw new \RuntimeException('Failed to prepare INSERT attachment statement.');
        }

        $stmt->bindValue(':record_id', $recordId, \PDO::PARAM_STR);
        $stmt->bindValue(':name', $name, \PDO::PARAM_STR);
        $stmt->bindValue(':content', $contents, \PDO::PARAM_LOB);
        $stmt->bindValue(':size', $size, \PDO::PARAM_INT);
        $stmt->execute();
    }

    public function readAttachment(string $entityName, string $recordId, string $name): mixed
    {
        $table = $this->sanitizeTableName($entityName);

        $stmt = $this->pdo->prepare(
            "SELECT content FROM {$table}__attachments WHERE record_id = :record_id AND name = :name"
        );

        if ($stmt === false) {
            throw new \RuntimeException('Failed to prepare SELECT attachment statement.');
        }

        $stmt->execute([':record_id' => $recordId, ':name' => $name]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (! is_array($row)) {
            throw new AttachmentNotFoundException(
                "Attachment '{$name}' not found for record '{$recordId}' in entity '{$entityName}'."
            );
        }

        /** @var string $content */
        $content = $row['content'];

        $stream = fopen('php://memory', 'r+');

        if ($stream === false) {
            throw new \RuntimeException('Failed to open php://memory stream.');
        }

        fwrite($stream, $content);
        rewind($stream);

        return $stream;
    }

    public function deleteAttachment(string $entityName, string $recordId, string $name): void
    {
        $table = $this->sanitizeTableName($entityName);

        $stmt = $this->pdo->prepare(
            "DELETE FROM {$table}__attachments WHERE record_id = :record_id AND name = :name"
        );

        if ($stmt === false) {
            throw new \RuntimeException('Failed to prepare DELETE attachment statement.');
        }

        $stmt->execute([':record_id' => $recordId, ':name' => $name]);
    }

    public function deleteAllAttachments(string $entityName, string $recordId): void
    {
        $table = $this->sanitizeTableName($entityName);

        $stmt = $this->pdo->prepare(
            "DELETE FROM {$table}__attachments WHERE record_id = :record_id"
        );

        if ($stmt === false) {
            throw new \RuntimeException('Failed to prepare DELETE all attachments statement.');
        }

        $stmt->execute([':record_id' => $recordId]);
    }

    public function listAttachments(string $entityName, string $recordId): array
    {
        $table = $this->sanitizeTableName($entityName);

        $stmt = $this->pdo->prepare(
            "SELECT name FROM {$table}__attachments WHERE record_id = :record_id"
        );

        if ($stmt === false) {
            throw new \RuntimeException('Failed to prepare SELECT attachment names statement.');
        }

        $stmt->execute([':record_id' => $recordId]);

        /** @var list<string> */
        return $stmt->fetchAll(\PDO::FETCH_COLUMN);
    }

    public function attachmentExists(string $entityName, string $recordId, string $name): bool
    {
        $table = $this->sanitizeTableName($entityName);

        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*) FROM {$table}__attachments WHERE record_id = :record_id AND name = :name"
        );

        if ($stmt === false) {
            throw new \RuntimeException('Failed to prepare SELECT COUNT attachment statement.');
        }

        $stmt->execute([':record_id' => $recordId, ':name' => $name]);

        return (int) $stmt->fetchColumn() > 0;
    }

    // -----------------------------------------------------------------
    //  Private helpers
    // -----------------------------------------------------------------

    /**
     * Check whether a table exists in the database.
     */
    private function tableExists(string $table): bool
    {
        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*) FROM sqlite_master WHERE type = 'table' AND name = :name"
        );

        if ($stmt === false) {
            throw new \RuntimeException('Failed to prepare tableExists query.');
        }

        $stmt->execute([':name' => $table]);

        return (int) $stmt->fetchColumn() > 0;
    }

    /**
     * Sanitize entity name to prevent SQL injection in table names.
     *
     * @throws \InvalidArgumentException
     */
    private function sanitizeTableName(string $entityName): string
    {
        if (preg_match('/^[a-zA-Z0-9_]+$/', $entityName) !== 1) {
            throw new \InvalidArgumentException(
                "Invalid entity name '{$entityName}'. Only alphanumeric characters and underscores are allowed."
            );
        }

        return $entityName;
    }

    /**
     * Build a single WHERE clause fragment and its bound parameters from a filter descriptor.
     *
     * @param  array<string, mixed>  $filter
     * @return array{0: string, 1: array<string, mixed>}
     */
    private function buildFilterClause(array $filter, int $index): array
    {
        $field = is_string($filter['field'] ?? null) ? $filter['field'] : '';
        $operator = is_string($filter['operator'] ?? null) ? $filter['operator'] : '=';
        $value = $filter['value'] ?? null;
        $jsonPath = '$.'.$field;
        $jsonExpr = "json_extract(data, '{$jsonPath}')";
        $paramName = ":filter_{$index}";
        $params = [];

        switch ($operator) {
            case '=':
            case '!=':
            case '<':
            case '>':
            case '<=':
            case '>=':
                if (is_int($value) || is_float($value)) {
                    // Cast the JSON value to REAL for proper numeric comparison.
                    // SQLite json_extract returns native JSON types, but PDO bind
                    // may coerce to string. Using CAST ensures numeric comparison.
                    $clause = "CAST({$jsonExpr} AS REAL) {$operator} {$paramName}";
                } else {
                    $clause = "{$jsonExpr} {$operator} {$paramName}";
                }
                $params[$paramName] = $value;
                break;

            case 'contains':
                if (! is_string($value)) {
                    throw new \InvalidArgumentException("Filter 'contains' requires a string value.");
                }
                $clause = "{$jsonExpr} LIKE {$paramName}";
                $params[$paramName] = '%'.$value.'%';
                break;

            case 'starts_with':
                if (! is_string($value)) {
                    throw new \InvalidArgumentException("Filter 'starts_with' requires a string value.");
                }
                $clause = "{$jsonExpr} LIKE {$paramName}";
                $params[$paramName] = $value.'%';
                break;

            case 'ends_with':
                if (! is_string($value)) {
                    throw new \InvalidArgumentException("Filter 'ends_with' requires a string value.");
                }
                $clause = "{$jsonExpr} LIKE {$paramName}";
                $params[$paramName] = '%'.$value;
                break;

            case 'in':
            case 'not_in':
                $placeholders = [];
                foreach ((array) $value as $i => $v) {
                    $p = ":filter_{$index}_{$i}";
                    $placeholders[] = $p;
                    $params[$p] = $v;
                }
                $inList = implode(', ', $placeholders);
                $sqlOp = $operator === 'in' ? 'IN' : 'NOT IN';
                $clause = "{$jsonExpr} {$sqlOp} ({$inList})";
                break;

            default:
                throw new \InvalidArgumentException("Unsupported filter operator: {$operator}");
        }

        return [$clause, $params];
    }
}
