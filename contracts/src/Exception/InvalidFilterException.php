<?php

declare(strict_types=1);

namespace KDuma\SimpleDAL\Contracts\Exception;

class InvalidFilterException extends \InvalidArgumentException implements DataStoreExceptionInterface
{
}
