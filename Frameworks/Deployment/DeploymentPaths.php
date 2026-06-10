<?php

/**
 * Adlaire Ecosystem - Deployment path policy
 *
 * @version v0.277
 * @php     >= 8.3
 */

declare(strict_types=1);

final class DeploymentPaths
{
    public static function compatibilityEntrypoint(): string
    {
        return 'Frameworks/Deployment/DeploymentCore.php';
    }
}
