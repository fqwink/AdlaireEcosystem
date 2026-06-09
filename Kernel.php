<?php

/**
 * Adlaire Ecosystem - Kernel.php
 *
 * @version v0.19
 * @php     >= 8.3
 */

declare(strict_types=1);

if (PHP_VERSION_ID < 80300) {
    echo json_encode(['error' => 'Adlaire Ecosystem requires PHP 8.3 or higher. Current version: ' . PHP_VERSION]);
    exit(1);
}

if (is_file(__DIR__ . '/Extension.php')) {
    require_once __DIR__ . '/Extension.php';
}

final class MicroKernel
{
    private array $services = [];
    private array $extensions = [];
    private bool $booted = false;

    public function set(string $name, mixed $service): self
    {
        if ($name === '') {
            throw new InvalidArgumentException('Kernel service name must not be empty.');
        }
        $this->services[$name] = $service;
        return $this;
    }

    public function get(string $name): mixed
    {
        if (!$this->has($name)) {
            throw new RuntimeException("Kernel service not found: {$name}");
        }
        return $this->services[$name];
    }

    public function has(string $name): bool
    {
        return array_key_exists($name, $this->services);
    }

    public function services(): array
    {
        return array_keys($this->services);
    }

    public function registerExtension(AdlaireExtension $extension): self
    {
        $name = $extension->name();
        if ($name === '') {
            throw new InvalidArgumentException('Extension name must not be empty.');
        }
        if (isset($this->extensions[$name])) {
            throw new RuntimeException("Extension already registered: {$name}");
        }

        $this->extensions[$name] = $extension;
        $extension->register($this);
        if ($this->booted) {
            $extension->boot($this);
        }
        return $this;
    }

    public function boot(): self
    {
        if ($this->booted) {
            return $this;
        }
        foreach ($this->extensions as $extension) {
            $extension->boot($this);
        }
        $this->booted = true;
        return $this;
    }

    public function extensions(): array
    {
        return array_keys($this->extensions);
    }

    public function booted(): bool
    {
        return $this->booted;
    }
}
