<?php

/**
 * Adlaire Ecosystem - Config.php
 *
 * @version v0.277
 * @php     >= 8.3
 */

declare(strict_types=1);

if (is_file(__DIR__ . '/Support.php')) {
    require_once __DIR__ . '/Support.php';
}

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
        return AdlaireSupport::dataHas($this->items, $key);
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return AdlaireSupport::dataGet($this->items, $key, $default);
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
        return AdlaireSupport::bool($this->get($key, $default), $default);
    }

    public function set(string $key, mixed $value): self
    {
        AdlaireSupport::dataSet($this->items, $key, $value);
        return $this;
    }

    public function forget(string $key): self
    {
        AdlaireSupport::dataForget($this->items, $key);
        return $this;
    }

    public function merge(array $items): self
    {
        $this->items = array_replace_recursive($this->items, $items);
        return $this;
    }
}
