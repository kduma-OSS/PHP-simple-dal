<?php

declare(strict_types=1);

namespace KDuma\SimpleDAL\Adapter\Flysystem\Tests;

use KDuma\SimpleDAL\Adapter\Contracts\Tests\Concerns\AdapterConformanceTests;
use KDuma\SimpleDAL\Adapter\Flysystem\FlysystemAdapter;
use KDuma\SimpleDAL\Contracts\EntityDefinitionInterface;
use League\Flysystem\Filesystem;
use League\Flysystem\ZipArchive\FilesystemZipArchiveProvider;
use League\Flysystem\ZipArchive\ZipArchiveAdapter;
use PHPUnit\Framework\TestCase;

final class ZipConformanceTest extends TestCase
{
    use AdapterConformanceTests;

    private FlysystemAdapter $adapter;

    private string $entityName;

    private string $tempDir;

    private string $zipPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tempDir = sys_get_temp_dir().'/simple-dal-zip-test-'.uniqid();
        mkdir($this->tempDir, 0777, true);

        $this->zipPath = $this->tempDir.'/test.zip';

        $flysystemAdapter = new ZipArchiveAdapter(
            new FilesystemZipArchiveProvider($this->zipPath),
        );
        $filesystem = new Filesystem($flysystemAdapter);

        $this->adapter = new FlysystemAdapter($filesystem);
        $this->entityName = 'test_entity';

        $definition = new class('test_entity', false, true, false, ['status', 'meta.role']) implements EntityDefinitionInterface
        {
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
        if (file_exists($this->zipPath)) {
            unlink($this->zipPath);
        }

        if (is_dir($this->tempDir)) {
            rmdir($this->tempDir);
        }

        parent::tearDown();
    }
}
