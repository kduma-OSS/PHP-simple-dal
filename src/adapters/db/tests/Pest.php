<?php

declare(strict_types=1);

use KDuma\SimpleDAL\Adapter\Database\DatabaseAdapter;

abstract class DatabaseAdapterTestCase extends \PHPUnit\Framework\TestCase
{
    public DatabaseAdapter $adapter;
    public string $entityName;
}

uses(DatabaseAdapterTestCase::class)->in(__DIR__);
