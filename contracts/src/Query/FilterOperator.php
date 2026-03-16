<?php

declare(strict_types=1);

namespace KDuma\SimpleDAL\Contracts\Query;

enum FilterOperator: string
{
    case Equals = '=';
    case NotEquals = '!=';
    case LessThan = '<';
    case GreaterThan = '>';
    case LessThanOrEqual = '<=';
    case GreaterThanOrEqual = '>=';
    case Contains = 'contains';
    case StartsWith = 'starts_with';
    case EndsWith = 'ends_with';
    case In = 'in';
    case NotIn = 'not_in';
}
