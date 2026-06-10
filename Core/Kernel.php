<?php

/**
 * Adlaire Ecosystem - Kernel.php
 *
 * @version v0.277
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
    private array $extensionStates = [];
    private array $extensionDependencies = [];
    private array $extensionConfigs = [];
    private array $extensionSchemas = [];
    private array $allowedServices = [];
    private array $listeners = [];
    private array $modules = [];
    private array $messageHandlers = [];
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
        $this->extensionStates[$name] = 'registered';
        $extension->register($this);
        if ($this->booted) {
            $this->bootExtension($extension);
        }
        return $this;
    }

    public function boot(): self
    {
        if ($this->booted) {
            return $this;
        }
        foreach ($this->extensions as $extension) {
            $this->bootExtension($extension);
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

    public function requires(string $extension, array $dependencies): self
    {
        $this->assertName($extension, 'Extension name');
        foreach ($dependencies as $dependency) {
            $this->assertName((string)$dependency, 'Extension dependency');
        }
        if (in_array($extension, $dependencies, true)) {
            throw new InvalidArgumentException("Extension dependency cycle detected: {$extension}");
        }
        $this->extensionDependencies[$extension] = array_values($dependencies);
        return $this;
    }

    public function extensionInfo(?string $name = null): array
    {
        $info = [];
        foreach ($this->extensions as $extensionName => $_extension) {
            $info[$extensionName] = [
                'name' => $extensionName,
                'state' => $this->extensionStates[$extensionName] ?? 'registered',
                'dependencies' => $this->extensionDependencies[$extensionName] ?? [],
                'config_valid' => $this->validateExtensionConfig($extensionName),
                'allowed_services' => $this->allowedServices[$extensionName] ?? [],
            ];
        }
        if ($name !== null) {
            return $info[$name] ?? throw new RuntimeException("Extension not found: {$name}");
        }
        return $info;
    }

    public function on(string $event, callable $listener): self
    {
        $this->assertName($event, 'Event name');
        $this->listeners[$event][] = $listener;
        return $this;
    }

    public function emit(string $event, array $payload = []): array
    {
        $this->assertName($event, 'Event name');
        $results = [];
        foreach ($this->listeners[$event] ?? [] as $listener) {
            $results[] = $listener($payload, $this);
        }
        return $results;
    }

    public function configureExtension(string $extension, array $config, array $schema = []): self
    {
        $this->assertName($extension, 'Extension name');
        $this->extensionConfigs[$extension] = $config;
        $this->extensionSchemas[$extension] = $schema;
        if (!$this->validateExtensionConfig($extension)) {
            throw new InvalidArgumentException("Invalid extension config: {$extension}");
        }
        return $this;
    }

    public function extensionConfig(string $extension): array
    {
        $this->assertName($extension, 'Extension name');
        return $this->extensionConfigs[$extension] ?? [];
    }

    public function allowServices(string $extension, array $services): self
    {
        $this->assertName($extension, 'Extension name');
        foreach ($services as $service) {
            $this->assertName((string)$service, 'Service name');
        }
        $this->allowedServices[$extension] = array_values($services);
        return $this;
    }

    public function serviceFor(string $extension, string $service): mixed
    {
        $allowed = $this->allowedServices[$extension] ?? [];
        if (!in_array($service, $allowed, true)) {
            throw new RuntimeException("Service is not allowed for extension {$extension}: {$service}");
        }
        return $this->get($service);
    }

    public function registerModule(AutonomousModule $module): self
    {
        $id = $module->id();
        $this->assertName($id, 'Module id');
        if (isset($this->modules[$id])) {
            throw new RuntimeException("Module already registered: {$id}");
        }
        $this->modules[$id] = $module;
        return $this;
    }

    public function modules(): array
    {
        return array_keys($this->modules);
    }

    public function handle(string $message, callable $handler): self
    {
        $this->assertName($message, 'Message name');
        $this->messageHandlers[$message] = $handler;
        return $this;
    }

    public function send(string $module, string $message, array $payload = []): mixed
    {
        $this->assertName($module, 'Module id');
        $this->assertName($message, 'Message name');
        if (isset($this->messageHandlers[$message])) {
            return ($this->messageHandlers[$message])($module, $payload, $this);
        }
        if (!isset($this->modules[$module])) {
            throw new RuntimeException("Module not found: {$module}");
        }
        return $this->modules[$module]->handle($message, $payload);
    }

    public function healthReport(): array
    {
        $modules = [];
        $overall = 'ready';
        foreach ($this->modules as $id => $module) {
            $health = $module->health();
            $status = $health['status'] ?? 'failed';
            if ($status === 'failed') {
                $overall = 'failed';
            } elseif ($status === 'degraded' && $overall !== 'failed') {
                $overall = 'degraded';
            }
            $modules[$id] = $health;
        }

        return [
            'status' => $overall,
            'extensions' => $this->extensionInfo(),
            'modules' => $modules,
        ];
    }

    public function extensionManifest(): array
    {
        return [
            'extensions' => $this->extensionInfo(),
            'services' => $this->services(),
            'modules' => $this->modules(),
            'booted' => $this->booted,
        ];
    }

    private function bootExtension(AdlaireExtension $extension): void
    {
        $name = $extension->name();
        foreach ($this->extensionDependencies[$name] ?? [] as $dependency) {
            if (!isset($this->extensions[$dependency])) {
                $this->extensionStates[$name] = 'failed';
                throw new RuntimeException("Missing extension dependency for {$name}: {$dependency}");
            }
        }
        if (!$this->validateExtensionConfig($name)) {
            $this->extensionStates[$name] = 'failed';
            throw new RuntimeException("Invalid extension config: {$name}");
        }
        $extension->boot($this);
        $this->extensionStates[$name] = 'booted';
    }

    private function validateExtensionConfig(string $extension): bool
    {
        $config = $this->extensionConfigs[$extension] ?? [];
        foreach ($this->extensionSchemas[$extension] ?? [] as $key => $type) {
            if (!array_key_exists($key, $config)) {
                return false;
            }
            $value = $config[$key];
            $valid = match ($type) {
                'string' => is_string($value),
                'int' => is_int($value),
                'bool' => is_bool($value),
                'array' => is_array($value),
                default => true,
            };
            if (!$valid) {
                return false;
            }
        }
        return true;
    }

    private function assertName(string $name, string $label): void
    {
        if ($name === '') {
            throw new InvalidArgumentException("{$label} must not be empty.");
        }
    }
}
