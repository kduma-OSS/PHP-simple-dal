<?php

declare(strict_types=1);

namespace KDuma\SimpleDAL\Typed;

use KDuma\SimpleDAL\Contracts\RecordInterface;
use KDuma\SimpleDAL\Typed\Contracts\Attribute\Field;
use KDuma\SimpleDAL\Typed\Contracts\TypedRecord;
use KDuma\SimpleDAL\Typed\Converter\EnumConverter;
use Spatie\Attributes\Attributes;

class TypedRecordHydrator
{
    /** @var array<class-string, FieldMapping[]> */
    private static array $cache = [];

    /**
     * Discover #[Field] properties on a TypedRecord subclass.
     * Results are cached per class.
     *
     * @param  class-string<TypedRecord>  $class
     * @return FieldMapping[]
     */
    public static function discoverFields(string $class): array
    {
        if (isset(self::$cache[$class])) {
            return self::$cache[$class];
        }

        $mappings = [];
        $targets = Attributes::find($class, Field::class);

        foreach ($targets as $target) {
            // Only handle property targets
            if (! $target->target instanceof \ReflectionProperty) {
                continue;
            }

            $prop = $target->target;
            $field = $target->attribute;

            // Determine data path
            $path = $field->path ?? self::camelToSnake($prop->getName());

            // Determine converter
            $converter = null;

            if ($field->converter !== null) {
                $converter = new ($field->converter)();
            } else {
                // Auto-detect backed enum
                $type = $prop->getType();

                if ($type instanceof \ReflectionNamedType && ! $type->isBuiltin()) {
                    $typeName = $type->getName();

                    if (enum_exists($typeName)) {
                        $ref = new \ReflectionEnum($typeName);

                        if ($ref->isBacked()) {
                            $converter = new EnumConverter($typeName);
                        }
                    }
                }
            }

            // Check nullable
            $isNullable = false;
            $type = $prop->getType();

            if ($type instanceof \ReflectionNamedType) {
                $isNullable = $type->allowsNull();
            }

            $mappings[] = new FieldMapping(
                propertyName: $prop->getName(),
                dataPath: $path,
                reflection: $prop,
                converter: $converter,
                isNullable: $isNullable,
            );
        }

        self::$cache[$class] = $mappings;

        return $mappings;
    }

    /**
     * Hydrate a TypedRecord from a RecordInterface (generic record from the base library).
     *
     * @param  class-string<TypedRecord>  $class
     */
    public static function hydrateFromRecord(string $class, RecordInterface $record): TypedRecord
    {
        $mappings = self::discoverFields($class);
        $data = $record->data;

        // Create instance via reflection (protected constructor)
        $ref = new \ReflectionClass($class);
        $instance = $ref->newInstanceWithoutConstructor();

        // Set readonly id, createdAt, updatedAt via reflection
        $baseRef = new \ReflectionClass(TypedRecord::class);

        $idProp = $baseRef->getProperty('id');
        $idProp->setValue($instance, $record->id);

        $caProp = $baseRef->getProperty('createdAt');
        $caProp->setValue($instance, $record->createdAt);

        $uaProp = $baseRef->getProperty('updatedAt');
        $uaProp->setValue($instance, $record->updatedAt);

        // Set field mappings on the instance
        $instance->_setFieldMappings($mappings);

        // Extract mapped fields from data into typed properties
        foreach ($mappings as $mapping) {
            $value = self::extractFromData($data, $mapping->dataPath);
            self::removeFromData($data, $mapping->dataPath);

            if ($value === null && $mapping->isNullable) {
                $mapping->reflection->setValue($instance, null);
            } elseif ($value !== null) {
                if ($mapping->converter !== null) {
                    $value = $mapping->converter->fromStorage($value);
                }

                $mapping->reflection->setValue($instance, $value);
            }
            // If value is null and not nullable, leave property uninitialized
        }

        // Store remaining non-mapped data
        $instance->_setExtraData($data);

        return $instance;
    }

    /**
     * Create a blank TypedRecord instance for populating before persistence.
     *
     * @param  class-string<TypedRecord>  $class
     */
    public static function createBlank(string $class): TypedRecord
    {
        $mappings = self::discoverFields($class);

        $ref = new \ReflectionClass($class);
        $instance = $ref->newInstanceWithoutConstructor();

        $baseRef = new \ReflectionClass(TypedRecord::class);
        $baseRef->getProperty('id')->setValue($instance, '');
        $baseRef->getProperty('createdAt')->setValue($instance, null);
        $baseRef->getProperty('updatedAt')->setValue($instance, null);

        $instance->_setFieldMappings($mappings);
        $instance->_setExtraData([]);

        return $instance;
    }

    /**
     * Dehydrate a TypedRecord back to a storage array.
     */
    public static function dehydrateToArray(TypedRecord $record): array
    {
        $mappings = $record->_getFieldMappings();
        $data = $record->_getExtraData();

        foreach ($mappings as $mapping) {
            if (! $mapping->reflection->isInitialized($record)) {
                continue;
            }

            $value = $mapping->reflection->getValue($record);

            if ($value !== null && $mapping->converter !== null) {
                $value = $mapping->converter->toStorage($value);
            }

            self::setInData($data, $mapping->dataPath, $value);
        }

        return $data;
    }

    /**
     * Convert camelCase to snake_case.
     */
    public static function camelToSnake(string $name): string
    {
        return strtolower(preg_replace('/[A-Z]/', '_$0', lcfirst($name)));
    }

    /**
     * Extract a value from a nested array using dot-notation.
     */
    private static function extractFromData(array $data, string $path): mixed
    {
        $segments = explode('.', $path);
        $current = $data;

        foreach ($segments as $segment) {
            if (! is_array($current) || ! array_key_exists($segment, $current)) {
                return null;
            }

            $current = $current[$segment];
        }

        return $current;
    }

    /**
     * Remove a value from a nested array using dot-notation.
     */
    private static function removeFromData(array &$data, string $path): void
    {
        $segments = explode('.', $path);
        $current = &$data;

        foreach (array_slice($segments, 0, -1) as $segment) {
            if (! isset($current[$segment]) || ! is_array($current[$segment])) {
                return;
            }

            $current = &$current[$segment];
        }

        unset($current[end($segments)]);
    }

    /**
     * Set a value in a nested array using dot-notation.
     */
    private static function setInData(array &$data, string $path, mixed $value): void
    {
        $segments = explode('.', $path);
        $current = &$data;

        foreach (array_slice($segments, 0, -1) as $segment) {
            if (! isset($current[$segment]) || ! is_array($current[$segment])) {
                $current[$segment] = [];
            }

            $current = &$current[$segment];
        }

        $current[end($segments)] = $value;
    }

    /**
     * Clear the field mapping cache. Useful for testing.
     */
    public static function clearCache(): void
    {
        self::$cache = [];
    }
}
