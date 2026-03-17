<?php

declare(strict_types=1);

use KDuma\SimpleDAL\Adapter\Database\DatabaseAdapter;
use KDuma\SimpleDAL\Integrity\Contracts\HashingAlgorithmInterface;
use KDuma\SimpleDAL\Integrity\Contracts\SigningAlgorithmInterface;
use PHPUnit\Framework\TestCase;

abstract class IntegrityTestCase extends TestCase
{
    public PDO $pdo;

    public DatabaseAdapter $adapter;

    public DatabaseAdapter $inner;

    public HashingAlgorithmInterface $hasher;

    public SigningAlgorithmInterface $signer;

    public SigningAlgorithmInterface $signerB;
}

uses(IntegrityTestCase::class)->in(__DIR__);
