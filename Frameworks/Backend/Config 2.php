<?php

/**
 * Adlaire Ecosystem - Config.php
 *
 * @version v0.201
 * @php     >= 8.3
 */

declare(strict_types=1);

if (PHP_VERSION_ID < 80300) {
    echo json_encode(['error' => 'Adlaire Ecosystem requires PHP 8.3 or higher. Current version: ' . PHP_VERSION]);
    exit(1);
}

final class ConfigRepository
{
    public function __construct(private array $items = [])
    {
    }

    public function all(): array
    {
        return $this->items;
    }

    public function has(string $key): bool
    {
        $value = $this->items;
        foreach (explode('.', $key) as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return false;
            }
            $value = $value[$segment];
        }
        return true;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $value = $this->items;
        foreach (explode('.', $key) as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }
            $value = $value[$segment];
        }
        return $value;
    }

    public function required(string $key): mixed
    {
        if (!$this->has($key)) {
            throw new InvalidArgumentException("Required config key is missing: {$key}");
        }
        return $this->get($key);
    }

    public function integer(string $key, int $default = 0): int
    {
        $value = $this->get($key, $default);
        return is_numeric($value) ? (int)$value : $default;
    }

    public function boolean(string $key, bool $default = false): bool
    {
        $value = $this->get($key, $default);
        if (is_bool($value)) {
            return $value;
        }
        if (is_scalar($value)) {
            return match (strtolower(trim((string)$value))) {
                '1', 'true', 'yes', 'on' => true,
                '0', 'false', 'no', 'off' => false,
                default => $default,
            };
        }
        return $default;
    }

    public function set(string $key, mixed $value): self
    {
        $target = &$this->items;
        foreach (explode('.', $key) as $segment) {
            if ($segment === '') {
                throw new InvalidArgumentException('Config key segment must not be empty.');
            }
            if (!isset($target[$segment]) || !is_array($target[$segment])) {
                $target[$segment] = [];
            }
            $target = &$target[$segment];
        }
        $target = $value;
        return $this;
    }

    public function merge(array $items): self
    {
        $this->items = array_replace_recursive($this->items, $items);
        return $this;
    }
}
