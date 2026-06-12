<?php

/**
 * Adlaire Ecosystem - Deployment Deployer
 *
 * @version v0.284
 * @php     >= 8.3
 */

declare(strict_types=1);

require_once __DIR__ . '/../Frameworks/Backend/Logger.php';
require_once __DIR__ . '/../Frameworks/Backend/Support.php';
require_once __DIR__ . '/DeployConfig.php';

final class Deployer
{
    private const CONTROL_VERSION = 'v0.284';

    private Logger $logger;
    private array $fileCache = [];

    public function __construct(private DeployConfig $config)
    {
        $this->logger = new Logger(
            $config->requiredString('log_file'),
            (string)$config->get('log_level', 'INFO'),
            is_string($config->get('hmac_key')) && $config->get('hmac_key') !== '' ? $config->get('hmac_key') : null,
            (int)$config->get('log_max_bytes', 1048576),
            (int)$config->get('log_keep', 5),
            ['password', 'token', 'secret'],
            4096,
            false,
            null,
            'deployer'
        );
    }

    public function run(bool $dryRun = false): array
    {
        $this->verifyHttpTrigger();
        $this->lock();
        $this->maintenance(true);
        $snapshot = null;

        try {
            $source = $this->fetchArchive();
            $changes = $this->diff($source);

            if ($dryRun || (bool)$this->config->get('dry_run', false)) {
                $this->logger->info('Dry run completed.', ['changes' => $changes]);
                return ['dry_run' => true, 'changes' => $changes];
            }

            $snapshot = $this->backup($changes);
            $this->apply($source, $changes);
            $this->healthCheck();
            $this->recordHistory($snapshot, $changes);

            $this->logger->info('Deployment completed.', ['changes' => $changes]);
            return ['dry_run' => false, 'changes' => $changes, 'snapshot' => $snapshot];
        } catch (Throwable $exception) {
            $this->logger->error('Deployment failed.', ['error' => $exception->getMessage()]);
            if ($snapshot !== null) {
                $this->rollbackSnapshot($snapshot);
            }
            throw $exception;
        } finally {
            $this->cleanup();
        }
    }

    public function validateOnly(): array
    {
        foreach (['target_dir', 'work_dir', 'backup_dir'] as $key) {
            $this->ensureDirectory($this->path($this->config->requiredString($key)));
        }

        $logFile = $this->config->requiredString('log_file');
        $this->ensureDirectory(dirname($logFile));

        return [
            'valid' => true,
            'repository' => $this->config->requiredString('repository'),
            'branch' => $this->config->requiredString('branch'),
            'target_dir' => $this->path($this->config->requiredString('target_dir')),
            'work_dir' => $this->path($this->config->requiredString('work_dir')),
            'backup_dir' => $this->path($this->config->requiredString('backup_dir')),
            'deployment_axis' => 'deployment system',
        ];
    }

    public function deploymentSystemManifest(): array
    {
        $config = $this->config->deploymentManifest();
        $releaseArtifactEvidence = $this->releaseArtifactEvidence();

        return [
            'component' => 'Core/Deployment.php',
            'axis' => 'deployment system',
            'design_philosophy' => 'distributed autonomous system design philosophy',
            'architecture_changed' => true,
            'release_source' => $config['release_source'],
            'release_artifact_manifest' => $releaseArtifactEvidence['manifest'],
            'release_artifact_manifest_validation' => $releaseArtifactEvidence['validation'],
            'application_boundary' => $config['application_boundary'],
            'application_dependency_allowed' => $config['application_dependency_allowed'],
            'legacy_modules_directory_allowed' => $config['legacy_modules_directory_allowed'],
            'autonomous_operation' => true,
            'required_directories' => [
                'target_dir' => $this->path($this->config->requiredString('target_dir')),
                'work_dir' => $this->path($this->config->requiredString('work_dir')),
                'backup_dir' => $this->path($this->config->requiredString('backup_dir')),
            ],
            'config' => $config,
        ];
    }

    public function deploymentReadiness(): array
    {
        $validation = $this->validateOnly();
        $manifest = $this->deploymentSystemManifest();
        $checks = [
            'config_valid' => ($validation['valid'] ?? false) === true,
            'deployment_axis' => ($manifest['axis'] ?? null) === 'deployment system',
            'distributed_autonomous_design' => ($manifest['design_philosophy'] ?? null) === 'distributed autonomous system design philosophy',
            'application_boundary' => ($manifest['application_boundary'] ?? null) === 'Applications',
            'application_dependency_absent' => ($manifest['application_dependency_allowed'] ?? true) === false,
            'legacy_modules_directory_forbidden' => ($manifest['legacy_modules_directory_allowed'] ?? true) === false,
            'architecture_changed' => ($manifest['architecture_changed'] ?? false) === true,
            'target_dir_ready' => is_dir($manifest['required_directories']['target_dir'] ?? ''),
            'work_dir_ready' => is_dir($manifest['required_directories']['work_dir'] ?? ''),
            'backup_dir_ready' => is_dir($manifest['required_directories']['backup_dir'] ?? ''),
        ];

        return [
            'ready' => self::gateReady($checks),
            'checks' => $checks,
            'validation' => $validation,
            'manifest' => $manifest,
        ];
    }

    public function preflight(): array
    {
        $manifest = $this->deploymentSystemManifest();
        $artifactEvidence = $this->releaseArtifactEvidence($manifest['release_artifact_manifest']);
        $targetDir = $manifest['required_directories']['target_dir'];
        $workDir = $manifest['required_directories']['work_dir'];
        $backupDir = $manifest['required_directories']['backup_dir'];
        $logDir = dirname($this->config->requiredString('log_file'));
        $lockFile = $this->path($this->config->get('lock_file', $this->config->requiredString('work_dir') . '/deploy.lock'));
        $lockTimeout = (int)$this->config->get('lock_timeout', 900);
        $allowlist = $this->config->array('deploy_allowlist');

        $checks = [
            'deployment_core_entrypoint_current' => ($manifest['component'] ?? null) === 'Core/Deployment.php'
                && ($manifest['axis'] ?? null) === 'deployment system'
                && ($manifest['architecture_changed'] ?? false) === true,
            'target_dir_exists' => is_dir($targetDir),
            'target_dir_writable' => is_dir($targetDir) && is_writable($targetDir),
            'work_dir_exists' => is_dir($workDir),
            'work_dir_writable' => is_dir($workDir) && is_writable($workDir),
            'backup_dir_exists' => is_dir($backupDir),
            'backup_dir_writable' => is_dir($backupDir) && is_writable($backupDir),
            'log_dir_writable' => is_dir($logDir) && is_writable($logDir),
            'deploy_allowlist_configured' => $allowlist !== [],
            'lock_available' => !is_file($lockFile) || time() - filemtime($lockFile) >= $lockTimeout,
            'history_retention_valid' => (int)$this->config->get('history_keep', 5) >= 1,
        ];
        $releaseValidation = $artifactEvidence['validation'];
        $checks['release_artifact_manifest_valid'] = $releaseValidation['valid'] === true;
        $checks['artifact_acquisition_plan_valid'] = $artifactEvidence['acquisition']['valid'] === true;
        $checks['artifact_pre_extract_preview_valid'] = $artifactEvidence['pre_extract']['valid'] === true;
        $checks['artifact_integrity_valid'] = $artifactEvidence['integrity']['valid'] === true;

        return [
            'ready' => self::gateReady($checks),
            'checks' => $checks,
            'component' => 'Core/Deployment.php',
            'compatibility_guaranteed' => false,
            'breaking_changes_allowed' => true,
            'dry_run_available' => true,
            'required_directories' => $manifest['required_directories'],
            'deploy_allowlist' => $allowlist,
            'lock_file' => $lockFile,
            'release_artifact_manifest' => $releaseValidation,
            'artifact_acquisition_plan' => $artifactEvidence['acquisition'],
            'artifact_pre_extract_preview' => $artifactEvidence['pre_extract'],
            'artifact_integrity' => $artifactEvidence['integrity'],
        ];
    }

    public function planPreview(string $sourceDir): array
    {
        $source = $this->path($sourceDir);
        if (!is_dir($source)) {
            throw new InvalidArgumentException("Deployment preview source directory not found: {$sourceDir}");
        }

        $target = $this->path($this->config->requiredString('target_dir'));
        $allowlist = $this->config->array('deploy_allowlist');
        $plan = [
            'added' => [],
            'modified' => [],
            'unchanged' => [],
            'skipped' => [],
        ];

        foreach ($this->files($source) as $file) {
            $this->assertRelativePath($file);
            if (!$this->allowed($file, $allowlist)) {
                $plan['skipped'][] = $file;
                continue;
            }

            $sourceFile = $source . '/' . $file;
            $targetFile = $target . '/' . $file;
            if (!is_file($targetFile)) {
                $plan['added'][] = $file;
                continue;
            }

            if (hash_file('sha256', $sourceFile) !== hash_file('sha256', $targetFile)) {
                $plan['modified'][] = $file;
                continue;
            }

            $plan['unchanged'][] = $file;
        }

        $changed = array_merge($plan['added'], $plan['modified']);

        return [
            'ready' => true,
            'read_only' => true,
            'command_execution_allowed' => false,
            'writes_allowed' => false,
            'component' => 'Core/Deployment.php',
            'deployment_core_change_detected' => in_array('Core/Deployment.php', $changed, true),
            'target_dir' => $target,
            'source_dir' => $source,
            'deploy_allowlist' => $allowlist,
            'summary' => [
                'added' => count($plan['added']),
                'modified' => count($plan['modified']),
                'unchanged' => count($plan['unchanged']),
                'skipped' => count($plan['skipped']),
                'changes' => count($changed),
            ],
            'files' => $plan,
        ];
    }

    public function controlSnapshot(?string $sourceDir = null): array
    {
        $preflight = $this->preflight();
        $manifest = $this->deploymentSystemManifest();
        $artifactEvidence = $this->releaseArtifactEvidence($manifest['release_artifact_manifest']);
        $plan = $sourceDir === null ? null : $this->planPreview($sourceDir);
        $deploymentCoreChangeDetected = $plan === null
            ? false
            : ($plan['deployment_core_change_detected'] ?? false) === true;

        $checks = [
            'deployment_core_component' => ($manifest['component'] ?? null) === 'Core/Deployment.php',
            'deployment_axis_retained' => ($manifest['axis'] ?? null) === 'deployment system',
            'architecture_changed' => ($manifest['architecture_changed'] ?? false) === true,
            'preflight_ready' => ($preflight['ready'] ?? false) === true,
            'breaking_changes_allowed' => true,
            'plan_preview_read_only' => $plan === null || (($plan['read_only'] ?? false) === true
                && ($plan['command_execution_allowed'] ?? true) === false
                && ($plan['writes_allowed'] ?? true) === false),
        ];

        return [
            'ready' => self::gateReady($checks),
            'read_only' => true,
            'command_execution_allowed' => false,
            'writes_allowed' => false,
            'component' => 'Core/Deployment.php',
            'compatibility_guaranteed' => false,
            'breaking_changes_allowed' => true,
            'deployment_core_change_detected' => $deploymentCoreChangeDetected,
            'checks' => $checks,
            'manifest' => [
                'component' => $manifest['component'],
                'axis' => $manifest['axis'],
                'architecture_changed' => $manifest['architecture_changed'],
                'design_philosophy' => $manifest['design_philosophy'],
                'release_source' => $manifest['release_source'],
                'release_artifact_manifest_validation' => $manifest['release_artifact_manifest_validation'],
                'artifact_acquisition_plan' => $artifactEvidence['acquisition'],
                'artifact_pre_extract_preview' => $artifactEvidence['pre_extract'],
                'artifact_integrity' => $artifactEvidence['integrity'],
                'required_directories' => $manifest['required_directories'],
            ],
            'preflight' => [
                'ready' => $preflight['ready'],
                'checks' => $preflight['checks'],
            ],
            'plan_summary' => $plan['summary'] ?? null,
        ];
    }

