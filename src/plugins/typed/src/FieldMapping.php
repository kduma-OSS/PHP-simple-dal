<?php

declare(strict_types=1);

namespace KDuma\SimpleDAL\Typed;

use KDuma\SimpleDAL\Typed\Contracts\Converter\FieldConverterInterface;

readonly class FieldMapping
{
    public function __construct(
        public string $propertyName,
        public string $dataPath,
        public \ReflectionProperty $reflection,
        public ?FieldConverterInterface $converter,
        public bool $isNullable,
    ) {}
}
