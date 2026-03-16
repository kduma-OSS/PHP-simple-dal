<?php

declare(strict_types=1);

namespace KDuma\SimpleDAL\Adapter\Database\Schema;

use KDuma\SimpleDAL\Contracts\EntityDefinitionInterface;

final class SchemaManager
{
    public function __construct(
        private readonly \PDO $pdo,
    ) {}

    /**
     * Create the record table, attachment table, and expression indexes for an entity.
     */
    public function createEntityTables(string $tableName, EntityDefinitionInterface $definition): void
    {
        $this->createRecordTable($tableName);
        $this->createAttachmentTable($tableName);
        $this->createFieldIndexes($tableName, $definition->indexedFields);
    }

    /**
     * Drop both tables for an entity.
     */
    public function dropEntityTables(string $tableName): void
    {
        $this->pdo->exec("DROP TABLE IF EXISTS {$tableName}__attachments");
        $this->pdo->exec("DROP TABLE IF EXISTS {$tableName}");
    }

    private function createRecordTable(string $tableName): void
    {
        $this->pdo->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS {$tableName} (
                id TEXT PRIMARY KEY,
                data JSON NOT NULL,
                created_at TEXT,
                updated_at TEXT
            )
        SQL);
    }

    private function createAttachmentTable(string $tableName): void
    {
        $this->pdo->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS {$tableName}__attachments (
                record_id TEXT NOT NULL,
                name TEXT NOT NULL,
                mime_type TEXT NOT NULL DEFAULT 'application/octet-stream',
                content BLOB NOT NULL,
                size INTEGER NOT NULL,
                PRIMARY KEY (record_id, name),
                FOREIGN KEY (record_id) REFERENCES {$tableName}(id) ON DELETE CASCADE
            )
        SQL);
    }

    /**
     * @param  string[]  $fields
     */
    private function createFieldIndexes(string $tableName, array $fields): void
    {
        foreach ($fields as $field) {
            $fieldSafe = str_replace('.', '_', $field);
            $jsonPath = '$.'.$field;

            $this->pdo->exec(
                "CREATE INDEX IF NOT EXISTS idx_{$tableName}_{$fieldSafe} ON {$tableName} (json_extract(data, '{$jsonPath}'))"
            );
        }
    }
}
