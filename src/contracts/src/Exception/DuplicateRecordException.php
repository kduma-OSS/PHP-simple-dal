<?php

declare(strict_types=1);

namespace KDuma\SimpleDAL\Contracts\Exception;

class DuplicateRecordException extends \RuntimeException implements DataStoreExceptionInterface {}
