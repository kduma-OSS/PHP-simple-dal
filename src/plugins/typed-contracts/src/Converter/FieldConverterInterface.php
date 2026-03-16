<?php

declare(strict_types=1);

namespace KDuma\SimpleDAL\Typed\Contracts\Converter;

interface FieldConverterInterface
{
    public function fromStorage(mixed $value): mixed;

    public function toStorage(mixed $value): mixed;
}
