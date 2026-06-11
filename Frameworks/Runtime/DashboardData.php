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
        $controlMatrix = self::controlMatrix($releaseReadiness);
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
            || ($releaseReadiness['ready'] ?? false) !== true
            || ($controlMatrix['status'] ?? 'blocked') !== 'ready';

        return [
            'status' => $failed ? 'failed' : 'ok',
            'version' => Adlaire::version(),
            'sections' => [
                'overview' => [
                    'framework_version' => Adlaire::version(),
                    'runtime_status' => $health['status'] ?? 'unknown',
                    'release_ready' => $releaseReadiness['ready'] ?? false,
                    'deployment_control_ready' => ($controlMatrix['status'] ?? 'blocked') === 'ready',
                    'deployment_control_ready_count' => $controlMatrix['summary']['ready'] ?? 0,
                    'deployment_control_blocked_count' => $controlMatrix['summary']['blocked'] ?? 0,
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
                    'control_matrix' => $controlMatrix,
                    'control_report' => Adlaire::deploymentControlReportPolicy(),
                    'stable_release_gate' => Adlaire::stableReleaseGatePolicy(),
                ],
                'deployment_control_matrix' => $controlMatrix,
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

    private static function controlMatrix(array $releaseReadiness): array
    {
        $spec = Adlaire::currentSpecification();
        $stablePolicy = Adlaire::v0284StableReleasePolicy();

        $rows = [
            'release_readiness' => [
                'ready' => $releaseReadiness['ready'] ?? false,
                'source' => 'Adlaire::releaseReadiness()',
                'severity' => 'critical',
                'next_action' => 'run_release_check',
            ],
            'stable_release_gate' => [
                'ready' => ($stablePolicy['stable_release'] ?? false) === true
                    && ($stablePolicy['known_bug_count'] ?? 1) === 0,
                'source' => 'Adlaire::v0284StableReleasePolicy()',
                'severity' => 'critical',
                'next_action' => 'resolve_stable_policy_blockers',
            ],
            'release_artifact_manifest' => [
                'ready' => ($spec['deployment_release_artifact_manifest']['enabled'] ?? false) === true,
                'single_pass_evidence_builder' => $spec['deployment_release_artifact_manifest']['single_pass_evidence_builder'] ?? false,
                'severity' => 'high',
                'next_action' => 'repair_release_artifact_manifest',
            ],
            'artifact_acquisition' => [
                'ready' => ($spec['deployment_artifact_acquisition']['source_verified_before_extract_required'] ?? false) === true,
                'default_method' => $spec['deployment_artifact_acquisition']['default_method'] ?? null,
                'severity' => 'high',
                'next_action' => 'verify_artifact_acquisition_plan',
            ],
            'artifact_pre_extract_preview' => [
                'ready' => ($spec['deployment_artifact_pre_extract_preview']['enabled'] ?? false) === true,
                'read_only' => $spec['deployment_artifact_pre_extract_preview']['read_only'] ?? false,
                'severity' => 'high',
                'next_action' => 'review_pre_extract_preview',
            ],
            'artifact_integrity' => [
                'ready' => ($spec['deployment_artifact_integrity']['enabled'] ?? false) === true,
                'sha256_required' => $spec['deployment_artifact_integrity']['sha256_required'] ?? false,
                'severity' => 'critical',
                'next_action' => 'verify_artifact_sha256',
            ],
            'final_deployment_plan' => [
                'ready' => ($spec['deployment_final_plan']['enabled'] ?? false) === true,
                'content_hash_required' => $spec['deployment_final_plan']['content_hash_required'] ?? false,
                'severity' => 'critical',
                'next_action' => 'freeze_final_deployment_plan',
            ],
            'release_check_evidence' => [
                'ready' => ($spec['release_check_evidence']['summary_required'] ?? false) === true
                    && ($spec['release_check_evidence']['named_passes_required'] ?? false) === true,
                'summary_required' => $spec['release_check_evidence']['summary_required'] ?? false,
                'severity' => 'medium',
                'next_action' => 'rerun_release_check_with_summary',
            ],
        ];
        $readyCount = 0;
        $blockers = [];
        foreach ($rows as $row) {
            if (($row['ready'] ?? false) === true) {
                $readyCount++;
            }
        }
        foreach ($rows as $name => $row) {
            if (($row['ready'] ?? false) !== true) {
                $blockers[] = [
                    'control' => $name,
                    'reason' => 'control_not_ready',
                    'severity' => $row['severity'] ?? 'high',
                    'next_action' => $row['next_action'] ?? 'inspect_control',
                ];
            }
        }
        $total = count($rows);
        $releaseAllowed = $readyCount === $total;
        $summary = [
            'total' => $total,
            'ready' => $readyCount,
            'blocked' => $total - $readyCount,
        ];
        $decision = [
            'release_allowed' => $releaseAllowed,
            'reason' => $releaseAllowed ? 'all_controls_ready' : 'blocked_controls_present',
            'blockers' => $blockers,
        ];
        $fingerprint = hash('sha256', json_encode([
            'version' => Adlaire::version(),
            'status' => $releaseAllowed ? 'ready' : 'blocked',
            'summary' => $summary,
            'decision' => $decision,
            'rows' => $rows,
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));

        return [
            'policy' => Adlaire::dashboardDeploymentControlMatrixPolicy(),
            'status' => $releaseAllowed ? 'ready' : 'blocked',
            'fingerprint' => $fingerprint,
            'execution_enabled' => false,
            'read_only' => true,
            'command_execution_allowed' => false,
            'writes_allowed' => false,
            'decision' => $decision,
            'summary' => $summary,
            'rows' => $rows,
        ];
    }
}
