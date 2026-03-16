<?php

declare(strict_types=1);

namespace KDuma\SimpleDAL;

use KDuma\SimpleDAL\Contracts\RecordInterface;

final class Record implements RecordInterface
{
    public string $id {
        get => $this->_id;
    }

    /** @var array<string, mixed> */
    public array $data {
        get => $this->_data;
    }

    public ?\DateTimeImmutable $createdAt {
        get => $this->_createdAt;
    }

    public ?\DateTimeImmutable $updatedAt {
        get => $this->_updatedAt;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function __construct(
        private string $_id,
        private array $_data = [],
        private ?\DateTimeImmutable $_createdAt = null,
        private ?\DateTimeImmutable $_updatedAt = null,
    ) {}

    public function get(string $key, mixed $default = null): mixed
    {
        $segments = explode('.', $key);
        $current = $this->_data;

        foreach ($segments as $segment) {
            if (!is_array($current) || !array_key_exists($segment, $current)) {
                return $default;
            }

            $current = $current[$segment];
        }

        return $current;
    }

    public function has(string $key): bool
    {
        $segments = explode('.', $key);
        $current = $this->_data;

        foreach ($segments as $segment) {
            if (!is_array($current) || !array_key_exists($segment, $current)) {
                return false;
            }

            $current = $current[$segment];
        }

        return true;
    }

    public function set(string $key, mixed $value): static
    {
        $segments = explode('.', $key);
        $current = &$this->_data;

        foreach (array_slice($segments, 0, -1) as $segment) {
            if (!isset($current[$segment]) || !is_array($current[$segment])) {
                $current[$segment] = [];
            }

            $current = &$current[$segment];
        }

        $current[end($segments)] = $value;

        return $this;
    }

    public function unset(string $key): static
    {
        $segments = explode('.', $key);
        $current = &$this->_data;

        foreach (array_slice($segments, 0, -1) as $segment) {
            if (!isset($current[$segment]) || !is_array($current[$segment])) {
                return $this;
            }

            $current = &$current[$segment];
        }

        unset($current[end($segments)]);

        return $this;
    }

    public function merge(array $data): static
    {
        $this->_data = self::deepMerge($this->_data, $data);

        return $this;
    }

    public function toJson(int $flags = 0): string
    {
        return json_encode($this->_data, $flags | JSON_THROW_ON_ERROR);
    }

    /**
     * @internal Used by stores to update timestamps.
     */
    public function setCreatedAt(\DateTimeImmutable $createdAt): void
    {
        $this->_createdAt = $createdAt;
    }

    /**
     * @internal Used by stores to update timestamps.
     */
    public function setUpdatedAt(\DateTimeImmutable $updatedAt): void
    {
        $this->_updatedAt = $updatedAt;
    }

    /**
     * @internal Used by stores to replace data after persistence.
     *
     * @param array<string, mixed> $data
     */
    public function setData(array $data): void
    {
        $this->_data = $data;
    }

    /**
     * @param array<string, mixed> $base
     * @param array<string, mixed> $overlay
     * @return array<string, mixed>
     */
    public static function deepMerge(array $base, array $overlay): array
    {
        foreach ($overlay as $key => $value) {
            if (is_array($value) && isset($base[$key]) && is_array($base[$key])) {
                $base[$key] = self::deepMerge($base[$key], $value);
            } else {
                $base[$key] = $value;
            }
        }

        return $base;
    }
}