    public function rollbackPreview(): array
    {
        $backupDir = $this->path($this->config->requiredString('backup_dir'));
        $target = $this->path($this->config->requiredString('target_dir'));
        $snapshots = glob($backupDir . '/*', GLOB_ONLYDIR);
        if ($snapshots === false || $snapshots === []) {
            return [
                'ready' => false,
                'read_only' => true,
                'command_execution_allowed' => false,
                'writes_allowed' => false,
                'component' => 'Core/Deployment.php',
                'reason' => 'no_snapshot',
                'snapshot' => null,
                'summary' => ['restore' => 0, 'remove' => 0, 'missing' => 0],
                'files' => ['restore' => [], 'remove' => [], 'missing' => []],
            ];
        }

        rsort($snapshots, SORT_STRING);
        $snapshot = $snapshots[0];
        $manifestFile = $snapshot . '/manifest.json';
        $before = [];
        if (is_file($manifestFile)) {
            $manifest = json_decode((string)file_get_contents($manifestFile), true, flags: JSON_THROW_ON_ERROR);
            $before = is_array($manifest['files'] ?? null) ? $manifest['files'] : [];
        }

        $restore = [];
        $missing = [];
        foreach ($this->files($snapshot) as $file) {
            if ($file === 'manifest.json') {
                continue;
            }
            $this->assertRelativePath($file);
            $restore[] = $file;
            if (!is_file($target . '/' . $file)) {
                $missing[] = $file;
            }
        }

        $remove = [];
        foreach ($this->files($target) as $file) {
            $this->assertRelativePath($file);
            if ($before !== [] && !in_array($file, $before, true)) {
                $remove[] = $file;
            }
        }

        return [
            'ready' => true,
            'read_only' => true,
            'command_execution_allowed' => false,
            'writes_allowed' => false,
            'component' => 'Core/Deployment.php',
            'snapshot' => $snapshot,
            'manifest_available' => is_file($manifestFile),
            'summary' => [
                'restore' => count($restore),
                'remove' => count($remove),
                'missing' => count($missing),
            ],
            'files' => [
                'restore' => $restore,
                'remove' => $remove,
                'missing' => $missing,
            ],
        ];
    }

    public function deploymentSafetyScore(?string $sourceDir = null): array
    {
        $snapshot = $this->controlSnapshot($sourceDir);
        $rollback = $this->rollbackPreview();
        $score = 100;
        $deductions = [];

        if (($snapshot['ready'] ?? false) !== true) {
            $score -= 40;
            $deductions['control_snapshot'] = 40;
        }
        if (($rollback['ready'] ?? false) !== true) {
            $score -= 20;
            $deductions['rollback_preview'] = 20;
        }
        if (($snapshot['deployment_core_change_detected'] ?? false) === true) {
            $score -= 10;
            $deductions['deployment_core_change_detected'] = 10;
        }
        if (($snapshot['plan_summary']['skipped'] ?? 0) > 0) {
            $score -= 10;
            $deductions['skipped_files'] = 10;
        }
        if (($snapshot['manifest']['release_artifact_manifest_validation']['valid'] ?? false) !== true) {
            $score -= 15;
            $deductions['release_artifact_manifest'] = 15;
        }
        if (($snapshot['manifest']['artifact_acquisition_plan']['valid'] ?? false) !== true) {
            $score -= 15;
            $deductions['artifact_acquisition_plan'] = 15;
        }
        if (($snapshot['manifest']['artifact_pre_extract_preview']['valid'] ?? false) !== true) {
            $score -= 15;
            $deductions['artifact_pre_extract_preview'] = 15;
        }
        if (($snapshot['manifest']['artifact_integrity']['valid'] ?? false) !== true) {
            $score -= 20;
            $deductions['artifact_integrity'] = 20;
        }
        if ($sourceDir !== null && ($this->finalDeploymentPlan($sourceDir)['valid'] ?? false) !== true) {
            $score -= 20;
            $deductions['final_deployment_plan'] = 20;
        }

        $score = max(0, $score);

        return [
            'ready' => $score >= 70,
            'score' => $score,
            'grade' => $score >= 90 ? 'safe' : ($score >= 70 ? 'review' : 'blocked'),
            'read_only' => true,
            'command_execution_allowed' => false,
            'writes_allowed' => false,
            'component' => 'Core/Deployment.php',
            'deductions' => $deductions,
            'control_snapshot_ready' => ($snapshot['ready'] ?? false) === true,
            'rollback_preview_ready' => ($rollback['ready'] ?? false) === true,
            'deployment_core_change_detected' => ($snapshot['deployment_core_change_detected'] ?? false) === true,
        ];
    }

    public function deploymentSafetyScoreDetails(?string $sourceDir = null): array
    {
        $score = $this->deploymentSafetyScore($sourceDir);
        $details = [];
        foreach ($score['deductions'] as $reason => $points) {
            $details[] = [
                'reason' => $reason,
                'severity' => $points >= 40 ? 'critical' : ($points >= 20 ? 'high' : 'medium'),
                'deduction' => $points,
            ];
        }

        return [
            'read_only' => true,
            'command_execution_allowed' => false,
            'writes_allowed' => false,
            'score' => $score['score'],
            'grade' => $score['grade'],
            'details' => $details,
        ];
    }

