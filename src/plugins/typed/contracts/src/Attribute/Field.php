<?php

declare(strict_types=1);

namespace KDuma\SimpleDAL\Typed\Contracts\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class Field
{
    /**
     * @param  string|null  $path  null = auto camelCase to snake_case
     * @param  string|null  $converter  class-string<\KDuma\SimpleDAL\Typed\Contracts\Converter\FieldConverterInterface>
     */
    public function __construct(
        public readonly ?string $path = null,
        public readonly ?string $converter = null,
    ) {}
}
