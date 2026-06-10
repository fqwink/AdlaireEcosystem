<?php

/**
 * Adlaire Ecosystem - Deployment path policy
 *
 * @version v0.272
 * @php     >= 8.3
 */

declare(strict_types=1);

final class DeploymentPaths
{
    public static function compatibilityEntrypoint(): string
    {
        return 'DeploymentCore.php';
    }
}
