<?php

declare(strict_types=1);

use KDuma\SimpleDAL\Adapter\Database\DatabaseAdapter;
use KDuma\SimpleDAL\Encryption\Sodium\SealedBoxAlgorithm;
use KDuma\SimpleDAL\Encryption\Sodium\SecretBoxAlgorithm;

abstract class EncryptionTestCase extends \PHPUnit\Framework\TestCase
{
    public \PDO $pdo;
    public DatabaseAdapter $inner;
    public DatabaseAdapter $adapter;
    public SecretBoxAlgorithm $symmetricKey;
    public SealedBoxAlgorithm $asymmetricKey;
    public SecretBoxAlgorithm $keyA;
    public SecretBoxAlgorithm $keyB;
}

uses(EncryptionTestCase::class)->in(__DIR__);
