<?php

/**
 * Adlaire Ecosystem - Deployment Core bootstrap and support
 *
 * @version v0.278
 * @php     >= 8.3
 */

declare(strict_types=1);

if (PHP_VERSION_ID < 80300) {
    echo json_encode(['error' => 'Adlaire Ecosystem requires PHP 8.3 or higher. Current version: ' . PHP_VERSION]);
    exit(1);
}

require_once __DIR__ . '/DeployConfig.php';
require_once __DIR__ . '/Deployer.php';

final class DeploymentPaths
{
    public static function compatibilityEntrypoint(): string
    {
        return 'Core/Deployment.php';
    }
}

final class DeploymentEvidence
{
    public static function artifacts(): array
    {
        return [
            'preflight',
            'plan_preview',
            'control_snapshot',
            'rollback_preview',
            'safety_score',
        ];
    }
}
