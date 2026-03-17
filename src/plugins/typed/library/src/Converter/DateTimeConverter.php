<?php

declare(strict_types=1);

namespace KDuma\SimpleDAL\Typed\Converter;

use KDuma\SimpleDAL\Typed\Contracts\Converter\FieldConverterInterface;

class DateTimeConverter implements FieldConverterInterface
{
    public function fromStorage(mixed $value): \DateTimeImmutable
    {
        assert(is_string($value));

        return new \DateTimeImmutable($value);
    }

    public function toStorage(mixed $value): string
    {
        assert($value instanceof \DateTimeImmutable);

        return $value->format(\DateTimeInterface::ATOM);
    }
}
