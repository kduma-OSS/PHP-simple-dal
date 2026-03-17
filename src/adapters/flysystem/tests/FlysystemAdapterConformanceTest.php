<?php

declare(strict_types=1);

namespace KDuma\SimpleDAL\Adapter\Flysystem\Tests;

use KDuma\SimpleDAL\Adapter\Contracts\Tests\Concerns\AdapterConformanceTests;
use KDuma\SimpleDAL\Adapter\Flysystem\FlysystemAdapter;
use KDuma\SimpleDAL\Contracts\EntityDefinitionInterface;
use League\Flysystem\Filesystem;
use League\Flysystem\Local\LocalFilesystemAdapter;
use PHPUnit\Framework\TestCase;

final class FlysystemAdapterConformanceTest extends TestCase
{
    use AdapterConformanceTests;

    private FlysystemAdapter $adapter;

    private string $entityName;

    private string $tempDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tempDir = sys_get_temp_dir().'/simple-dal-test-'.uniqid();
        mkdir($this->tempDir, 0777, true);

        $flysystem = new Filesystem(new LocalFilesystemAdapter($this->tempDir));

        $this->adapter = new FlysystemAdapter($flysystem);
        $this->entityName = 'test_entity';

        $definition = new class('test_entity', false, true, false, ['status', 'meta.role']) implements EntityDefinitionInterface
        {
            /** @param array<string> $indexedFields */
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

    protected function tearDown(): void
    {
        if (is_dir($this->tempDir)) {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($this->tempDir, \RecursiveDirectoryIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::CHILD_FIRST,
            );

            foreach ($iterator as $file) {
                assert($file instanceof \SplFileInfo);
                if ($file->isDir()) {
                    rmdir($file->getPathname());
                } else {
                    unlink($file->getPathname());
                }
            }

            rmdir($this->tempDir);
        }

        parent::tearDown();
    }
}
