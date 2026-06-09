<?php

/**
 * Adlaire Ecosystem - Support.php
 *
 * @version v0.200
 * @php     >= 8.3
 */

declare(strict_types=1);

if (PHP_VERSION_ID < 80300) {
    echo json_encode(['error' => 'Adlaire Ecosystem requires PHP 8.3 or higher. Current version: ' . PHP_VERSION]);
    exit(1);
}

final class AdlaireSupport
{
    public static function dataGet(array $data, string $key, mixed $default = null): mixed
    {
        $value = $data;
        foreach (explode('.', $key) as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }
            $value = $value[$segment];
        }
        return $value;
    }

    public static function dataHas(array $data, string $key): bool
    {
        $value = $data;
        foreach (explode('.', $key) as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return false;
            }
            $value = $value[$segment];
        }
        return true;
    }

    public static function dataSet(array &$data, string $key, mixed $value): void
    {
        $target = &$data;
        foreach (explode('.', $key) as $segment) {
            if ($segment === '') {
                throw new InvalidArgumentException('Data key segment must not be empty.');
            }
            if (!isset($target[$segment]) || !is_array($target[$segment])) {
                $target[$segment] = [];
            }
            $target = &$target[$segment];
        }
        $target = $value;
    }

    public static function slug(string $value): string
    {
        $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9]+/', '-', $value) ?? '', '-'));
        return $slug === '' ? 'n-a' : $slug;
    }

    public static function snake(string $value): string
    {
        $value = preg_replace('/([a-z])([A-Z])/', '$1_$2', trim($value)) ?? '';
        $snake = strtolower(trim(preg_replace('/[^A-Za-z0-9]+/', '_', $value) ?? '', '_'));
        return $snake === '' ? 'n_a' : $snake;
    }

    public static function studly(string $value): string
    {
        $words = preg_split('/[^A-Za-z0-9]+/', $value) ?: [];
        return implode('', array_map(static fn(string $word): string => ucfirst(strtolower($word)), array_filter($words, static fn(string $word): bool => $word !== '')));
    }

    public static function classBasename(object|string $class): string
    {
        $name = is_object($class) ? $class::class : $class;
        $parts = explode('\\', trim($name, '\\'));
        return end($parts) ?: $name;
    }
}
