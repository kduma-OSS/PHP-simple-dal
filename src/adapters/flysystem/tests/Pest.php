<?php

declare(strict_types=1);

use KDuma\SimpleDAL\Adapter\Flysystem\FlysystemAdapter;

abstract class FlysystemAdapterTestCase extends \PHPUnit\Framework\TestCase
{
    public string $tempDir;

    public FlysystemAdapter $adapter;

    public string $entityName;
}

uses(FlysystemAdapterTestCase::class)->in(__DIR__);
