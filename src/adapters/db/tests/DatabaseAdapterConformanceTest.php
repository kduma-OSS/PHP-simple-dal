<?php

declare(strict_types=1);

namespace KDuma\SimpleDAL\Adapter\Database\Tests;

use KDuma\SimpleDAL\Adapter\Database\DatabaseAdapter;
use KDuma\SimpleDAL\Contracts\EntityDefinitionInterface;
use KDuma\SimpleDAL\Adapter\Contracts\Tests\Concerns\AdapterConformanceTests;
use PHPUnit\Framework\TestCase;

final class DatabaseAdapterConformanceTest extends TestCase
{
    use AdapterConformanceTests;

    private DatabaseAdapter $adapter;

    private string $entityName;

    protected function setUp(): void
    {
        parent::setUp();

        $pdo = new \PDO('sqlite::memory:');
        $this->adapter = new DatabaseAdapter($pdo);
        $this->entityName = 'test_entity';

        $definition = new class ('test_entity', false, true, true, ['status', 'meta.role']) implements EntityDefinitionInterface {
            public function __construct(
                public readonly string $name,
                public readonly bool $isSingleton,
                public readonly bool $hasAttachments,
                public readonly bool $hasTimestamps,
                public readonly array $indexedFields,
            ) {}
        };

        $this->adapter->initializeEntity($this->entityName, $definition);
    }
}
