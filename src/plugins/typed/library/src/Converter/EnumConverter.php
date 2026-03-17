<?php

declare(strict_types=1);

namespace KDuma\SimpleDAL\Typed\Converter;

use KDuma\SimpleDAL\Typed\Contracts\Converter\FieldConverterInterface;

class EnumConverter implements FieldConverterInterface
{
    /**
     * @param  class-string<\BackedEnum>  $enumClass
     */
    public function __construct(
        private readonly string $enumClass,
    ) {}

    public function fromStorage(mixed $value): \BackedEnum
    {
        assert(is_int($value) || is_string($value));

        return $this->enumClass::from($value);
    }

    public function toStorage(mixed $value): int|string
    {
        assert($value instanceof \BackedEnum);

        return $value->value;
    }
}
