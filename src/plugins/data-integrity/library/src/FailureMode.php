<?php

declare(strict_types=1);

namespace KDuma\SimpleDAL\DataIntegrity;

enum FailureMode
{
    case Throw;
    case Ignore;
}
