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
        $executionGate = self::executionGateView($controlMatrix);
        $dryRun = self::dryRunPanel($executionGate);
        $auditLedger = self::auditLedgerView($root);
        $decisionTimeline = self::decisionTimeline($controlMatrix, $executionGate, $dryRun, $auditLedger);
        $queueStatus = self::deploymentQueueStatus($root);
        $deployControls = self::dashboardDeployControls($executionGate, $dryRun, $auditLedger, $queueStatus);
        $fullAutomationGate = self::fullAutomationGate($controlMatrix, $deployControls, $queueStatus);
        $providerReadiness = self::providerReadiness();
        $providerOrchestration = self::providerOrchestrationReadiness();
        $providerRuntime = self::providerRuntimeReadiness();
        $providerRuntimeExecution = self::providerRuntimeExecutionReadiness();
        $providerRuntimeOperations = self::providerRuntimeOperationsReadiness();
        $serverApiExecution = self::serverApiExecutionReadiness();
        $serverAutomationControl = self::serverAutomationControlReadiness();
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
                    'deployment_execution_gate_ready' => $executionGate['ready'] ?? false,
                    'deployment_dry_run_ready' => $dryRun['ready'] ?? false,
                    'full_auto_deployment_ready' => $fullAutomationGate['ready'] ?? false,
                    'provider_api_deployment_ready' => $providerReadiness['ready'] ?? false,
                    'provider_orchestration_ready' => $providerOrchestration['ready'] ?? false,
                    'provider_runtime_ready' => $providerRuntime['ready'] ?? false,
                    'provider_runtime_execution_ready' => $providerRuntimeExecution['ready'] ?? false,
                    'provider_runtime_operations_ready' => $providerRuntimeOperations['ready'] ?? false,
                    'server_api_execution_ready' => $serverApiExecution['ready'] ?? false,
                    'server_automation_control_ready' => $serverAutomationControl['ready'] ?? false,
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
                    'execution_gate' => $executionGate,
                    'dry_run' => $dryRun,
                    'audit_ledger' => $auditLedger,
                    'decision_timeline' => $decisionTimeline,
                    'queue_status' => $queueStatus,
                    'deploy_controls' => $deployControls,
                    'full_automation_gate' => $fullAutomationGate,
                    'provider_readiness' => $providerReadiness,
                    'provider_orchestration' => $providerOrchestration,
                    'provider_runtime' => $providerRuntime,
                    'provider_runtime_execution' => $providerRuntimeExecution,
                    'provider_runtime_operations' => $providerRuntimeOperations,
                    'server_api_execution' => $serverApiExecution,
                    'server_automation_control' => $serverAutomationControl,
                ],
                'deployment_control_matrix' => $controlMatrix,
                'deployment_execution_gate' => $executionGate,
                'deployment_dry_run' => $dryRun,
                'deployment_audit_ledger' => $auditLedger,
                'deployment_decision_timeline' => $decisionTimeline,
                'deployment_queue_status' => $queueStatus,
                'dashboard_deploy_controls' => $deployControls,
                'full_auto_deployment_gate' => $fullAutomationGate,
                'provider_api_deployment' => $providerReadiness,
                'provider_orchestrated_deployment' => $providerOrchestration,
                'provider_runtime_foundation' => $providerRuntime,
                'provider_runtime_execution' => $providerRuntimeExecution,
                'provider_runtime_operations' => $providerRuntimeOperations,
                'server_api_execution' => $serverApiExecution,
                'server_automation_control' => $serverAutomationControl,
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
            'execution_gate_view' => [
                'ready' => ($spec['deployment_dashboard_control']['execution_gate_view'] ?? false) === true
                    && ($spec['deployment_execution_foundation']['final_plan_fingerprint_required'] ?? false) === true,
                'source' => 'Adlaire::currentSpecification()',
                'severity' => 'critical',
                'next_action' => 'review_execution_gate_view',
            ],
            'dry_run_panel' => [
                'ready' => ($spec['deployment_dashboard_control']['dry_run_panel'] ?? false) === true
                    && ($spec['deployment_execution_foundation']['dry_run_method'] ?? null) === 'Deployer::deploymentDryRun()',
                'source' => 'Adlaire::currentSpecification()',
                'severity' => 'high',
                'next_action' => 'review_dry_run_panel',
            ],
            'audit_ledger_viewer' => [
                'ready' => ($spec['deployment_dashboard_control']['audit_ledger_viewer'] ?? false) === true
                    && ($spec['deployment_execution_foundation']['append_only_json_audit_allowed'] ?? false) === true,
                'source' => 'Adlaire::currentSpecification()',
                'severity' => 'high',
                'next_action' => 'review_audit_ledger_viewer',
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

    private static function executionGateView(array $controlMatrix): array
    {
        $spec = Adlaire::currentSpecification();
        $ready = ($controlMatrix['decision']['release_allowed'] ?? false) === true
            && ($spec['deployment_execution_foundation']['final_plan_fingerprint_required'] ?? false) === true
            && ($spec['deployment_dashboard_control']['execution_gate_view'] ?? false) === true;

        return [
            'ready' => $ready,
            'status' => $ready ? 'ready' : 'blocked',
            'read_only' => true,
            'command_execution_allowed' => false,
            'writes_allowed' => false,
            'dashboard_execution_enabled' => false,
            'apply_enabled' => false,
            'source' => 'Deployer::executionGate()',
            'fingerprint_source' => 'final_deployment_plan',
            'matrix_fingerprint' => $controlMatrix['fingerprint'] ?? null,
            'required_inputs' => [
                'stable_release_candidate_gate',
                'final_deployment_plan',
                'final_plan_fingerprint',
                'safety_score',
            ],
            'blocked_reasons' => $ready ? [] : array_column($controlMatrix['decision']['blockers'] ?? [], 'reason'),
        ];
    }

    private static function dryRunPanel(array $executionGate): array
    {
        $ready = ($executionGate['ready'] ?? false) === true;

        return [
            'ready' => $ready,
            'dry_run_required' => true,
            'read_only' => true,
            'command_execution_allowed' => false,
            'writes_allowed' => false,
            'apply_allowed' => false,
            'source' => 'Deployer::deploymentDryRun()',
            'final_plan_fingerprint_required' => true,
            'execution_gate_ready' => $ready,
            'next_action' => $ready ? 'record_dry_run_evidence' : 'resolve_execution_gate_blockers',
        ];
    }

    private static function auditLedgerView(string $root): array
    {
        $path = $root . '/storage/deployment_audit_ledger.jsonl';
        $entries = [];
        if (is_file($path)) {
            $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach (array_slice(array_reverse(is_array($lines) ? $lines : []), 0, 10) as $line) {
                $entry = json_decode((string)$line, true);
                if (is_array($entry)) {
                    $entries[] = [
                        'time' => $entry['time'] ?? null,
                        'event' => $entry['event'] ?? null,
                        'fingerprint' => $entry['evidence']['final_plan_fingerprint'] ?? null,
                    ];
                }
            }
        }

        return [
            'ready' => true,
            'read_only' => true,
            'command_execution_allowed' => false,
            'writes_allowed' => false,
            'configuration_file' => false,
            'audit_artifact' => true,
            'source' => 'deployment_audit_ledger.jsonl',
            'path' => 'storage/deployment_audit_ledger.jsonl',
            'summary' => [
                'visible_entries' => count($entries),
                'append_only_jsonl' => true,
            ],
            'entries' => $entries,
        ];
    }

    private static function decisionTimeline(array $controlMatrix, array $executionGate, array $dryRun, array $auditLedger): array
    {
        $events = [
            ['name' => 'release_readiness', 'ready' => ($controlMatrix['rows']['release_readiness']['ready'] ?? false) === true],
            ['name' => 'artifact_integrity', 'ready' => ($controlMatrix['rows']['artifact_integrity']['ready'] ?? false) === true],
            ['name' => 'final_deployment_plan', 'ready' => ($controlMatrix['rows']['final_deployment_plan']['ready'] ?? false) === true],
            ['name' => 'execution_gate', 'ready' => ($executionGate['ready'] ?? false) === true],
            ['name' => 'dry_run', 'ready' => ($dryRun['ready'] ?? false) === true],
            ['name' => 'audit_ledger', 'ready' => ($auditLedger['ready'] ?? false) === true],
        ];

        return [
            'ready' => !in_array(false, array_column($events, 'ready'), true),
            'read_only' => true,
            'command_execution_allowed' => false,
            'writes_allowed' => false,
            'events' => $events,
        ];
    }

    private static function deploymentQueueStatus(string $root): array
    {
        $lock = $root . '/storage/deploy.lock';
        $running = is_file($lock);

        return [
            'ready' => !$running,
            'status' => $running ? 'running' : 'idle',
            'read_only' => true,
            'command_execution_allowed' => false,
            'writes_allowed' => false,
            'lock_file' => 'storage/deploy.lock',
            'allowed_statuses' => ['idle', 'running', 'completed', 'failed', 'rolled_back'],
        ];
    }

    private static function dashboardDeployControls(array $executionGate, array $dryRun, array $auditLedger, array $queueStatus): array
    {
        $ready = ($executionGate['ready'] ?? false) === true
            && ($dryRun['ready'] ?? false) === true
            && ($auditLedger['ready'] ?? false) === true
            && ($queueStatus['ready'] ?? false) === true;

        return [
            'ready' => $ready,
            'dashboard_execution_enabled' => $ready,
            'safety_gated' => true,
            'public_api_required' => false,
            'configuration_file' => false,
            'csrf_required' => true,
            'short_lived_execution_token_required' => true,
            'explicit_confirmation_required' => true,
            'final_plan_fingerprint_required' => true,
            'source' => 'AdlaireDashboardSecurity',
        ];
    }

    private static function fullAutomationGate(array $controlMatrix, array $deployControls, array $queueStatus): array
    {
        $spec = Adlaire::currentSpecification();
        $checks = [
            'roadmap_targets_v0_290' => ($spec['auto_deployment_roadmap']['target'] ?? null) === 'v0.290',
            'control_matrix_ready' => ($controlMatrix['status'] ?? null) === 'ready',
            'dashboard_controls_ready' => ($deployControls['ready'] ?? false) === true,
            'queue_idle' => ($queueStatus['status'] ?? null) === 'idle',
            'public_api_absent' => ($deployControls['public_api_required'] ?? true) === false,
            'configuration_file_absent' => ($deployControls['configuration_file'] ?? true) === false,
        ];

        return [
            'ready' => !in_array(false, $checks, true),
            'target' => 'v0.290',
            'full_auto_deployment_enabled' => true,
            'release_gate_required' => true,
            'checks' => $checks,
        ];
    }

    private static function providerReadiness(): array
    {
        $spec = Adlaire::currentSpecification();
        $policy = is_array($spec['provider_api_deployment'] ?? null) ? $spec['provider_api_deployment'] : [];
        $profiles = is_array($policy['supported_initial_profiles'] ?? null) ? $policy['supported_initial_profiles'] : [];
        $checks = [
            'target_v0_295' => ($policy['target'] ?? null) === 'v0.295',
            'xserver_rental_profile' => in_array('xserver_rental', $profiles, true),
            'xserver_vps_profile' => in_array('xserver_vps', $profiles, true),
            'public_api_absent' => ($policy['public_api_required'] ?? true) === false,
            'configuration_file_absent' => ($policy['configuration_file'] ?? true) === false,
            'internal_only' => ($policy['provider_api_internal_only'] ?? false) === true,
        ];

        return [
            'ready' => !in_array(false, $checks, true),
            'target' => $policy['target'] ?? null,
            'read_only' => true,
            'command_execution_allowed' => false,
            'writes_allowed' => false,
            'checks' => $checks,
            'profiles' => $profiles,
            'methods' => $policy['methods'] ?? [],
        ];
    }

    private static function providerOrchestrationReadiness(): array
    {
        $spec = Adlaire::currentSpecification();
        $policy = is_array($spec['provider_orchestrated_deployment'] ?? null) ? $spec['provider_orchestrated_deployment'] : [];
        $methods = is_array($policy['methods'] ?? null) ? $policy['methods'] : [];
        $checks = [
            'target_v0_305' => ($policy['target'] ?? null) === 'v0.305',
            'orchestrator_method' => ($methods['orchestrator'] ?? null) === 'Deployer::providerOrchestrator()',
            'remote_operation_plan_method' => ($methods['remote_operation_plan'] ?? null) === 'Deployer::remoteOperationPlan()',
            'transport_evidence_method' => ($methods['transport_evidence'] ?? null) === 'Deployer::providerApiTransportEvidence()',
            'release_gate_method' => ($methods['release_gate'] ?? null) === 'Deployer::providerOrchestratedReleaseGate()',
            'public_api_absent' => ($policy['public_api_required'] ?? true) === false,
            'configuration_file_absent' => ($policy['configuration_file'] ?? true) === false,
        ];

        return [
            'ready' => !in_array(false, $checks, true),
            'target' => $policy['target'] ?? null,
            'read_only' => true,
            'command_execution_allowed' => false,
            'writes_allowed' => false,
            'checks' => $checks,
            'methods' => $methods,
        ];
    }

    private static function providerRuntimeReadiness(): array
    {
        $spec = Adlaire::currentSpecification();
        $policy = is_array($spec['provider_runtime_foundation'] ?? null) ? $spec['provider_runtime_foundation'] : [];
        $methods = is_array($policy['methods'] ?? null) ? $policy['methods'] : [];
        $checks = [
            'target_v0_311' => ($policy['target'] ?? null) === 'v0.311',
            'runtime_interface' => ($methods['runtime_interface'] ?? null) === 'Deployer::providerRuntimeInterface()',
            'remote_state_snapshot' => ($methods['remote_state_snapshot'] ?? null) === 'Deployer::remoteStateSnapshot()',
            'transaction_plan' => ($methods['transaction_plan'] ?? null) === 'Deployer::providerTransactionPlan()',
            'secret_redaction_engine' => ($methods['secret_redaction_engine'] ?? null) === 'Deployer::providerSecretRedactionEngine()',
            'public_api_absent' => ($policy['public_api_required'] ?? true) === false,
            'configuration_file_absent' => ($policy['configuration_file'] ?? true) === false,
        ];

        return [
            'ready' => !in_array(false, $checks, true),
            'target' => $policy['target'] ?? null,
            'read_only' => true,
            'command_execution_allowed' => false,
            'writes_allowed' => false,
            'checks' => $checks,
            'methods' => $methods,
        ];
    }

    private static function providerRuntimeExecutionReadiness(): array
    {
        $spec = Adlaire::currentSpecification();
        $policy = is_array($spec['provider_runtime_execution'] ?? null) ? $spec['provider_runtime_execution'] : [];
        $methods = is_array($policy['methods'] ?? null) ? $policy['methods'] : [];
        $checks = [
            'target_v0_320' => ($policy['target'] ?? null) === 'v0.320',
            'xserver_rental_adapter' => ($methods['xserver_rental_adapter'] ?? null) === 'Deployer::xserverRentalRuntimeAdapter()',
            'xserver_vps_adapter' => ($methods['xserver_vps_adapter'] ?? null) === 'Deployer::xserverVpsRuntimeAdapter()',
            'execution_plan' => ($methods['execution_plan'] ?? null) === 'Deployer::providerRuntimeExecutionPlan()',
            'execution_gate' => ($methods['execution_gate'] ?? null) === 'Deployer::providerRuntimeExecutionGate()',
            'public_api_absent' => ($policy['public_api_required'] ?? true) === false,
            'configuration_file_absent' => ($policy['configuration_file'] ?? true) === false,
        ];

        return [
            'ready' => !in_array(false, $checks, true),
            'target' => $policy['target'] ?? null,
            'read_only' => true,
            'command_execution_allowed' => false,
            'writes_allowed' => false,
            'checks' => $checks,
            'methods' => $methods,
        ];
    }

    private static function providerRuntimeOperationsReadiness(): array
    {
        $spec = Adlaire::currentSpecification();
        $policy = is_array($spec['provider_runtime_operations'] ?? null) ? $spec['provider_runtime_operations'] : [];
        $methods = is_array($policy['methods'] ?? null) ? $policy['methods'] : [];
        $checks = [
            'target_v0_330' => ($policy['target'] ?? null) === 'v0.330',
            'operation_journal' => ($methods['operation_journal'] ?? null) === 'Deployer::providerRuntimeOperationJournal()',
            'credential_envelope' => ($methods['credential_envelope'] ?? null) === 'Deployer::providerRuntimeCredentialEnvelope()',
            'preflight' => ($methods['preflight'] ?? null) === 'Deployer::providerRuntimePreflight()',
            'apply_plan' => ($methods['apply_plan'] ?? null) === 'Deployer::providerRuntimeApplyPlan()',
            'rollback_drill' => ($methods['rollback_drill'] ?? null) === 'Deployer::providerRuntimeRollbackDrill()',
            'health_sla' => ($methods['health_sla'] ?? null) === 'Deployer::providerRuntimeHealthSla()',
            'audit_bundle' => ($methods['audit_bundle'] ?? null) === 'Deployer::providerRuntimeAuditBundle()',
            'operations_gate' => ($methods['operations_gate'] ?? null) === 'Deployer::providerRuntimeOperationsGate()',
            'public_api_absent' => ($policy['public_api_required'] ?? true) === false,
            'configuration_file_absent' => ($policy['configuration_file'] ?? true) === false,
            'credentials_not_persisted' => ($policy['credentials_persisted'] ?? true) === false,
        ];

        return [
            'ready' => !in_array(false, $checks, true),
            'target' => $policy['target'] ?? null,
            'read_only' => true,
            'command_execution_allowed' => false,
            'writes_allowed' => false,
            'checks' => $checks,
            'methods' => $methods,
        ];
    }

    private static function serverApiExecutionReadiness(): array
    {
        $spec = Adlaire::currentSpecification();
        $policy = is_array($spec['server_api_execution'] ?? null) ? $spec['server_api_execution'] : [];
        $methods = is_array($policy['methods'] ?? null) ? $policy['methods'] : [];
        $checks = [
            'target_v0_340' => ($policy['target'] ?? null) === 'v0.340',
            'driver_contract' => ($methods['driver_contract'] ?? null) === 'Deployer::serverApiDriverContract()',
            'capability_probe' => ($methods['capability_probe'] ?? null) === 'Deployer::serverApiCapabilityProbe()',
            'auth_session' => ($methods['auth_session'] ?? null) === 'Deployer::serverApiAuthSession()',
            'command_sandbox' => ($methods['command_sandbox'] ?? null) === 'Deployer::remoteCommandSandbox()',
            'transaction_engine' => ($methods['transaction_engine'] ?? null) === 'Deployer::serverApiTransactionEngine()',
            'drift_detection' => ($methods['drift_detection'] ?? null) === 'Deployer::providerDriftDetection()',
            'governance' => ($methods['governance'] ?? null) === 'Deployer::serverApiGovernance()',
            'failover_plan' => ($methods['failover_plan'] ?? null) === 'Deployer::multiProviderFailoverPlan()',
            'dashboard_console' => ($methods['dashboard_console'] ?? null) === 'Deployer::dashboardServerApiConsole()',
            'execution_gate' => ($methods['execution_gate'] ?? null) === 'Deployer::serverApiExecutionGate()',
            'public_api_absent' => ($policy['public_api_required'] ?? true) === false,
            'configuration_file_absent' => ($policy['configuration_file'] ?? true) === false,
            'credentials_not_persisted' => ($policy['credentials_persisted'] ?? true) === false,
            'mysql_absent' => ($policy['mysql_supported'] ?? true) === false,
        ];

        return [
            'ready' => !in_array(false, $checks, true),
            'target' => $policy['target'] ?? null,
            'read_only' => true,
            'command_execution_allowed' => false,
            'writes_allowed' => false,
            'checks' => $checks,
            'methods' => $methods,
        ];
    }

    private static function serverAutomationControlReadiness(): array
    {
        $spec = Adlaire::currentSpecification();
        $policy = is_array($spec['server_automation_control'] ?? null) ? $spec['server_automation_control'] : [];
        $methods = is_array($policy['methods'] ?? null) ? $policy['methods'] : [];
        $checks = [
            'target_v0_350' => ($policy['target'] ?? null) === 'v0.350',
            'operation_catalog' => ($methods['operation_catalog'] ?? null) === 'Deployer::serverApiOperationCatalog()',
            'execution_policy' => ($methods['execution_policy'] ?? null) === 'Deployer::providerExecutionPolicy()',
            'file_sync_plan' => ($methods['file_sync_plan'] ?? null) === 'Deployer::remoteFileSyncPlan()',
            'state_reconciliation' => ($methods['state_reconciliation'] ?? null) === 'Deployer::serverStateReconciliation()',
            'restart_orchestrator' => ($methods['restart_orchestrator'] ?? null) === 'Deployer::safeRestartOrchestrator()',
            'snapshot_backup' => ($methods['snapshot_backup'] ?? null) === 'Deployer::snapshotBackupControl()',
            'audit_trail' => ($methods['audit_trail'] ?? null) === 'Deployer::serverApiAuditTrail()',
            'recovery_engine' => ($methods['recovery_engine'] ?? null) === 'Deployer::deploymentRecoveryEngine()',
            'dashboard_console' => ($methods['dashboard_console'] ?? null) === 'Deployer::dashboardAutomationConsole()',
            'release_gate' => ($methods['release_gate'] ?? null) === 'Deployer::serverAutomationReleaseGate()',
            'safe_execution' => ($methods['safe_execution'] ?? null) === 'Deployer::executeServerAutomation()',
            'safety_gated_execution' => ($policy['command_execution_allowed'] ?? null) === 'safety_gated',
            'arbitrary_command_absent' => ($policy['arbitrary_command_allowed'] ?? true) === false,
            'public_api_absent' => ($policy['public_api_required'] ?? true) === false,
            'configuration_file_absent' => ($policy['configuration_file'] ?? true) === false,
            'credentials_not_persisted' => ($policy['credentials_persisted'] ?? true) === false,
            'mysql_absent' => ($policy['mysql_supported'] ?? true) === false,
        ];

        return [
            'ready' => !in_array(false, $checks, true),
            'target' => $policy['target'] ?? null,
            'read_only' => false,
            'command_execution_allowed' => $policy['command_execution_allowed'] ?? false,
            'writes_allowed' => 'audit_only',
            'checks' => $checks,
            'methods' => $methods,
        ];
    }
}
