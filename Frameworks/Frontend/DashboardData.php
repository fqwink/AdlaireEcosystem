<?php

declare(strict_types=1);

final class AdlaireDashboardData
{
    public static function collect(string $root): array
    {
        $health = Adlaire::health([
            'writable_paths' => [
                'storage' => $root . '/storage',
            ],
        ]);
        $configAudit = Adlaire::configAudit([
            'writable_paths' => [
                'storage' => $root . '/storage',
            ],
        ]);
        $releaseReadiness = Adlaire::releaseReadiness();
        $distribution = Adlaire::distributionManifest();
        $database = ['configured' => false];

        try {
            $database = [
                'configured' => true,
                'runtime_profile' => Database::default()->runtimeProfile(),
            ];
        } catch (Throwable) {
        }

        $failed = ($health['status'] ?? 'failed') !== 'ok'
            || ($configAudit['valid'] ?? false) !== true
            || ($releaseReadiness['ready'] ?? false) !== true;

        return [
            'status' => $failed ? 'failed' : 'ok',
            'version' => Adlaire::version(),
            'sections' => [
                'overview' => [
                    'framework_version' => Adlaire::version(),
                    'runtime_status' => $health['status'] ?? 'unknown',
                    'release_ready' => $releaseReadiness['ready'] ?? false,
                    'environment' => Adlaire::env('APP_ENV', 'production'),
                    'php_version' => PHP_VERSION,
                ],
                'health' => $health,
                'config_audit' => $configAudit,
                'release_readiness' => [
                    'ready' => $releaseReadiness['ready'] ?? false,
                    'checks' => $releaseReadiness['checks'] ?? [],
                ],
                'deployment_control' => [
                    'control_visibility' => Adlaire::dashboardControlVisibilityPolicy(),
                    'control_report' => Adlaire::deploymentControlReportPolicy(),
                    'stable_release_gate' => Adlaire::stableReleaseGatePolicy(),
                ],
                'safety_score' => Adlaire::deploymentSafetyScorePolicy(),
                'deploy_history' => Adlaire::deploymentHistoryVisualizationPolicy(),
                'distribution' => [
                    'version' => $distribution['version'] ?? Adlaire::version(),
                    'files' => $distribution['files'] ?? [],
                    'required_verifications' => Adlaire::audit()['required_verifications'] ?? [],
                ],
                'database' => $database,
                'security' => [
                    'dashboard_enabled' => Adlaire::dashboardEnabled(),
                    'auth_required' => Adlaire::dashboardPolicy()['auth_required'],
                    'auth_configured' => Adlaire::dashboardTokenConfigured(),
                    'app_debug' => Adlaire::env('APP_DEBUG', false),
                    'secret_values_exposed' => false,
                ],
            ],
        ];
    }
}
