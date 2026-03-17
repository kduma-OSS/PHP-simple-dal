<?php

declare(strict_types=1);

namespace KDuma\SimpleDAL\Encryption;

class EncryptionRule
{
    /** @var string[]|null */
    private readonly ?array $normalizedAttachmentNames;

    /** @var string[]|null */
    private readonly ?array $normalizedRecordIds;

    /**
     * @param  string|string[]|\BackedEnum|\BackedEnum[]|null  $attachmentNames  null = all
     * @param  string|string[]|null  $recordIds  null = all records
     */
    public function __construct(
        public readonly string $keyId,
        public readonly string $entityName,
        string|array|\BackedEnum|null $attachmentNames = null,
        string|array|null $recordIds = null,
    ) {
        $this->normalizedAttachmentNames = self::normalizeNames($attachmentNames);
        $this->normalizedRecordIds = self::normalizeStringArray($recordIds);
    }

    public function matches(string $entityName, string $recordId, string $attachmentName): bool
    {
        if ($this->entityName !== $entityName) {
            return false;
        }

        if ($this->normalizedRecordIds !== null && ! in_array($recordId, $this->normalizedRecordIds, true)) {
            return false;
        }

        if ($this->normalizedAttachmentNames !== null && ! in_array($attachmentName, $this->normalizedAttachmentNames, true)) {
            return false;
        }

        return true;
    }

    /**
     * @return string[]|null
     */
    private static function normalizeNames(string|array|\BackedEnum|null $value): ?array
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof \BackedEnum) {
            return [(string) $value->value];
        }

        if (is_string($value)) {
            return [$value];
        }

        return array_map(
            fn (string|\BackedEnum $v) => $v instanceof \BackedEnum ? (string) $v->value : $v,
            $value,
        );
    }

    /**
     * @return string[]|null
     */
    private static function normalizeStringArray(string|array|null $value): ?array
    {
        if ($value === null) {
            return null;
        }

        if (is_string($value)) {
            return [$value];
        }

        return $value;
    }
}
