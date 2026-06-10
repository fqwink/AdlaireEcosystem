<?php

/**
 * Adlaire Ecosystem - Extension.php
 *
 * @version v0.277
 * @php     >= 8.3
 */

declare(strict_types=1);

if (PHP_VERSION_ID < 80300) {
    echo json_encode(['error' => 'Adlaire Ecosystem requires PHP 8.3 or higher. Current version: ' . PHP_VERSION]);
    exit(1);
}

interface AdlaireExtension
{
    public function name(): string;

    public function register(MicroKernel $kernel): void;

    public function boot(MicroKernel $kernel): void;
}

interface AutonomousModule
{
    public function id(): string;

    public function responsibility(): string;

    public function dependencies(): array;

    public function handle(string $message, array $payload = []): mixed;

    public function health(): array;
}

interface PolicyRule
{
    public function name(): string;

    public function evaluate(array $context = []): array;
}

final class ApplicationModuleBoundary implements AutonomousModule
{
    public function id(): string
    {
        return 'Applications';
    }

    public function responsibility(): string
    {
        return 'Application feature module boundary';
    }

    public function dependencies(): array
    {
        return ['documented specification'];
    }

    public function handle(string $message, array $payload = []): mixed
    {
        return match ($message) {
            'applications.status' => $this->status(),
            'applications.policy' => $this->policy(),
            'applications.manifest' => $this->manifest(),
            'applications.validate' => $this->validatePolicy($payload),
            'applications.metadata' => [
                'module' => $this->id(),
                'payload' => $payload,
                'responsibility' => $this->responsibility(),
                'dependencies' => $this->dependencies(),
            ],
            default => throw new RuntimeException("Unsupported application module boundary message: {$message}"),
        };
    }

    public function health(): array
    {
        return [
            'status' => 'ready',
            'module' => $this->id(),
            'base_directory' => 'Applications',
            'deployment_dependency_allowed' => false,
            'legacy_modules_directory_allowed' => false,
        ];
    }

    private function status(): array
    {
        return [
            'base_directory' => 'Applications',
            'purpose' => 'application feature layer',
            'examples' => ['CMS', 'Commerce', 'StaticGenerator', 'Wiki'],
            'deployment_dependency_allowed' => false,
            'legacy_modules_directory_allowed' => false,
        ];
    }

    private function policy(): array
    {
        return [
            'base_directory' => 'Applications',
            'module_role' => 'application feature module',
            'deployment_framework_dependency_allowed' => false,
            'legacy_modules_directory_allowed' => false,
            'default_file_principle' => '5 files',
            'source_of_truth' => 'Adlaire Ecosystem documentation',
        ];
    }

    private function manifest(): array
    {
        return [
            'id' => $this->id(),
            'base_directory' => 'Applications',
            'messages' => ['applications.status', 'applications.policy', 'applications.metadata', 'applications.manifest', 'applications.validate'],
            'health' => $this->health(),
            'policy' => $this->policy(),
        ];
    }

    private function validatePolicy(array $policy): array
    {
        $checks = [
            'base_directory' => ($policy['base_directory'] ?? null) === 'Applications',
            'deployment_framework_dependency_allowed' => ($policy['deployment_framework_dependency_allowed'] ?? true) === false,
            'legacy_modules_directory_allowed' => ($policy['legacy_modules_directory_allowed'] ?? true) === false,
            'default_file_principle' => ($policy['default_file_principle'] ?? null) === '5 files',
        ];

        return [
            'valid' => !in_array(false, $checks, true),
            'checks' => $checks,
        ];
    }
}
