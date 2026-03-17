<?php

declare(strict_types=1);

use KDuma\SimpleDAL\Adapter\Flysystem\FlysystemAdapter;
use PHPUnit\Framework\TestCase;

abstract class FlysystemAdapterTestCase extends TestCase
{
    public string $tempDir;

    public FlysystemAdapter $adapter;

    public string $entityName;
}

uses(FlysystemAdapterTestCase::class)->in(__DIR__);
