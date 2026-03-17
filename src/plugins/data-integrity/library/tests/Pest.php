<?php

declare(strict_types=1);

use KDuma\SimpleDAL\Adapter\Database\DatabaseAdapter;
use KDuma\SimpleDAL\DataIntegrity\Contracts\HashingAlgorithmInterface;
use KDuma\SimpleDAL\DataIntegrity\Contracts\SigningAlgorithmInterface;

abstract class DataIntegrityTestCase extends \PHPUnit\Framework\TestCase
{
    public \PDO $pdo;
    public DatabaseAdapter $adapter;
    public DatabaseAdapter $inner;
    public HashingAlgorithmInterface $hasher;
    public SigningAlgorithmInterface $signer;
    public SigningAlgorithmInterface $signerB;
}

uses(DataIntegrityTestCase::class)->in(__DIR__);
