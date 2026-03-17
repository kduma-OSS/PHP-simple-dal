<?php

declare(strict_types=1);

namespace KDuma\SimpleDAL\Integrity;

enum FailureMode
{
    case Throw;
    case Ignore;
}
