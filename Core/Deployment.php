<?php

/**
 * Adlaire Ecosystem - Deployment Core bootstrap and support
 *
 * @version v0.284
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
            'release_artifact_manifest',
            'execution_gate',
            'deployment_dry_run',
            'deployment_audit_ledger',
            'auto_deployment',
            'provider_capability_matrix',
            'provider_execution_plan',
            'provider_audit_evidence',
            'provider_orchestrator',
            'remote_operation_plan',
            'provider_transport_evidence',
            'provider_orchestrated_release_gate',
            'provider_runtime_interface',
            'remote_state_snapshot',
            'provider_transaction_plan',
            'provider_secret_redaction',
            'xserver_runtime_adapter',
            'provider_runtime_execution_plan',
            'remote_artifact_lifecycle',
            'provider_runtime_execution_gate',
        ];
    }
}
