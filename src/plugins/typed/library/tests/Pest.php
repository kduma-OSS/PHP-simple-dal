<?php

declare(strict_types=1);

use KDuma\SimpleDAL\Adapter\Database\DatabaseAdapter;
use KDuma\SimpleDAL\Typed\Entity\TypedCollectionDefinition;
use KDuma\SimpleDAL\Typed\Entity\TypedSingletonDefinition;
use KDuma\SimpleDAL\Typed\TypedDataStore;

abstract class TypedTestCase extends \PHPUnit\Framework\TestCase
{
    public DatabaseAdapter $adapter;
    public TypedCollectionDefinition $articlesDef;
    public TypedSingletonDefinition $settingsDef;
    public TypedDataStore $store;
}

uses(TypedTestCase::class)->in(__DIR__);
