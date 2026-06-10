<?php

/**
 * Adlaire Ecosystem - Deployment evidence policy
 *
 * @version v0.277
 * @php     >= 8.3
 */

declare(strict_types=1);

final class DeploymentEvidence
{
    public static function artifacts(): array
    {
        return [
            'preflight',
            'plan_preview',
            'compatibility_snapshot',
            'rollback_preview',
            'safety_score',
        ];
    }
}
