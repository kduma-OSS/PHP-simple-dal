<?php

declare(strict_types=1);

namespace KDuma\SimpleDAL\Contracts\Exception;

class CorruptedDataException extends \RuntimeException implements DataStoreExceptionInterface
{
}
