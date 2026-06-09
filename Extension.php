<?php

/**
 * Adlaire Ecosystem - Extension.php
 *
 * @version v0.30
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
