<?php

declare(strict_types=1);

namespace KDuma\SimpleDAL\Contracts\Exception;

class EntityNotFoundException extends \InvalidArgumentException implements DataStoreExceptionInterface {}
