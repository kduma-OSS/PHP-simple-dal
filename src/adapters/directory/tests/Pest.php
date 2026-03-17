<?php

declare(strict_types=1);

use KDuma\SimpleDAL\Adapter\Directory\DirectoryAdapter;

abstract class DirectoryAdapterTestCase extends \PHPUnit\Framework\TestCase
{
    public string $tempDir;
    public DirectoryAdapter $adapter;
    public string $entityName;
}

uses(DirectoryAdapterTestCase::class)->in(__DIR__);
