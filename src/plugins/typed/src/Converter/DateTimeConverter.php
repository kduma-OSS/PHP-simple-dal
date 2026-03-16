<?php

declare(strict_types=1);

namespace KDuma\SimpleDAL\Typed\Converter;

use KDuma\SimpleDAL\Typed\Contracts\Converter\FieldConverterInterface;

class DateTimeConverter implements FieldConverterInterface
{
    public function fromStorage(mixed $value): mixed
    {
        return new \DateTimeImmutable($value);
    }

    public function toStorage(mixed $value): mixed
    {
        return $value->format(\DateTimeInterface::ATOM);
    }
}
