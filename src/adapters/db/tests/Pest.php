<?php

declare(strict_types=1);

use KDuma\SimpleDAL\Adapter\Database\DatabaseAdapter;
use PHPUnit\Framework\TestCase;

abstract class DatabaseAdapterTestCase extends TestCase
{
    public DatabaseAdapter $adapter;

    public string $entityName;
}

uses(DatabaseAdapterTestCase::class)->in(__DIR__);
