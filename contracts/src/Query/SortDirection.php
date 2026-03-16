<?php

declare(strict_types=1);

namespace KDuma\SimpleDAL\Contracts\Query;

enum SortDirection: string
{
    case Asc = 'asc';
    case Desc = 'desc';
}