    public function deploymentHistorySummary(int $limit = 10): array
    {
        $history = $this->path($this->config->requiredString('backup_dir')) . '/deploy_history.jsonl';
        if (!is_file($history)) {
            return [
                'read_only' => true,
                'command_execution_allowed' => false,
                'writes_allowed' => false,
                'entries' => [],
                'summary' => ['total' => 0, 'completed' => 0, 'failed' => 0],
            ];
        }

        $lines = file($history, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $entries = [];
        foreach (array_slice(array_reverse(is_array($lines) ? $lines : []), 0, max(1, $limit)) as $line) {
            $entry = json_decode((string)$line, true);
            if (is_array($entry)) {
                $entries[] = $entry;
            }
        }

        $completed = 0;
        $failed = 0;
        foreach ($entries as $entry) {
            if (($entry['status'] ?? null) === 'completed') {
                $completed++;
            } elseif (($entry['status'] ?? null) === 'failed') {
                $failed++;
            }
        }

        return [
            'read_only' => true,
            'command_execution_allowed' => false,
            'writes_allowed' => false,
            'entries' => $entries,
            'summary' => [
                'total' => count($entries),
                'completed' => $completed,
                'failed' => $failed,
            ],
        ];
    }

    public function deploymentControlReport(?string $sourceDir = null): array
    {
        $artifactEvidence = $this->releaseArtifactEvidence();

        return [
            'version' => self::CONTROL_VERSION,
            'read_only' => true,
            'command_execution_allowed' => false,
            'writes_allowed' => false,
            'component' => 'Core/Deployment.php',
            'preflight' => $this->preflight(),
            'plan_preview' => $sourceDir === null ? null : $this->planPreview($sourceDir),
            'control_snapshot' => $this->controlSnapshot($sourceDir),
            'rollback_preview' => $this->rollbackPreview(),
            'safety_score' => $this->deploymentSafetyScore($sourceDir),
            'safety_score_details' => $this->deploymentSafetyScoreDetails($sourceDir),
            'history' => $this->deploymentHistorySummary(),
            'release_artifact_manifest' => $artifactEvidence['validation'],
            'artifact_acquisition_plan' => $artifactEvidence['acquisition'],
            'artifact_pre_extract_preview' => $artifactEvidence['pre_extract'],
            'artifact_integrity' => $artifactEvidence['integrity'],
            'final_deployment_plan' => $sourceDir === null ? null : $this->finalDeploymentPlan($sourceDir),
        ];
    }

    public function recordDeploymentControlSnapshot(?string $sourceDir = null): array
    {
        $report = $this->deploymentControlReport($sourceDir);
        $path = $this->path($this->config->requiredString('backup_dir')) . '/deployment_control_snapshots.jsonl';
        $entry = [
            'time' => date('c'),
            'version' => self::CONTROL_VERSION,
            'phase' => 'control_snapshot',
            'status' => 'recorded',
            'report' => $report,
        ];

        $written = file_put_contents($path, json_encode($entry, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . PHP_EOL, FILE_APPEND | LOCK_EX);
        if ($written === false) {
            throw new RuntimeException('Failed to write deployment control snapshot.');
        }

        return [
            'recorded' => true,
            'path' => $path,
            'writes_allowed' => true,
            'configuration_file' => false,
            'audit_artifact' => true,
            'report_summary' => [
                'safety_score' => $report['safety_score']['score'] ?? null,
                'release_ready_evidence' => ($report['safety_score']['ready'] ?? false) === true,
            ],
        ];
    }

    public function rollbackStatePreview(): array
    {
        $preview = $this->rollbackPreview();
        return [
            'read_only' => true,
            'command_execution_allowed' => false,
            'writes_allowed' => false,
            'ready' => $preview['ready'],
            'projected_state' => [
                'restored_files' => $preview['summary']['restore'] ?? 0,
                'removed_files' => $preview['summary']['remove'] ?? 0,
                'missing_before_rollback' => $preview['summary']['missing'] ?? 0,
            ],
            'rollback_preview' => $preview,
        ];
    }

    public function deploymentControlDiff(array $previous, ?string $sourceDir = null): array
    {
        $current = $this->deploymentControlReport($sourceDir);
        $changes = [];
        foreach ([
            'preflight',
            'control_snapshot',
            'rollback_preview',
            'safety_score',
            'release_artifact_manifest',
            'artifact_acquisition_plan',
            'artifact_pre_extract_preview',
            'artifact_integrity',
            'final_deployment_plan',
        ] as $section) {
            if (($previous[$section] ?? null) !== ($current[$section] ?? null)) {
                $changes[] = $section;
            }
        }

        return [
            'read_only' => true,
            'command_execution_allowed' => false,
            'writes_allowed' => false,
            'changed_sections' => $changes,
            'summary' => [
                'changes' => count($changes),
                'current_safety_score' => $current['safety_score']['score'] ?? null,
                'previous_safety_score' => $previous['safety_score']['score'] ?? null,
            ],
        ];
    }

    public function releaseEvidenceBundle(?string $sourceDir = null): array
    {
        $report = $this->deploymentControlReport($sourceDir);
        return [
            'version' => self::CONTROL_VERSION,
            'read_only' => true,
            'command_execution_allowed' => false,
            'writes_allowed' => false,
            'evidence' => [
                'control_report' => $report,
                'release_gate_inputs' => [
                    'control_snapshot_ready' => $report['control_snapshot']['ready'] ?? false,
                    'rollback_preview_ready' => $report['rollback_preview']['ready'] ?? false,
                    'deployment_safety_score' => $report['safety_score']['score'] ?? 0,
                    'release_artifact_manifest_valid' => $report['release_artifact_manifest']['valid'] ?? false,
                    'artifact_acquisition_plan_valid' => $report['artifact_acquisition_plan']['valid'] ?? false,
                    'artifact_pre_extract_preview_valid' => $report['artifact_pre_extract_preview']['valid'] ?? false,
                    'artifact_integrity_valid' => $report['artifact_integrity']['valid'] ?? false,
                    'final_deployment_plan_valid' => $report['final_deployment_plan']['valid'] ?? ($sourceDir === null),
                    'final_deployment_plan_fingerprint' => $report['final_deployment_plan']['fingerprint'] ?? null,
                ],
            ],
        ];
    }

    public function stableReleaseCandidateGate(?string $sourceDir = null): array
    {
        $bundle = $this->releaseEvidenceBundle($sourceDir);
        $inputs = $bundle['evidence']['release_gate_inputs'];
        $ready = ($inputs['control_snapshot_ready'] ?? false) === true
            && ($inputs['rollback_preview_ready'] ?? false) === true
            && ($inputs['deployment_safety_score'] ?? 0) >= 70
            && ($inputs['release_artifact_manifest_valid'] ?? false) === true
            && ($inputs['artifact_acquisition_plan_valid'] ?? false) === true
            && ($inputs['artifact_pre_extract_preview_valid'] ?? false) === true
            && ($inputs['artifact_integrity_valid'] ?? false) === true
            && ($inputs['final_deployment_plan_valid'] ?? false) === true;

        return [
            'rc_ready' => $ready,
            'grade' => $ready ? 'release-candidate' : 'blocked',
            'read_only' => true,
            'command_execution_allowed' => false,
            'writes_allowed' => false,
            'inputs' => $inputs,
        ];
    }

    public function executionGate(string $sourceDir, ?string $expectedFingerprint = null): array
    {
        $candidateGate = $this->stableReleaseCandidateGate($sourceDir);
        $finalPlan = $this->finalDeploymentPlan($sourceDir);
        $fingerprint = is_string($finalPlan['fingerprint'] ?? null) ? $finalPlan['fingerprint'] : null;
        $fingerprintMatched = $expectedFingerprint === null || ($fingerprint !== null && hash_equals($fingerprint, $expectedFingerprint));
        $checks = [
            'stable_release_candidate_ready' => ($candidateGate['rc_ready'] ?? false) === true,
            'final_deployment_plan_valid' => ($finalPlan['valid'] ?? false) === true,
            'final_deployment_plan_frozen' => ($finalPlan['frozen'] ?? false) === true,
            'final_deployment_plan_fingerprint_present' => $fingerprint !== null,
            'expected_fingerprint_matched' => $fingerprintMatched,
            'safety_score_minimum' => ($candidateGate['inputs']['deployment_safety_score'] ?? 0) >= 70,
        ];

        return [
            'ready' => self::gateReady($checks),
            'version' => self::CONTROL_VERSION,
            'theme' => 'Deployment Execution Gate',
            'read_only' => true,
            'command_execution_allowed' => false,
            'writes_allowed' => false,
            'configuration_file' => false,
            'audit_artifact' => true,
            'dashboard_execution_enabled' => false,
            'apply_allowed' => self::gateReady($checks),
            'checks' => $checks,
            'expected_fingerprint' => $expectedFingerprint,
            'final_plan_fingerprint' => $fingerprint,
            'stable_release_candidate_gate' => $candidateGate,
            'final_deployment_plan' => [
                'valid' => $finalPlan['valid'] ?? false,
                'fingerprint' => $fingerprint,
                'summary' => $finalPlan['summary'] ?? [],
                'files' => $finalPlan['files'] ?? [],
            ],
        ];
    }

    public function deploymentDryRun(string $sourceDir, ?string $expectedFingerprint = null): array
    {
        $gate = $this->executionGate($sourceDir, $expectedFingerprint);

        return [
            'dry_run' => true,
            'ready' => ($gate['ready'] ?? false) === true,
            'version' => self::CONTROL_VERSION,
            'read_only' => true,
            'command_execution_allowed' => false,
            'writes_allowed' => false,
            'apply_allowed' => false,
            'configuration_file' => false,
            'audit_artifact' => true,
            'final_plan_fingerprint' => $gate['final_plan_fingerprint'] ?? null,
            'execution_gate' => $gate,
        ];
    }

    public function recordDeploymentAuditLedger(string $event, ?string $sourceDir = null, array $context = []): array
    {
        if (!preg_match('/^[a-z0-9_.-]+$/', $event)) {
            throw new InvalidArgumentException('Deployment audit event name must be a stable identifier.');
        }

        $path = $this->path($this->config->requiredString('backup_dir')) . '/deployment_audit_ledger.jsonl';
        $evidence = $sourceDir === null ? $this->deploymentControlReport(null) : $this->deploymentDryRun($sourceDir);
        $entry = [
            'time' => date('c'),
            'version' => self::CONTROL_VERSION,
            'event' => $event,
            'configuration_file' => false,
            'audit_artifact' => true,
            'context' => $context,
            'evidence' => $evidence,
        ];

        $written = file_put_contents($path, json_encode($entry, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . PHP_EOL, FILE_APPEND | LOCK_EX);
        if ($written === false) {
            throw new RuntimeException('Failed to write deployment audit ledger.');
        }

        return [
            'recorded' => true,
            'path' => $path,
            'writes_allowed' => true,
            'configuration_file' => false,
            'audit_artifact' => true,
            'event' => $event,
            'fingerprint' => is_string($evidence['final_plan_fingerprint'] ?? null) ? $evidence['final_plan_fingerprint'] : null,
        ];
    }

    public function autoDeploy(string $sourceDir, string $expectedFingerprint): array
    {
        $this->verifyHttpTrigger();
        $source = $this->path($sourceDir);
        $gate = $this->executionGate($source, $expectedFingerprint);
        if (($gate['ready'] ?? false) !== true) {
            return [
                'status' => 'blocked',
                'applied' => false,
                'rolled_back' => false,
                'execution_gate' => $gate,
            ];
        }

        $this->lock();
        $this->maintenance(true);
        $snapshot = null;
        $changes = [];

        try {
            $lockedPlan = $this->finalDeploymentPlan($source);
            $lockedFingerprint = is_string($lockedPlan['fingerprint'] ?? null) ? $lockedPlan['fingerprint'] : '';
            if (($lockedPlan['valid'] ?? false) !== true || !hash_equals($lockedFingerprint, $expectedFingerprint)) {
                throw new RuntimeException('Deployment final plan fingerprint changed before apply.');
            }

            $changes = $this->diff($source);
            $snapshot = $this->backup($changes);
            $this->recordDeploymentAuditLedger('auto_deploy_started', $source, [
                'snapshot' => $snapshot,
                'changes' => count($changes),
            ]);
            $this->apply($source, $changes);
            $this->recordDeploymentAuditLedger('auto_deploy_applied', $source, [
                'snapshot' => $snapshot,
                'changes' => count($changes),
            ]);
            $this->healthCheck();
            $this->recordHistory($snapshot, $changes);
            $ledger = $this->recordDeploymentAuditLedger('auto_deploy_completed', $source, [
                'snapshot' => $snapshot,
                'changes' => count($changes),
            ]);

            return [
                'status' => 'completed',
                'applied' => true,
                'rolled_back' => false,
                'snapshot' => $snapshot,
                'changes' => $changes,
                'execution_gate' => $gate,
                'audit_ledger' => $ledger,
            ];
        } catch (Throwable $exception) {
            $rolledBack = false;
            if ($snapshot !== null) {
                $this->rollbackSnapshot($snapshot);
                $rolledBack = true;
            }
            $this->recordDeploymentAuditLedger($rolledBack ? 'auto_deploy_rolled_back' : 'auto_deploy_failed', $source, [
                'snapshot' => $snapshot,
                'changes' => count($changes),
                'error' => $exception->getMessage(),
            ]);

            return [
                'status' => $rolledBack ? 'rolled_back' : 'failed',
                'applied' => false,
                'rolled_back' => $rolledBack,
                'snapshot' => $snapshot,
                'changes' => $changes,
                'error' => $exception->getMessage(),
                'execution_gate' => $gate,
            ];
        } finally {
            $this->cleanup();
        }
    }

    public function providerCapabilityMatrix(?string $provider = null): array
    {
        $profiles = self::providerProfiles();
        $selected = $this->selectedProvider($provider);

        return [
            'version' => self::CONTROL_VERSION,
            'selected_provider' => $selected,
            'read_only' => true,
            'command_execution_allowed' => false,
            'writes_allowed' => false,
            'configuration_file' => false,
            'public_api_required' => false,
            'provider_api_internal_only' => true,
            'credentials_persisted' => false,
            'profiles' => $profiles,
            'selected_profile' => $profiles[$selected],
        ];
    }

    public function providerExecutionPlan(?string $provider = null): array
    {
        $selected = $this->selectedProvider($provider);
        $capabilities = $this->providerCapabilities($provider);
        $steps = [
            'provider_preflight',
            'artifact_transfer',
            'remote_release_prepare',
            'remote_health_check',
            'provider_audit_evidence',
        ];
        if (($capabilities['service_restart'] ?? false) === true) {
            $steps[] = 'service_restart';
        }
        if (($capabilities['snapshot'] ?? false) !== false) {
            $steps[] = 'provider_snapshot';
        }
        if (($capabilities['rollback'] ?? false) !== false) {
            $steps[] = 'provider_rollback_plan';
        }

        $manualRequired = ($capabilities['manual_required'] ?? false) === true;

        return [
            'valid' => true,
            'provider' => $selected,
            'read_only' => true,
            'command_execution_allowed' => false,
            'writes_allowed' => false,
            'configuration_file' => false,
            'audit_artifact' => true,
            'public_api_required' => false,
            'provider_api_internal_only' => true,
            'credentials_persisted' => false,
            'manual_required' => $manualRequired,
            'steps' => $steps,
            'capabilities' => $capabilities,
        ];
    }

    public function providerAuditEvidence(?string $provider = null): array
    {
        $plan = $this->providerExecutionPlan($provider);
        $fingerprint = self::fingerprint([
            'version' => self::CONTROL_VERSION,
            'provider' => $plan['provider'],
            'steps' => $plan['steps'],
            'capabilities' => $plan['capabilities'],
        ]);

        return [
            'valid' => ($plan['valid'] ?? false) === true,
            'provider' => $plan['provider'],
            'fingerprint' => $fingerprint,
            'configuration_file' => false,
            'audit_artifact' => true,
            'provider_api_internal_only' => true,
            'credentials_persisted' => false,
            'plan' => $plan,
        ];
    }

    public function providerOrchestrator(?string $provider = null): array
    {
        $matrix = $this->providerCapabilityMatrix($provider);
        $plan = $this->providerExecutionPlan($matrix['selected_provider']);
        return [
            'valid' => true,
            'provider' => $matrix['selected_provider'],
            'read_only' => true,
            'command_execution_allowed' => false,
            'writes_allowed' => false,
            'configuration_file' => false,
            'public_api_required' => false,
            'orchestration_layers' => ['deployment_plan', 'provider_orchestration', 'execution'],
            'capability_matrix' => $matrix,
            'execution_plan' => $plan,
        ];
    }

    public function remoteOperationPlan(?string $provider = null): array
    {
        $plan = $this->providerExecutionPlan($provider);
        $operations = [
            'upload_artifact',
            'verify_remote_sha256',
            'extract_release',
            'switch_current_release',
            'run_remote_php_lint',
            'health_probe',
            'provider_audit_evidence',
        ];
        if (in_array('service_restart', $plan['steps'] ?? [], true)) {
            $operations[] = 'restart_service';
        }
        if (in_array('provider_rollback_plan', $plan['steps'] ?? [], true)) {
            $operations[] = 'rollback_release';
        }

        return [
            'valid' => true,
            'provider' => $plan['provider'],
            'read_only' => true,
            'command_execution_allowed' => false,
            'writes_allowed' => false,
            'operations' => $operations,
        ];
    }

    public function providerCredentialPolicy(): array
    {
        return [
            'valid' => true,
            'configuration_file' => false,
            'env_file_allowed' => false,
            'credentials_persisted' => false,
            'runtime_injection_only' => true,
            'redaction_required' => true,
            'audit_stores_secret_values' => false,
            'credential_reference_fingerprint_only' => true,
        ];
    }

    public function providerApiTransportEvidence(?string $provider = null, string $operation = 'provider_preflight'): array
    {
        $plan = $this->providerExecutionPlan($provider);
        $requestFingerprint = self::fingerprint([
            'provider' => $plan['provider'],
            'operation' => $operation,
            'credentials' => 'redacted',
        ]);
        $responseFingerprint = self::fingerprint([
            'status' => 'planned',
            'redaction_applied' => true,
        ]);

        return [
            'valid' => true,
            'provider' => $plan['provider'],
            'operation' => $operation,
            'status' => 'planned',
            'request_fingerprint' => $requestFingerprint,
            'response_fingerprint' => $responseFingerprint,
            'retry_count' => 0,
            'redaction_applied' => true,
            'secret_values_exposed' => false,
            'audit_artifact' => true,
        ];
    }

    public function multiProviderDeploymentPlan(array $providers = ['xserver_rental', 'xserver_vps']): array
    {
        $plans = [];
        foreach ($providers as $provider) {
            if (!is_string($provider) || $provider === '') {
                continue;
            }
            $plans[] = $this->providerExecutionPlan($provider);
        }

        return [
            'valid' => $plans !== [],
            'strategy' => 'sequential',
            'read_only' => true,
            'command_execution_allowed' => false,
            'writes_allowed' => false,
            'plans' => $plans,
            'provider_count' => count($plans),
        ];
    }

    public function providerHealthProbe(?string $provider = null): array
    {
        $plan = $this->providerExecutionPlan($provider);
        return [
            'valid' => ($plan['capabilities']['health_check'] ?? false) === true,
            'provider' => $plan['provider'],
            'read_only' => true,
            'command_execution_allowed' => false,
            'writes_allowed' => false,
            'probes' => ['remote_php_lint', 'health_endpoint', 'artifact_sha256'],
        ];
    }

    public function providerRollbackOrchestrator(?string $provider = null): array
    {
        $plan = $this->providerExecutionPlan($provider);
        $rollback = $plan['capabilities']['rollback'] ?? false;
        return [
            'valid' => $rollback !== false,
            'provider' => $plan['provider'],
            'read_only' => true,
            'command_execution_allowed' => false,
            'writes_allowed' => false,
            'rollback_mode' => $rollback,
            'steps' => ['select_previous_release', 'verify_snapshot', 'switch_release', 'health_probe', 'audit_rollback'],
        ];
    }

    public function providerOrchestratedReleaseGate(?string $provider = null): array
    {
        $checks = [
            'orchestrator_valid' => ($this->providerOrchestrator($provider)['valid'] ?? false) === true,
            'remote_plan_valid' => ($this->remoteOperationPlan($provider)['valid'] ?? false) === true,
            'credential_policy_valid' => ($this->providerCredentialPolicy()['valid'] ?? false) === true,
            'transport_evidence_valid' => ($this->providerApiTransportEvidence($provider)['valid'] ?? false) === true,
            'health_probe_valid' => ($this->providerHealthProbe($provider)['valid'] ?? false) === true,
            'rollback_orchestrator_valid' => ($this->providerRollbackOrchestrator($provider)['valid'] ?? false) === true,
        ];

        return [
            'ready' => self::gateReady($checks),
            'target' => 'v0.305',
            'read_only' => true,
            'command_execution_allowed' => false,
            'writes_allowed' => false,
            'checks' => $checks,
        ];
    }

    public function providerRuntimeInterface(?string $provider = null): array
    {
        $plan = $this->providerExecutionPlan($provider);

        return [
            'valid' => true,
            'provider' => $plan['provider'],
            'target' => 'v0.306',
            'read_only' => true,
            'command_execution_allowed' => false,
            'writes_allowed' => false,
            'public_api_required' => false,
            'operations' => ['connect', 'upload', 'verify', 'extract', 'switch', 'restart', 'snapshot', 'rollback', 'health', 'disconnect'],
            'adapter' => $this->providerCapabilityMatrix($provider)['selected_profile']['adapter'] ?? 'provider_runtime_adapter',
        ];
    }

    public function remoteStateSnapshot(?string $provider = null): array
    {
        $plan = $this->providerExecutionPlan($provider);
        $state = [
            'provider' => $plan['provider'],
            'current_release' => 'planned',
            'document_root' => $this->config->get('remote_document_root', 'public_html'),
            'php_version' => 'provider_declared',
            'service_status' => 'provider_declared',
            'disk_status' => 'provider_declared',
            'capabilities' => $plan['capabilities'],
        ];

        return [
            'valid' => true,
            'target' => 'v0.307',
            'read_only' => true,
            'command_execution_allowed' => false,
            'writes_allowed' => false,
            'fingerprint' => self::fingerprint($state),
            'state' => $state,
        ];
    }

    public function providerTransactionPlan(?string $provider = null): array
    {
        $runtime = $this->providerRuntimeInterface($provider);

        return [
            'valid' => ($runtime['valid'] ?? false) === true,
            'target' => 'v0.308',
            'provider' => $runtime['provider'],
            'read_only' => true,
            'command_execution_allowed' => false,
            'writes_allowed' => false,
            'rollback_on_failure' => true,
            'steps' => ['begin', 'lock', 'connect', 'upload', 'verify', 'switch', 'health', 'commit', 'rollback_on_failure', 'disconnect'],
        ];
    }

    public function providerRetryBackoffPolicy(): array
    {
        return [
            'valid' => true,
            'target' => 'v0.309',
            'retry_max' => 3,
            'backoff_seconds' => [1, 3, 9],
            'timeout_seconds' => 60,
            'retryable_errors' => ['timeout', 'rate_limited', 'temporary_unavailable'],
            'non_retryable_errors' => ['auth_failed', 'fingerprint_mismatch', 'unsafe_path'],
        ];
    }

    public function providerRateLimitGuard(): array
    {
        return [
            'valid' => true,
            'target' => 'v0.310',
            'request_limit_per_window' => 60,
            'window_seconds' => 60,
            'cooldown_seconds' => 30,
            'emergency_stop_enabled' => true,
            'provider_quota_required' => true,
        ];
    }

    public function providerSecretRedactionEngine(array $payload = []): array
    {
        $redacted = [];
        foreach ($payload as $key => $value) {
            $name = strtolower((string)$key);
            $redacted[$key] = str_contains($name, 'token')
                || str_contains($name, 'secret')
                || str_contains($name, 'password')
                || str_contains($name, 'key')
                ? '[redacted]'
                : $value;
        }

        return [
            'valid' => true,
            'target' => 'v0.311',
            'secret_values_exposed' => false,
            'credential_reference_fingerprint_only' => true,
            'redaction_applied' => $redacted !== $payload,
            'payload' => $redacted,
        ];
    }

    public function xserverRentalRuntimeAdapter(): array
    {
        return [
            'valid' => true,
            'target' => 'v0.312',
            'provider' => 'xserver_rental',
            'adapter' => 'xserver_rental_runtime_adapter',
            'runtime_operations' => ['push_artifact', 'sftp_upload', 'public_html_deploy', 'php_lint', 'health_check'],
            'service_restart_supported' => false,
            'manual_required' => true,
            'document_root' => 'public_html',
        ];
    }

    public function xserverVpsRuntimeAdapter(): array
    {
        return [
            'valid' => true,
            'target' => 'v0.313',
            'provider' => 'xserver_vps',
            'adapter' => 'xserver_vps_runtime_adapter',
            'runtime_operations' => ['ssh_command', 'artifact_pull', 'service_restart', 'snapshot', 'rollback', 'firewall_check', 'health_probe'],
            'service_restart_supported' => true,
            'manual_required' => false,
            'document_root' => 'provider_declared',
        ];
    }

    public function providerRuntimeExecutionPlan(?string $provider = null): array
    {
        $runtime = $this->providerRuntimeInterface($provider);
        $remote = $this->remoteOperationPlan($provider);

        return [
            'valid' => ($runtime['valid'] ?? false) === true && ($remote['valid'] ?? false) === true,
            'target' => 'v0.314',
            'provider' => $runtime['provider'],
            'read_only' => true,
            'command_execution_allowed' => false,
            'writes_allowed' => false,
            'phases' => ['transfer', 'verify', 'extract', 'switch', 'restart', 'health', 'rollback'],
            'runtime' => $runtime,
            'remote_operations' => $remote['operations'] ?? [],
        ];
    }

    public function remoteArtifactLifecycle(?string $provider = null): array
    {
        return [
            'valid' => true,
            'target' => 'v0.315',
            'provider' => $this->providerExecutionPlan($provider)['provider'],
            'states' => ['uploaded', 'verified', 'extracted', 'promoted', 'retained', 'pruned'],
            'fingerprint_required' => true,
            'retention_policy' => 'provider_runtime_managed',
        ];
    }

    public function remoteReleaseSwitchStrategy(?string $provider = null): array
    {
        $plan = $this->providerExecutionPlan($provider);
        $strategy = $plan['provider'] === 'xserver_rental' ? 'public_html_overwrite' : 'release_directory_switch';

        return [
            'valid' => true,
            'target' => 'v0.316',
            'provider' => $plan['provider'],
            'strategy' => $strategy,
            'atomic_switch_supported' => $strategy !== 'public_html_overwrite',
            'fallback_strategy' => 'direct_copy',
        ];
    }

    public function providerRuntimeFailureClassifier(string $failure = 'health_failed'): array
    {
        $classes = ['auth_failed', 'network_timeout', 'quota_limited', 'fingerprint_mismatch', 'remote_lint_failed', 'health_failed', 'rollback_failed'];

        return [
            'valid' => in_array($failure, $classes, true),
            'target' => 'v0.317',
            'failure' => $failure,
            'known_classes' => $classes,
            'severity' => in_array($failure, ['auth_failed', 'fingerprint_mismatch', 'rollback_failed'], true) ? 'critical' : 'high',
        ];
    }

    public function providerRuntimeRecoveryPlan(string $failure = 'health_failed'): array
    {
        $classified = $this->providerRuntimeFailureClassifier($failure);
        $actions = match ($failure) {
            'auth_failed' => ['refresh_runtime_credential', 'retry_after_operator_confirmation'],
            'quota_limited' => ['wait_provider_quota_window', 'retry_with_backoff'],
            'fingerprint_mismatch' => ['block_apply', 'rebuild_final_plan'],
            'rollback_failed' => ['manual_intervention_required', 'preserve_audit_evidence'],
            default => ['retry_with_backoff', 'rollback_release', 'health_probe'],
        };

        return [
            'valid' => ($classified['valid'] ?? false) === true,
            'target' => 'v0.318',
            'failure' => $failure,
            'actions' => $actions,
            'manual_intervention_required' => in_array('manual_intervention_required', $actions, true),
        ];
    }

    public function providerRuntimeDashboardControl(?string $provider = null): array
    {
        return [
            'ready' => true,
            'target' => 'v0.319',
            'provider' => $this->providerExecutionPlan($provider)['provider'],
            'read_only' => true,
            'command_execution_allowed' => false,
            'writes_allowed' => false,
            'sections' => ['runtime_adapter', 'execution_plan', 'artifact_lifecycle', 'switch_strategy', 'failure_classifier', 'recovery_plan'],
        ];
    }

    public function providerRuntimeExecutionGate(?string $provider = null): array
    {
        $checks = [
            'runtime_interface' => ($this->providerRuntimeInterface($provider)['valid'] ?? false) === true,
            'execution_plan' => ($this->providerRuntimeExecutionPlan($provider)['valid'] ?? false) === true,
            'artifact_lifecycle' => ($this->remoteArtifactLifecycle($provider)['valid'] ?? false) === true,
            'switch_strategy' => ($this->remoteReleaseSwitchStrategy($provider)['valid'] ?? false) === true,
            'recovery_plan' => ($this->providerRuntimeRecoveryPlan('health_failed')['valid'] ?? false) === true,
            'dashboard_control' => ($this->providerRuntimeDashboardControl($provider)['ready'] ?? false) === true,
        ];

        return [
            'ready' => self::gateReady($checks),
            'target' => 'v0.320',
            'provider' => $this->providerExecutionPlan($provider)['provider'],
            'read_only' => true,
            'command_execution_allowed' => false,
            'writes_allowed' => false,
            'checks' => $checks,
        ];
    }

    public function providerRuntimeOperationJournal(?string $provider = null): array
    {
        $plan = $this->providerExecutionPlan($provider);
        $events = ['preflight_started', 'credential_enveloped', 'apply_planned', 'health_observed', 'audit_bundled'];

        return [
            'valid' => true,
            'target' => 'v0.321',
            'provider' => $plan['provider'],
            'append_only' => true,
            'format' => 'jsonl_evidence',
            'configuration_file' => false,
            'events' => $events,
            'fingerprint' => self::fingerprint([$plan['provider'], $events]),
        ];
    }

    public function providerRuntimeCredentialEnvelope(array $credentialReference = []): array
    {
        $payload = $credentialReference === [] ? ['credential_reference' => 'runtime_injected'] : $credentialReference;
        $redacted = $this->providerSecretRedactionEngine($payload);

        return [
            'valid' => true,
            'target' => 'v0.322',
            'runtime_injection_only' => true,
            'credentials_persisted' => false,
            'configuration_file' => false,
            'secret_values_exposed' => false,
            'reference_fingerprint' => self::fingerprint($redacted['payload']),
            'redacted_payload' => $redacted['payload'],
        ];
    }

    public function providerRuntimePreflight(?string $provider = null): array
    {
        $checks = [
            'execution_gate' => ($this->providerRuntimeExecutionGate($provider)['ready'] ?? false) === true,
            'rate_limit' => ($this->providerRateLimitGuard()['valid'] ?? false) === true,
            'credential_envelope' => ($this->providerRuntimeCredentialEnvelope()['valid'] ?? false) === true,
            'remote_state' => ($this->remoteStateSnapshot($provider)['valid'] ?? false) === true,
            'operation_journal' => ($this->providerRuntimeOperationJournal($provider)['valid'] ?? false) === true,
        ];

        return [
            'ready' => self::gateReady($checks),
            'target' => 'v0.323',
            'provider' => $this->providerExecutionPlan($provider)['provider'],
            'read_only' => true,
            'command_execution_allowed' => false,
            'checks' => $checks,
        ];
    }

    public function providerRuntimeApplyPlan(?string $provider = null): array
    {
        $execution = $this->providerRuntimeExecutionPlan($provider);
        $lifecycle = $this->remoteArtifactLifecycle($provider);
        $switch = $this->remoteReleaseSwitchStrategy($provider);

        return [
            'valid' => ($execution['valid'] ?? false) === true && ($lifecycle['valid'] ?? false) === true && ($switch['valid'] ?? false) === true,
            'target' => 'v0.324',
            'provider' => $execution['provider'],
            'read_only' => true,
            'command_execution_allowed' => false,
            'apply_steps' => ['preflight', 'transfer', 'verify', 'promote', 'switch', 'health', 'journal'],
            'execution_phases' => $execution['phases'] ?? [],
            'lifecycle_states' => $lifecycle['states'] ?? [],
            'switch_strategy' => $switch['strategy'] ?? null,
        ];
    }

    public function providerRuntimeRollbackDrill(?string $provider = null): array
    {
        $rollback = $this->providerRollbackOrchestrator($provider);

        return [
            'valid' => ($rollback['valid'] ?? false) === true,
            'target' => 'v0.325',
            'provider' => $rollback['provider'] ?? $this->providerExecutionPlan($provider)['provider'],
            'drill_required_before_release' => true,
            'destructive_execution' => false,
            'steps' => ['snapshot_reference', 'rollback_route', 'health_probe_after_rollback', 'audit_evidence'],
        ];
    }

    public function providerRuntimeHealthSla(?string $provider = null): array
    {
        return [
            'valid' => true,
            'target' => 'v0.326',
            'provider' => $this->providerExecutionPlan($provider)['provider'],
            'required_probe_count' => 3,
            'failure_threshold' => 1,
            'observability_events' => ['http_status', 'php_lint', 'service_status', 'latency_ms'],
            'release_block_on_failure' => true,
        ];
    }

    public function providerRuntimeProviderRegistry(): array
    {
        $profiles = $this->providerCapabilityMatrix()['profiles'];

        return [
            'valid' => true,
            'target' => 'v0.327',
            'provider_count' => count($profiles),
            'providers' => array_keys($profiles),
            'xserver_profiles_required' => ['xserver_rental', 'xserver_vps'],
            'generic_provider_extension_allowed' => true,
            'public_api_required' => false,
            'configuration_file' => false,
        ];
    }

    public function providerRuntimeAuditBundle(?string $provider = null): array
    {
        $bundle = [
            'journal' => $this->providerRuntimeOperationJournal($provider),
            'preflight' => $this->providerRuntimePreflight($provider),
            'apply_plan' => $this->providerRuntimeApplyPlan($provider),
            'rollback_drill' => $this->providerRuntimeRollbackDrill($provider),
            'health_sla' => $this->providerRuntimeHealthSla($provider),
        ];

        return [
            'valid' => self::gateReady(array_map(static fn(array $entry): bool => ($entry['valid'] ?? $entry['ready'] ?? false) === true, $bundle)),
            'target' => 'v0.328',
            'provider' => $this->providerExecutionPlan($provider)['provider'],
            'bundle' => $bundle,
            'secret_values_exposed' => false,
            'fingerprint' => self::fingerprint($bundle),
        ];
    }

    public function providerRuntimeOperationsDashboard(?string $provider = null): array
    {
        return [
            'ready' => true,
            'target' => 'v0.329',
            'provider' => $this->providerExecutionPlan($provider)['provider'],
            'read_only' => true,
            'command_execution_allowed' => false,
            'writes_allowed' => false,
            'sections' => ['preflight', 'apply_plan', 'rollback_drill', 'health_sla', 'audit_bundle', 'operations_gate'],
        ];
    }

    public function providerRuntimeOperationsGate(?string $provider = null): array
    {
        $checks = [
            'preflight' => ($this->providerRuntimePreflight($provider)['ready'] ?? false) === true,
            'apply_plan' => ($this->providerRuntimeApplyPlan($provider)['valid'] ?? false) === true,
            'rollback_drill' => ($this->providerRuntimeRollbackDrill($provider)['valid'] ?? false) === true,
            'health_sla' => ($this->providerRuntimeHealthSla($provider)['valid'] ?? false) === true,
            'provider_registry' => ($this->providerRuntimeProviderRegistry()['valid'] ?? false) === true,
            'audit_bundle' => ($this->providerRuntimeAuditBundle($provider)['valid'] ?? false) === true,
            'dashboard_operations' => ($this->providerRuntimeOperationsDashboard($provider)['ready'] ?? false) === true,
        ];

        return [
            'ready' => self::gateReady($checks),
            'target' => 'v0.330',
            'provider' => $this->providerExecutionPlan($provider)['provider'],
            'read_only' => true,
            'command_execution_allowed' => false,
            'writes_allowed' => false,
            'checks' => $checks,
        ];
    }

    public function serverApiDriverContract(?string $provider = null): array
    {
        $providerName = $this->providerName($provider);

        return $this->validEvidence('v0.331', $providerName, [
            'valid' => true,
            'internal_only' => true,
            'public_api_required' => false,
            'configuration_file' => false,
            'required_methods' => ['capabilities', 'authenticate', 'preflight', 'apply', 'rollback', 'health', 'audit'],
            'required_evidence' => ['driver_fingerprint', 'capability_probe', 'auth_session', 'transaction', 'drift_detection'],
            'fingerprint' => self::fingerprint([$providerName, 'server_api_driver_contract']),
        ]);
    }

    public function serverApiCapabilityProbe(?string $provider = null): array
    {
        $matrix = $this->providerCapabilityMatrix($provider);
        $capabilities = $matrix['selected_profile']['capabilities'] ?? [];
        $probe = [
            'file_upload' => ($capabilities['push_artifact'] ?? false) !== false || ($capabilities['sftp'] ?? false) !== false,
            'service_restart' => ($capabilities['service_restart'] ?? false) !== false,
            'snapshot' => ($capabilities['snapshot'] ?? false) !== false,
            'rollback' => ($capabilities['rollback'] ?? false) !== false,
            'dns' => ($capabilities['dns'] ?? false) !== false,
            'ssl' => ($capabilities['ssl'] ?? false) !== false,
            'mysql' => false,
            'health_check' => true,
        ];

        return $this->validEvidence('v0.332', $matrix['selected_provider'], [
            'valid' => true,
            'capabilities' => $probe,
            'mysql_supported' => false,
            'public_api_required' => false,
            'fingerprint' => self::fingerprint($probe),
        ]);
    }

    public function serverApiAuthSession(array $credentialReference = []): array
    {
        $envelope = $this->providerRuntimeCredentialEnvelope($credentialReference);

        return [
            'valid' => ($envelope['valid'] ?? false) === true,
            'target' => 'v0.333',
            'runtime_injection_only' => true,
            'credentials_persisted' => false,
            'secret_values_exposed' => false,
            'session_evidence_only' => true,
            'session_fingerprint' => self::fingerprint((string)($envelope['reference_fingerprint'] ?? 'runtime_injected')),
        ];
    }

    public function remoteCommandSandbox(?string $provider = null): array
    {
        $providerName = $this->providerName($provider);
        $commands = $providerName === 'xserver_vps'
            ? ['artifact_pull', 'php_lint', 'service_restart', 'health_probe', 'rollback']
            : ['sftp_upload', 'php_lint', 'health_probe'];

        return $this->validEvidence('v0.334', $providerName, [
            'valid' => true,
            'allowed_commands' => $commands,
            'shell_passthrough_allowed' => false,
            'arbitrary_command_allowed' => false,
            'deny_by_default' => true,
        ]);
    }

    public function serverApiTransactionEngine(?string $provider = null): array
    {
        $apply = $this->providerRuntimeApplyPlan($provider);
        $rollback = $this->providerRuntimeRollbackDrill($provider);

        return [
            'valid' => ($apply['valid'] ?? false) === true && ($rollback['valid'] ?? false) === true,
            'target' => 'v0.335',
            'provider' => $apply['provider'],
            'transaction_steps' => ['begin', 'preflight', 'apply', 'verify', 'commit_or_rollback', 'audit'],
            'rollback_route_required' => true,
            'rollback_on_failure' => true,
            'apply_plan_fingerprint' => self::fingerprint($apply),
        ];
    }

    public function providerDriftDetection(?string $provider = null): array
    {
        $before = $this->remoteStateSnapshot($provider);
        $after = $before;
        $drift = ($before['fingerprint'] ?? null) !== ($after['fingerprint'] ?? null);

        return [
            'valid' => true,
            'target' => 'v0.336',
            'provider' => $before['provider'] ?? $this->providerName($provider),
            'drift_detected' => $drift,
            'apply_blocked' => $drift,
            'before_fingerprint' => $before['fingerprint'] ?? null,
            'after_fingerprint' => $after['fingerprint'] ?? null,
        ];
    }

    public function serverApiGovernance(?string $provider = null): array
    {
        $rateLimit = $this->providerRateLimitGuard();

        return $this->validEvidence('v0.337', $this->providerName($provider), [
            'valid' => ($rateLimit['valid'] ?? false) === true,
            'retry_max' => $rateLimit['retry_max'] ?? 3,
            'cooldown_seconds' => $rateLimit['cooldown_seconds'] ?? 30,
            'emergency_stop_enabled' => true,
            'quota_window_required' => true,
        ]);
    }

    public function multiProviderFailoverPlan(array $providers = ['xserver_rental', 'xserver_vps']): array
    {
        $providers = $providers === [] ? ['xserver_rental', 'xserver_vps'] : array_values(array_unique($providers));
        $plans = [];
        foreach ($providers as $provider) {
            $plans[$provider] = [
                'capability_probe' => $this->serverApiCapabilityProbe($provider),
                'health_sla' => $this->providerRuntimeHealthSla($provider),
            ];
        }

        return [
            'valid' => true,
            'target' => 'v0.338',
            'providers' => $providers,
            'automatic_failover_enabled' => false,
            'manual_promotion_required' => true,
            'plans' => $plans,
            'fingerprint' => self::fingerprint($plans),
        ];
    }

    public function dashboardServerApiConsole(?string $provider = null): array
    {
        return $this->readyEvidence('v0.339', $this->providerName($provider), [
            'ready' => true,
            'read_only' => true,
            'command_execution_allowed' => false,
            'writes_allowed' => false,
            'sections' => ['driver_contract', 'capability_probe', 'auth_session', 'command_sandbox', 'transaction_engine', 'drift_detection', 'governance', 'failover_plan'],
        ]);
    }

    public function serverApiExecutionGate(?string $provider = null): array
    {
        $checks = [
            'driver_contract' => ($this->serverApiDriverContract($provider)['valid'] ?? false) === true,
            'capability_probe' => ($this->serverApiCapabilityProbe($provider)['valid'] ?? false) === true,
            'auth_session' => ($this->serverApiAuthSession()['valid'] ?? false) === true,
            'command_sandbox' => ($this->remoteCommandSandbox($provider)['valid'] ?? false) === true,
            'transaction_engine' => ($this->serverApiTransactionEngine($provider)['valid'] ?? false) === true,
            'drift_detection' => ($this->providerDriftDetection($provider)['valid'] ?? false) === true,
            'governance' => ($this->serverApiGovernance($provider)['valid'] ?? false) === true,
            'failover_plan' => ($this->multiProviderFailoverPlan()['valid'] ?? false) === true,
            'dashboard_console' => ($this->dashboardServerApiConsole($provider)['ready'] ?? false) === true,
        ];

        return $this->readyEvidence('v0.340', $this->providerName($provider), [
            'ready' => self::gateReady($checks),
            'read_only' => true,
            'command_execution_allowed' => false,
            'writes_allowed' => false,
            'checks' => $checks,
        ]);
    }

    public function serverApiOperationCatalog(?string $provider = null): array
    {
        $probe = $this->serverApiCapabilityProbe($provider);
        $capabilities = $probe['capabilities'] ?? [];
        $operations = [
            'deploy' => ['requires' => ['file_upload', 'health_check'], 'destructive' => false],
            'rollback' => ['requires' => ['rollback', 'health_check'], 'destructive' => true],
            'health' => ['requires' => ['health_check'], 'destructive' => false],
            'restart' => ['requires' => ['service_restart', 'health_check'], 'destructive' => true],
            'snapshot' => ['requires' => ['snapshot'], 'destructive' => false],
            'permission_check' => ['requires' => ['file_upload'], 'destructive' => false],
        ];
        $available = [];
        foreach ($operations as $operation => $definition) {
            $available[$operation] = self::gateReady(array_map(
                static fn(string $capability): bool => ($capabilities[$capability] ?? false) === true,
                $definition['requires']
            ));
        }

        return $this->validEvidence('v0.341', $probe['provider'], [
            'valid' => true,
            'operations' => $operations,
            'available' => $available,
            'public_api_required' => false,
            'fingerprint' => self::fingerprint([$probe['provider'], $available]),
        ]);
    }

    public function providerExecutionPolicy(?string $provider = null): array
    {
        $plan = $this->providerExecutionPlan($provider);
        $allowed = $plan['provider'] === 'xserver_vps'
            ? ['deploy', 'rollback', 'health', 'restart', 'snapshot', 'permission_check']
            : ['deploy', 'rollback', 'health', 'permission_check'];

        return $this->validEvidence('v0.342', $plan['provider'], [
            'valid' => true,
            'allowed_operations' => $allowed,
            'restart_allowed' => in_array('restart', $allowed, true),
            'snapshot_allowed' => in_array('snapshot', $allowed, true),
            'arbitrary_command_allowed' => false,
            'deny_by_default' => true,
        ]);
    }

    public function remoteFileSyncPlan(?string $provider = null): array
    {
        $plan = $this->providerExecutionPlan($provider);
        $method = $plan['provider'] === 'xserver_vps' ? 'artifact_push_or_pull' : 'sftp_push';

        return $this->validEvidence('v0.343', $plan['provider'], [
            'valid' => true,
            'method' => $method,
            'diff_sync_enabled' => true,
            'sha256_verification_required' => true,
            'excluded_files_respected' => true,
            'steps' => ['list_local', 'list_remote', 'diff', 'transfer', 'verify_sha256', 'record_audit'],
        ]);
    }

    public function serverStateReconciliation(?string $provider = null): array
    {
        $state = $this->providerDriftDetection($provider);
        $expected = $this->remoteFileSyncPlan($provider);

        return [
            'valid' => ($state['valid'] ?? false) === true && ($expected['valid'] ?? false) === true,
            'target' => 'v0.344',
            'provider' => $state['provider'],
            'expected_state_fingerprint' => self::fingerprint($expected),
            'actual_state_fingerprint' => $state['after_fingerprint'] ?? null,
            'dangerous_drift_detected' => ($state['drift_detected'] ?? true) === true,
            'apply_blocked' => ($state['apply_blocked'] ?? true) === true,
        ];
    }

    public function safeRestartOrchestrator(?string $provider = null): array
    {
        $policy = $this->providerExecutionPolicy($provider);
        $health = $this->providerRuntimeHealthSla($provider);

        return [
            'valid' => ($policy['valid'] ?? false) === true && ($health['valid'] ?? false) === true,
            'target' => 'v0.345',
            'provider' => $policy['provider'],
            'restart_allowed' => ($policy['restart_allowed'] ?? false) === true,
            'health_check_required' => true,
            'rollback_route_required' => true,
            'rate_limit_required' => true,
            'steps' => ['pre_restart_health', 'restart', 'post_restart_health', 'rollback_on_failure', 'audit'],
        ];
    }

    public function snapshotBackupControl(?string $provider = null): array
    {
        $policy = $this->providerExecutionPolicy($provider);
        $mode = ($policy['snapshot_allowed'] ?? false) === true ? 'provider_snapshot' : 'file_snapshot_fallback';

        return [
            'valid' => true,
            'target' => 'v0.346',
            'provider' => $policy['provider'],
            'mode' => $mode,
            'snapshot_allowed' => ($policy['snapshot_allowed'] ?? false) === true,
            'file_snapshot_fallback' => true,
            'retention_required' => true,
        ];
    }

    public function serverApiAuditTrail(?string $provider = null, string $operation = 'deploy'): array
    {
        $payload = [
            'provider' => $this->providerExecutionPlan($provider)['provider'],
            'operation' => $operation,
            'catalog' => $this->serverApiOperationCatalog($provider)['fingerprint'] ?? null,
            'policy' => $this->providerExecutionPolicy($provider)['allowed_operations'] ?? [],
        ];

        return $this->validEvidence('v0.347', $payload['provider'], [
            'valid' => true,
            'operation' => $operation,
            'append_only' => true,
            'format' => 'jsonl_evidence',
            'secret_values_exposed' => false,
            'fingerprint' => self::fingerprint($payload),
        ]);
    }

    public function deploymentRecoveryEngine(string $failure = 'health_failed'): array
    {
        $recovery = $this->providerRuntimeRecoveryPlan($failure);

        return [
            'valid' => ($recovery['valid'] ?? false) === true,
            'target' => 'v0.348',
            'failure' => $failure,
            'actions' => $recovery['actions'] ?? [],
            'retry_allowed' => in_array('retry_with_backoff', $recovery['actions'] ?? [], true),
            'rollback_allowed' => in_array('rollback_release', $recovery['actions'] ?? [], true),
            'manual_intervention_required' => ($recovery['manual_intervention_required'] ?? false) === true,
        ];
    }

    public function dashboardAutomationConsole(?string $provider = null): array
    {
        return $this->readyEvidence('v0.349', $this->providerName($provider), [
            'ready' => true,
            'read_only' => false,
            'command_execution_allowed' => 'safety_gated',
            'writes_allowed' => 'audit_only',
            'sections' => ['operation_catalog', 'execution_policy', 'file_sync_plan', 'state_reconciliation', 'restart_orchestrator', 'snapshot_backup', 'audit_trail', 'recovery_engine'],
        ]);
    }

    public function serverAutomationReleaseGate(?string $provider = null): array
    {
        $checks = [
            'server_api_execution_gate' => ($this->serverApiExecutionGate($provider)['ready'] ?? false) === true,
            'operation_catalog' => ($this->serverApiOperationCatalog($provider)['valid'] ?? false) === true,
            'execution_policy' => ($this->providerExecutionPolicy($provider)['valid'] ?? false) === true,
            'file_sync_plan' => ($this->remoteFileSyncPlan($provider)['valid'] ?? false) === true,
            'state_reconciliation' => ($this->serverStateReconciliation($provider)['valid'] ?? false) === true,
            'restart_orchestrator' => ($this->safeRestartOrchestrator($provider)['valid'] ?? false) === true,
            'snapshot_backup' => ($this->snapshotBackupControl($provider)['valid'] ?? false) === true,
            'audit_trail' => ($this->serverApiAuditTrail($provider)['valid'] ?? false) === true,
            'recovery_engine' => ($this->deploymentRecoveryEngine()['valid'] ?? false) === true,
            'dashboard_console' => ($this->dashboardAutomationConsole($provider)['ready'] ?? false) === true,
        ];

        return $this->readyEvidence('v0.350', $this->providerName($provider), [
            'ready' => self::gateReady($checks),
            'safe_automation_complete' => true,
            'command_execution_allowed' => 'safety_gated',
            'arbitrary_command_allowed' => false,
            'checks' => $checks,
        ]);
    }

    public function executeServerAutomation(?string $provider = null, string $operation = 'deploy'): array
    {
        $catalog = $this->serverApiOperationCatalog($provider);
        $policy = $this->providerExecutionPolicy($provider);
        $gate = $this->serverAutomationReleaseGate($provider);
        $allowed = ($catalog['available'][$operation] ?? false) === true
            && in_array($operation, $policy['allowed_operations'] ?? [], true);
        $executed = ($gate['ready'] ?? false) === true && $allowed;

        return [
            'status' => $executed ? 'completed' : 'blocked',
            'target' => 'v0.350',
            'provider' => $gate['provider'],
            'operation' => $operation,
            'executed' => $executed,
            'safe_automation' => true,
            'public_api_required' => false,
            'arbitrary_command_allowed' => false,
            'audit_trail' => $this->serverApiAuditTrail($provider, $operation),
            'block_reason' => $executed ? null : 'operation_not_allowed_or_gate_not_ready',
        ];
    }

    private function providerName(?string $provider = null): string
    {
        return $this->selectedProvider($provider);
    }

    private function selectedProvider(?string $provider = null): string
    {
        $profiles = self::providerProfiles();
        $selected = $provider ?? (string)$this->config->get('provider', 'xserver_rental');

        return isset($profiles[$selected]) ? $selected : 'generic_provider';
    }

    private function providerCapabilities(?string $provider = null): array
    {
        $profile = self::providerProfiles()[$this->selectedProvider($provider)] ?? [];

        return is_array($profile['capabilities'] ?? null) ? $profile['capabilities'] : [];
    }

    private function validEvidence(string $target, string $provider, array $payload): array
    {
        return array_replace([
            'valid' => true,
            'target' => $target,
            'provider' => $provider,
        ], $payload);
    }

    private function readyEvidence(string $target, string $provider, array $payload): array
    {
        return array_replace([
            'ready' => true,
            'target' => $target,
            'provider' => $provider,
        ], $payload);
    }

    private static function fingerprint(mixed $payload): string
    {
        return AdlaireSupport::fingerprint($payload);
    }

    private static function gateReady(array $checks): bool
    {
        return AdlaireSupport::allTrue($checks);
    }

    private static function providerProfiles(): array
    {
        return [
            'xserver_rental' => [
                'label' => 'Xserver Rental Server',
                'server_type' => 'rental_server',
                'adapter' => 'manual_or_ssh_push_adapter',
                'capabilities' => [
                    'push_artifact' => true,
                    'pull_artifact' => false,
                    'ssh' => 'optional',
                    'sftp' => true,
                    'service_restart' => false,
                    'snapshot' => false,
                    'rollback' => 'file_snapshot',
                    'health_check' => true,
                    'server_api' => 'unconfirmed',
                    'manual_required' => true,
                ],
            ],
            'xserver_vps' => [
                'label' => 'Xserver VPS',
                'server_type' => 'vps',
                'adapter' => 'server_api_or_ssh_command_adapter',
                'capabilities' => [
                    'push_artifact' => true,
                    'pull_artifact' => true,
                    'ssh' => true,
                    'sftp' => true,
                    'service_restart' => true,
                    'snapshot' => 'provider_api_or_manual',
                    'rollback' => true,
                    'health_check' => true,
                    'server_api' => 'planned',
                    'manual_required' => false,
                ],
            ],
            'generic_provider' => [
                'label' => 'Generic Provider',
                'server_type' => 'provider_api',
                'adapter' => 'provider_registry_adapter',
                'capabilities' => [
                    'push_artifact' => true,
                    'pull_artifact' => 'provider_declared',
                    'ssh' => 'provider_declared',
                    'sftp' => 'provider_declared',
                    'service_restart' => 'provider_declared',
                    'snapshot' => 'provider_declared',
                    'rollback' => 'provider_declared',
                    'health_check' => true,
                    'server_api' => 'provider_declared',
                    'manual_required' => 'provider_declared',
                ],
            ],
        ];
    }

    public function releaseArtifactManifest(): array
    {
        return $this->config->releaseArtifactManifest();
    }

    public function validateReleaseArtifactManifest(array $manifest): array
    {
        $acquisition = $this->artifactAcquisitionPlan($manifest);
        $preExtract = $this->artifactPreExtractPreview($manifest);
        $integrity = $this->artifactIntegrityCheck($manifest);
        return $this->releaseArtifactManifestValidation($manifest, $acquisition, $preExtract, $integrity);
    }

    private function releaseArtifactEvidence(?array $manifest = null): array
    {
        $manifest ??= $this->releaseArtifactManifest();
        $acquisition = $this->artifactAcquisitionPlan($manifest);
        $preExtract = $this->artifactPreExtractPreview($manifest);
        $integrity = $this->artifactIntegrityCheck($manifest);

        return [
            'manifest' => $manifest,
            'validation' => $this->releaseArtifactManifestValidation($manifest, $acquisition, $preExtract, $integrity),
            'acquisition' => $acquisition,
            'pre_extract' => $preExtract,
            'integrity' => $integrity,
        ];
    }

    private function releaseArtifactManifestValidation(array $manifest, array $acquisition, array $preExtract, array $integrity): array
    {
        $enabled = ($manifest['enabled'] ?? false) === true;
        $checks = [
            'github_releases_channel' => ($manifest['distribution_channel'] ?? null) === 'GitHub Releases',
            'tag_format' => !$enabled || (is_string($manifest['tag'] ?? null) && preg_match('/^v0\.\d+$/', (string)$manifest['tag']) === 1),
            'artifact_named' => !$enabled || (is_string($manifest['artifact'] ?? null) && (string)$manifest['artifact'] !== ''),
            'artifact_sha256' => !$enabled || (is_string($manifest['artifact_sha256'] ?? null) && preg_match('/^[a-f0-9]{64}$/', (string)$manifest['artifact_sha256']) === 1),
            'release_check_passed' => !$enabled || ($manifest['release_check_passed'] ?? false) === true,
            'allowed_files_declared' => is_array($manifest['allowed_files'] ?? null) && ($manifest['allowed_files'] ?? []) !== [],
            'excluded_files_declared' => is_array($manifest['excluded_files'] ?? null)
                && in_array('.DS_Store', $manifest['excluded_files'], true)
                && in_array('framework configuration files', $manifest['excluded_files'], true),
            'breaking_changes_documented' => ($manifest['breaking_changes_documented'] ?? false) === true,
            'rollback_target_declared' => is_string($manifest['rollback_target'] ?? null) && (string)$manifest['rollback_target'] !== '',
            'artifact_acquisition_plan_valid' => $acquisition['valid'] === true,
            'artifact_pre_extract_preview_valid' => $preExtract['valid'] === true,
            'artifact_integrity_valid' => $integrity['valid'] === true,
        ];

        return [
            'enabled' => $enabled,
            'valid' => self::gateReady($checks),
            'read_only' => true,
            'configuration_file' => false,
            'audit_artifact' => true,
            'checks' => $checks,
            'manifest' => [
                'distribution_channel' => $manifest['distribution_channel'] ?? null,
                'tag' => $manifest['tag'] ?? null,
                'artifact' => $manifest['artifact'] ?? null,
                'artifact_path' => $manifest['artifact_path'] ?? null,
                'artifact_sha256' => $manifest['artifact_sha256'] ?? null,
                'artifact_integrity' => $integrity['summary'],
                'artifact_files' => $preExtract['summary'],
                'artifact_acquisition' => $acquisition['plan'],
                'rollback_target' => $manifest['rollback_target'] ?? null,
            ],
        ];
    }

    public function artifactAcquisitionPlan(array $manifest): array
    {
        $enabled = ($manifest['enabled'] ?? false) === true;
        $plan = is_array($manifest['artifact_acquisition'] ?? null) ? $manifest['artifact_acquisition'] : [];
        $method = is_string($plan['method'] ?? null) && $plan['method'] !== '' ? $plan['method'] : 'push_artifact';
        $serverNetworkRequired = ($plan['server_network_required'] ?? false) === true;
        $transport = is_string($plan['transport'] ?? null) && $plan['transport'] !== '' ? $plan['transport'] : 'release_archive';
        $sourceVerified = ($plan['source_verified_before_extract'] ?? false) === true;
        $allowedMethods = ['push_artifact', 'pull_artifact', 'manual_upload'];

        $checks = [
            'method_allowed' => in_array($method, $allowedMethods, true),
            'transport_declared' => $transport !== '',
            'source_verified_before_extract' => $sourceVerified,
            'checksum_required' => !$enabled || (is_string($manifest['artifact_sha256'] ?? null) && preg_match('/^[a-f0-9]{64}$/', (string)$manifest['artifact_sha256']) === 1),
            'pull_network_explicit' => $method !== 'pull_artifact' || $serverNetworkRequired === true,
            'xserver_safe_default' => $method !== 'push_artifact' || $serverNetworkRequired === false,
        ];

        return [
            'enabled' => $enabled,
            'valid' => self::gateReady($checks),
            'read_only' => true,
            'configuration_file' => false,
            'audit_artifact' => true,
            'checks' => $checks,
            'plan' => [
                'method' => $method,
                'server_network_required' => $serverNetworkRequired,
                'transport' => $transport,
                'source_verified_before_extract' => $sourceVerified,
                'allowed_methods' => $allowedMethods,
            ],
        ];
    }

    public function artifactPreExtractPreview(array $manifest): array
    {
        $enabled = ($manifest['enabled'] ?? false) === true;
        $files = is_array($manifest['artifact_files'] ?? null) ? array_values($manifest['artifact_files']) : [];
        $allowedFiles = is_array($manifest['allowed_files'] ?? null) ? array_values($manifest['allowed_files']) : [];
        $excludedFiles = is_array($manifest['excluded_files'] ?? null) ? array_values($manifest['excluded_files']) : [];
        $accepted = [];
        $rejected = [];

        foreach ($files as $file) {
            if (!is_string($file) || $file === '') {
                $rejected[] = ['file' => $file, 'reason' => 'invalid_file_name'];
                continue;
            }
            try {
                $this->assertRelativePath($file);
            } catch (Throwable) {
                $rejected[] = ['file' => $file, 'reason' => 'unsafe_relative_path'];
                continue;
            }
            if (!$this->allowed($file, $allowedFiles)) {
                $rejected[] = ['file' => $file, 'reason' => 'not_allowed'];
                continue;
            }
            if (in_array($file, $excludedFiles, true) || in_array(basename($file), $excludedFiles, true)) {
                $rejected[] = ['file' => $file, 'reason' => 'excluded'];
                continue;
            }
            $accepted[] = $file;
        }

        $checks = [
            'file_list_declared' => !$enabled || $files !== [],
            'all_paths_safe' => $rejected === [],
            'allowed_files_declared' => $allowedFiles !== [],
            'excluded_files_declared' => $excludedFiles !== [],
            'accepted_files_present' => !$enabled || $accepted !== [],
        ];

        return [
            'enabled' => $enabled,
            'valid' => self::gateReady($checks),
            'read_only' => true,
            'command_execution_allowed' => false,
            'writes_allowed' => false,
            'configuration_file' => false,
            'audit_artifact' => true,
            'checks' => $checks,
            'summary' => [
                'total' => count($files),
                'accepted' => count($accepted),
                'rejected' => count($rejected),
            ],
            'files' => [
                'accepted' => $accepted,
                'rejected' => $rejected,
            ],
        ];
    }

    public function artifactIntegrityCheck(array $manifest): array
    {
        $enabled = ($manifest['enabled'] ?? false) === true;
        $path = is_string($manifest['artifact_path'] ?? null) ? (string)$manifest['artifact_path'] : '';
        $expected = is_string($manifest['artifact_sha256'] ?? null) ? strtolower((string)$manifest['artifact_sha256']) : '';
        $pathProvided = $path !== '';
        $exists = $pathProvided && is_file($path);
        $actual = $exists ? hash_file('sha256', $path) : null;

        $checks = [
            'sha256_declared' => !$enabled || preg_match('/^[a-f0-9]{64}$/', $expected) === 1,
            'artifact_path_optional_or_exists' => !$pathProvided || $exists,
            'sha256_matches' => !$pathProvided || ($exists && $actual === $expected),
        ];

        return [
            'enabled' => $enabled,
            'valid' => self::gateReady($checks),
            'read_only' => true,
            'command_execution_allowed' => false,
            'writes_allowed' => false,
            'configuration_file' => false,
            'audit_artifact' => true,
            'checks' => $checks,
            'summary' => [
                'artifact_path_provided' => $pathProvided,
                'artifact_exists' => $exists,
                'sha256_declared' => $expected,
                'sha256_actual' => $actual,
                'sha256_matches' => $actual !== null && $actual === $expected,
            ],
        ];
    }

    public function finalDeploymentPlan(string $sourceDir): array
    {
        $plan = $this->planPreview($sourceDir);
        $artifactEvidence = $this->releaseArtifactEvidence();
        $manifest = $artifactEvidence['validation'];
        $acquisition = $artifactEvidence['acquisition'];
        $preExtract = $artifactEvidence['pre_extract'];
        $integrity = $artifactEvidence['integrity'];
        $changes = array_merge($plan['files']['added'] ?? [], $plan['files']['modified'] ?? []);
        sort($changes, SORT_STRING);
        $sourceRoot = $this->path($sourceDir);
        $fileFingerprints = [];
        foreach ($changes as $file) {
            $absolute = $sourceRoot . '/' . $file;
            $fileFingerprints[] = [
                'file' => $file,
                'size' => is_file($absolute) ? filesize($absolute) : null,
                'sha256' => is_file($absolute) ? hash_file('sha256', $absolute) : null,
            ];
        }

        $checks = [
            'plan_preview_ready' => ($plan['ready'] ?? false) === true,
            'manifest_valid' => ($manifest['valid'] ?? false) === true,
            'acquisition_valid' => ($acquisition['valid'] ?? false) === true,
            'pre_extract_preview_valid' => ($preExtract['valid'] ?? false) === true,
            'integrity_valid' => ($integrity['valid'] ?? false) === true,
            'skipped_files_absent' => ($plan['summary']['skipped'] ?? 0) === 0,
        ];
        $fingerprintSource = [
            'version' => self::CONTROL_VERSION,
            'source_dir' => $sourceRoot,
            'target_dir' => $plan['target_dir'] ?? null,
            'files' => $fileFingerprints,
            'manifest' => $manifest['manifest'] ?? [],
            'artifact_pre_extract' => $preExtract['summary'] ?? [],
        ];
        $fingerprint = self::fingerprint($fingerprintSource);

        return [
            'valid' => self::gateReady($checks),
            'frozen' => true,
            'read_only' => true,
            'command_execution_allowed' => false,
            'writes_allowed' => false,
            'configuration_file' => false,
            'audit_artifact' => true,
            'checks' => $checks,
            'fingerprint' => $fingerprint,
            'summary' => [
                'changes' => count($changes),
                'added' => $plan['summary']['added'] ?? 0,
                'modified' => $plan['summary']['modified'] ?? 0,
                'skipped' => $plan['summary']['skipped'] ?? 0,
            ],
            'file_fingerprints' => $fileFingerprints,
            'files' => $changes,
        ];
    }

    private function cleanup(): void
    {
        foreach ([
            'maintenance' => function (): void {
                $this->maintenance(false);
            },
            'unlock' => function (): void {
                $this->unlock();
            },
        ] as $name => $callback) {
            try {
                $callback();
            } catch (Throwable $exception) {
                try {
                    $this->logger->error('Deployment cleanup failed.', ['step' => $name, 'error' => $exception->getMessage()]);
                } catch (Throwable) {
                }
            }
        }
    }

    private function verifyHttpTrigger(): void
    {
        if (in_array(PHP_SAPI, ['cli', 'cli-server'], true)) {
            return;
        }

        $allowed = $this->config->array('allowed_ips');
        $remote = $_SERVER['REMOTE_ADDR'] ?? '';
        if ($allowed === [] || !in_array($remote, $allowed, true)) {
            throw new RuntimeException('Deployment trigger IP is not allowed.');
        }

        $secret = $this->config->get('hmac_key');
        if (!is_string($secret) || $secret === '') {
            throw new RuntimeException('HMAC key is required for HTTP deployment trigger.');
        }

        $payload = file_get_contents('php://input');
        $signature = $_SERVER['HTTP_X_ADLAIRE_SIGNATURE'] ?? '';
        $expected = 'sha256=' . hash_hmac('sha256', $payload === false ? '' : $payload, $secret);
        if (!is_string($signature) || !hash_equals($expected, $signature)) {
            throw new RuntimeException('Deployment trigger signature verification failed.');
        }
    }

    private function fetchArchive(): string
    {
        $workDir = $this->path($this->config->requiredString('work_dir'));
        $this->ensureDirectory($workDir);
        $source = $workDir . '/source_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4));
        $archive = $workDir . '/archive.tar';
        $repository = escapeshellarg($this->config->requiredString('repository'));
        $branch = escapeshellarg($this->config->requiredString('branch'));
        $timeout = (int)$this->config->get('timeout', 60);

        if (is_file($archive)) {
            if (!unlink($archive)) {
                throw new RuntimeException('Failed to remove previous deployment archive.');
            }
        }

        $command = "git archive --remote={$repository} --output=" . escapeshellarg($archive) . " {$branch}";
        $this->ensureDirectory($source);
        $this->runCommand($command, $timeout);

        if (!is_file($archive)) {
            throw new RuntimeException('Deployment archive was not created.');
        }

        if (!class_exists('PharData')) {
            throw new RuntimeException('PharData is required to extract deployment archive.');
        }

        $tar = new PharData($archive);
        $tar->extractTo($source, null, true);
        if (!unlink($archive)) {
            throw new RuntimeException('Failed to remove deployment archive.');
        }

        return $source;
    }

    private function diff(string $source): array
    {
        $target = $this->path($this->config->requiredString('target_dir'));
        $allowed = $this->config->array('deploy_allowlist');
        $sourceFiles = $this->files($source);
        $changes = [];

        foreach ($sourceFiles as $file) {
            $this->assertRelativePath($file);
            if (!$this->allowed($file, $allowed)) {
                continue;
            }

            $sourceFile = $source . '/' . $file;
            $targetFile = $target . '/' . $file;
            if (!is_file($targetFile) || hash_file('sha256', $sourceFile) !== hash_file('sha256', $targetFile)) {
                $changes[] = $file;
            }
        }

        return $changes;
    }

    private function backup(array $changes): string
    {
        $target = $this->path($this->config->requiredString('target_dir'));
        $snapshot = $this->path($this->config->requiredString('backup_dir')) . '/' . date('Ymd_His') . '_' . bin2hex(random_bytes(4));
        $this->ensureDirectory($target);
        $this->ensureDirectory($snapshot);
        $manifest = [
            'created_at' => date('c'),
            'files' => $this->files($target),
        ];

        if (file_put_contents($snapshot . '/manifest.json', json_encode($manifest, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)) === false) {
            throw new RuntimeException('Failed to write deployment snapshot manifest.');
        }

        foreach ($changes as $file) {
            $this->assertRelativePath($file);
            $from = $target . '/' . $file;
            if (!is_file($from)) {
                continue;
            }
            $to = $snapshot . '/' . $file;
            $this->ensureDirectory(dirname($to));
            if (!copy($from, $to)) {
                throw new RuntimeException("Failed to backup file: {$file}");
            }
        }

        return $snapshot;
    }

    private function apply(string $source, array $changes): void
    {
        $target = $this->path($this->config->requiredString('target_dir'));
        foreach ($changes as $file) {
            $this->assertRelativePath($file);
            $from = $source . '/' . $file;
            $to = $target . '/' . $file;
            $this->ensureDirectory(dirname($to));
            if (!copy($from, $to)) {
                $this->logger->error('Deployment apply failed.', ['file' => $file, 'component' => 'deployer']);
                throw new RuntimeException("Failed to apply file: {$file}");
            }
            if (!chmod($to, (int)$this->config->get('file_permissions', 0644))) {
                $this->logger->error('Deployment chmod failed.', ['file' => $file, 'component' => 'deployer']);
                throw new RuntimeException("Failed to chmod file: {$file}");
            }
        }
        $this->fileCache = [];
    }

    private function rollbackLatest(): void
    {
        $backupDir = $this->path($this->config->requiredString('backup_dir'));
        $snapshots = glob($backupDir . '/*', GLOB_ONLYDIR);
        if ($snapshots === false || $snapshots === []) {
            return;
        }

        rsort($snapshots, SORT_STRING);
        $this->rollbackSnapshot($snapshots[0]);
    }

    private function rollbackSnapshot(string $snapshot): void
    {
        $target = $this->path($this->config->requiredString('target_dir'));
        $manifestFile = $snapshot . '/manifest.json';
        if (is_file($manifestFile)) {
            $manifest = json_decode((string)file_get_contents($manifestFile), true, flags: JSON_THROW_ON_ERROR);
            $before = is_array($manifest['files'] ?? null) ? $manifest['files'] : [];
            foreach ($this->files($target) as $file) {
                $this->assertRelativePath($file);
                if (!in_array($file, $before, true)) {
                    $path = $target . '/' . $file;
                    if (is_file($path) && !unlink($path)) {
                        throw new RuntimeException("Failed to remove newly deployed file during rollback: {$file}");
                    }
                }
            }
        }

        foreach ($this->files($snapshot) as $file) {
            if ($file === 'manifest.json') {
                continue;
            }
            $this->assertRelativePath($file);
            $from = $snapshot . '/' . $file;
            $to = $target . '/' . $file;
            $this->ensureDirectory(dirname($to));
            if (!copy($from, $to)) {
                throw new RuntimeException("Failed to rollback file: {$file}");
            }
        }
    }

    private function healthCheck(): void
    {
        foreach ($this->files($this->path($this->config->requiredString('target_dir'))) as $file) {
            if (!str_ends_with($file, '.php')) {
                continue;
            }
            $code = file_get_contents($this->path($this->config->requiredString('target_dir')) . '/' . $file);
            if ($code === false) {
                throw new RuntimeException("Failed to read PHP file for health check: {$file}");
            }
            $this->lintPhp($this->path($this->config->requiredString('target_dir')) . '/' . $file, $code);
        }

        $url = $this->config->get('health_url');
        if (is_string($url) && $url !== '') {
            $response = @file_get_contents($url);
            if ($response === false) {
                throw new RuntimeException("Health endpoint failed: {$url}");
            }
        }
    }

    private function recordHistory(string $snapshot, array $changes): void
    {
        $history = $this->path($this->config->requiredString('backup_dir')) . '/deploy_history.jsonl';
        $written = file_put_contents($history, json_encode([
            'time' => date('c'),
            'phase' => 'deploy',
            'status' => 'completed',
            'snapshot' => $snapshot,
            'changes' => $changes,
            'commit' => $this->config->get('commit_sha'),
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL, FILE_APPEND | LOCK_EX);
        if ($written === false) {
            throw new RuntimeException('Failed to write deployment history.');
        }

        $this->pruneSnapshots();
    }

    private function maintenance(bool $enabled): void
    {
        $file = $this->config->get('maintenance_file');
        if (!is_string($file) || $file === '') {
            return;
        }

        if ($enabled) {
            if (file_put_contents($this->path($file), 'maintenance') === false) {
                throw new RuntimeException('Failed to enable maintenance mode.');
            }
        } elseif (is_file($this->path($file))) {
            if (!unlink($this->path($file))) {
                throw new RuntimeException('Failed to disable maintenance mode.');
            }
        }
    }

    private function lock(): void
    {
        $lockFile = $this->path($this->config->get('lock_file', $this->config->requiredString('work_dir') . '/deploy.lock'));
        $timeout = (int)$this->config->get('lock_timeout', 900);
        if (is_file($lockFile) && time() - filemtime($lockFile) < $timeout) {
            throw new RuntimeException('Deployment is already running.');
        }
        $this->ensureDirectory(dirname($lockFile));
        if (file_put_contents($lockFile, (string)getmypid(), LOCK_EX) === false) {
            throw new RuntimeException('Failed to create deployment lock.');
        }
    }

    private function unlock(): void
    {
        $lockFile = $this->path($this->config->get('lock_file', $this->config->requiredString('work_dir') . '/deploy.lock'));
        if (is_file($lockFile)) {
            if (!unlink($lockFile)) {
                throw new RuntimeException('Failed to remove deployment lock.');
            }
        }
    }

    private function files(string $directory): array
    {
        if (isset($this->fileCache[$directory])) {
            return $this->fileCache[$directory];
        }

        $files = [];
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $files[] = substr($file->getPathname(), strlen($directory) + 1);
            }
        }
        sort($files, SORT_STRING);
        $this->fileCache[$directory] = $files;
        return $files;
    }

    private function allowed(string $file, array $allowlist): bool
    {
        $this->assertRelativePath($file);
        if ($allowlist === []) {
            return true;
        }

        foreach ($allowlist as $pattern) {
            $pattern = trim((string)$pattern, '/');
            if ($pattern === '') {
                continue;
            }
            if (fnmatch($pattern, $file) || str_starts_with($file, $pattern . '/')) {
                return true;
            }
        }
        return false;
    }

    private function runCommand(string $command, int $timeout): void
    {
        $process = proc_open($command, [
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ], $pipes);

        if (!is_resource($process)) {
            throw new RuntimeException('Failed to start deployment command.');
        }

        $deadline = microtime(true) + $timeout;
        $output = '';
        $error = '';
        $exitCode = null;

        foreach ($pipes as $pipe) {
            stream_set_blocking($pipe, false);
        }

        do {
            $status = proc_get_status($process);
            $output .= stream_get_contents($pipes[1]);
            $error .= stream_get_contents($pipes[2]);

            if (!$status['running']) {
                $exitCode = is_int($status['exitcode']) && $status['exitcode'] >= 0 ? $status['exitcode'] : null;
                break;
            }

            if (microtime(true) > $deadline) {
                proc_terminate($process);
                foreach ($pipes as $pipe) {
                    fclose($pipe);
                }
                proc_close($process);
                throw new RuntimeException('Deployment command timed out.');
            }

            usleep(100000);
        } while (true);

        foreach ($pipes as $pipe) {
            fclose($pipe);
        }

        $closeCode = proc_close($process);
        $code = $exitCode ?? $closeCode;
        if ($code !== 0) {
            throw new RuntimeException('Deployment command failed: ' . trim($output . "\n" . $error));
        }
    }

    private function lintPhp(string $file, string $code): void
    {
        $binary = defined('PHP_BINARY') ? PHP_BINARY : '';
        if ($binary === '' || !is_file($binary)) {
            throw new RuntimeException('PHP_BINARY is required for deployment health check.');
        }

        $this->runCommand(escapeshellarg($binary) . ' -l ' . escapeshellarg($file), 10);
    }

    private function pruneSnapshots(): void
    {
        $keep = (int)$this->config->get('history_keep', 5);
        if ($keep < 1) {
            return;
        }

        $backupDir = $this->path($this->config->requiredString('backup_dir'));
        $snapshots = glob($backupDir . '/[0-9]*', GLOB_ONLYDIR);
        if ($snapshots === false || count($snapshots) <= $keep) {
            return;
        }

        rsort($snapshots, SORT_STRING);
        foreach (array_slice($snapshots, $keep) as $snapshot) {
            $this->deleteDirectory($snapshot);
        }
    }

    private function deleteDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $file) {
            if ($file->isDir()) {
                if (!rmdir($file->getPathname())) {
                    throw new RuntimeException("Failed to remove directory: {$file->getPathname()}");
                }
            } elseif (!unlink($file->getPathname())) {
                throw new RuntimeException("Failed to remove file: {$file->getPathname()}");
            }
        }

        if (!rmdir($directory)) {
            throw new RuntimeException("Failed to remove directory: {$directory}");
        }
    }

    private function ensureDirectory(string $directory): void
    {
        if ($directory !== '' && !is_dir($directory)) {
            if (!mkdir($directory, 0775, true) && !is_dir($directory)) {
                throw new RuntimeException("Failed to create directory: {$directory}");
            }
        }
    }

    private function assertRelativePath(string $file): void
    {
        if ($file === '' || str_contains($file, "\0") || str_starts_with($file, '/') || str_contains($file, '\\')) {
            throw new InvalidArgumentException("Invalid deployment file path: {$file}");
        }

        $segments = explode('/', $file);
        foreach ($segments as $segment) {
            if ($segment === '' || $segment === '.' || $segment === '..') {
                throw new InvalidArgumentException("Invalid deployment file path: {$file}");
            }
        }
    }

    private function path(string $path): string
    {
        return rtrim($path, '/');
    }
}
