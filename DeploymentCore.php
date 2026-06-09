<?php

/**
 * Adlaire Ecosystem - DeploymentCore.php
 *
 * @version v0.202
 * @php     >= 8.3
 */

declare(strict_types=1);

require_once __DIR__ . '/FrameworkCore/Logger.php';

if (PHP_VERSION_ID < 80300) {
    echo json_encode(['error' => 'Adlaire Ecosystem requires PHP 8.3 or higher. Current version: ' . PHP_VERSION]);
    exit(1);
}

final class DeployConfig
{
    public static function fromArray(array $config): self
    {
        return new self($config);
    }

    public function __construct(private array $values)
    {
        $this->values = $this->withEnvironment($values);
        $this->validate();
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $envValue = getenv('ADLAIRE_' . strtoupper($key));
        if ($envValue !== false) {
            return $envValue;
        }
        return $this->values[$key] ?? $default;
    }

    public function requiredString(string $key): string
    {
        $value = $this->get($key);
        if (!is_string($value) || $value === '') {
            throw new InvalidArgumentException("Missing required config: {$key}");
        }
        return $value;
    }

    public function array(string $key): array
    {
        $value = $this->get($key, []);
        if (is_string($value) && $value !== '') {
            $decoded = json_decode($value, true);
            if (is_array($decoded)) {
                return $decoded;
            }
            return array_values(array_filter(array_map('trim', explode(',', $value)), static fn(string $item): bool => $item !== ''));
        }
        if (!is_array($value)) {
            throw new InvalidArgumentException("Config must be an array: {$key}");
        }
        return $value;
    }

    public function bool(string $key, bool $default = false): bool
    {
        $value = $this->get($key, $default);
        if (is_bool($value)) {
            return $value;
        }
        if (is_string($value)) {
            $normalized = strtolower(trim($value));
            if (in_array($normalized, ['1', 'true', 'yes', 'on'], true)) {
                return true;
            }
            if (in_array($normalized, ['0', 'false', 'no', 'off'], true)) {
                return false;
            }
        }
        return (bool)$value;
    }

    public function deploymentManifest(): array
    {
        $integrationModules = $this->array('integration_modules');
        $aurisConsidered = $this->bool('auris_integration', false) || in_array('Auris', $integrationModules, true);

        return [
            'axis' => 'deployment system',
            'repository' => $this->requiredString('repository'),
            'branch' => $this->requiredString('branch'),
            'target_dir' => $this->requiredString('target_dir'),
            'work_dir' => $this->requiredString('work_dir'),
            'backup_dir' => $this->requiredString('backup_dir'),
            'log_file' => $this->requiredString('log_file'),
            'deploy_allowlist' => $this->array('deploy_allowlist'),
            'integration_modules' => $integrationModules,
            'auris_integration_considered' => $aurisConsidered,
            'autonomous_operation' => true,
            'architecture_changed' => false,
        ];
    }

    private function withEnvironment(array $values): array
    {
        foreach ($values as $key => $value) {
            $envKey = 'ADLAIRE_' . strtoupper($key);
            $envValue = getenv($envKey);
            if ($envValue !== false) {
                $values[$key] = $envValue;
            }
        }
        return $values;
    }

    private function validate(): void
    {
        foreach (['repository', 'branch', 'target_dir', 'work_dir', 'backup_dir', 'log_file'] as $key) {
            $this->requiredString($key);
        }

        if (!function_exists('exec')) {
            throw new RuntimeException('exec() is required for deployment.');
        }

        if (filter_var(ini_get('phar.readonly'), FILTER_VALIDATE_BOOLEAN)) {
            throw new RuntimeException('phar.readonly must be Off for deployment archive handling.');
        }

        $sshKey = $this->get('ssh_key');
        if (is_string($sshKey) && $sshKey !== '') {
            if (!is_file($sshKey)) {
                throw new InvalidArgumentException("SSH key not found: {$sshKey}");
            }
            $perms = substr(sprintf('%o', fileperms($sshKey)), -3);
            if ($perms !== '600') {
                throw new InvalidArgumentException("SSH key permissions must be 600: {$sshKey}");
            }
        }
    }
}

final class Deployer
{
    private const CONTROL_VERSION = 'v0.263';

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

        return [
            'component' => 'DeploymentCore.php',
            'axis' => 'deployment system',
            'design_philosophy' => 'distributed autonomous system design philosophy',
            'architecture_changed' => false,
            'auris_integration_considered' => $config['auris_integration_considered'],
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
            'auris_integration_considered' => ($manifest['auris_integration_considered'] ?? false) === true,
            'architecture_unchanged' => ($manifest['architecture_changed'] ?? true) === false,
            'target_dir_ready' => is_dir($manifest['required_directories']['target_dir'] ?? ''),
            'work_dir_ready' => is_dir($manifest['required_directories']['work_dir'] ?? ''),
            'backup_dir_ready' => is_dir($manifest['required_directories']['backup_dir'] ?? ''),
        ];

        return [
            'ready' => !in_array(false, $checks, true),
            'checks' => $checks,
            'validation' => $validation,
            'manifest' => $manifest,
        ];
    }

    public function preflight(): array
    {
        $manifest = $this->deploymentSystemManifest();
        $targetDir = $manifest['required_directories']['target_dir'];
        $workDir = $manifest['required_directories']['work_dir'];
        $backupDir = $manifest['required_directories']['backup_dir'];
        $logDir = dirname($this->config->requiredString('log_file'));
        $lockFile = $this->path($this->config->get('lock_file', $this->config->requiredString('work_dir') . '/deploy.lock'));
        $lockTimeout = (int)$this->config->get('lock_timeout', 900);
        $allowlist = $this->config->array('deploy_allowlist');

        $checks = [
            'deployment_core_compatible' => ($manifest['component'] ?? null) === 'DeploymentCore.php'
                && ($manifest['axis'] ?? null) === 'deployment system'
                && ($manifest['architecture_changed'] ?? true) === false,
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

        return [
            'ready' => !in_array(false, $checks, true),
            'checks' => $checks,
            'component' => 'DeploymentCore.php',
            'compatibility_guaranteed' => true,
            'breaking_changes_allowed' => false,
            'dry_run_available' => true,
            'required_directories' => $manifest['required_directories'],
            'deploy_allowlist' => $allowlist,
            'lock_file' => $lockFile,
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
            'component' => 'DeploymentCore.php',
            'deployment_core_change_detected' => in_array('DeploymentCore.php', $changed, true),
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

    public function compatibilitySnapshot(?string $sourceDir = null): array
    {
        $preflight = $this->preflight();
        $manifest = $this->deploymentSystemManifest();
        $plan = $sourceDir === null ? null : $this->planPreview($sourceDir);
        $deploymentCoreChangeDetected = $plan === null
            ? false
            : ($plan['deployment_core_change_detected'] ?? false) === true;

        $checks = [
            'deployment_core_component' => ($manifest['component'] ?? null) === 'DeploymentCore.php',
            'deployment_axis_retained' => ($manifest['axis'] ?? null) === 'deployment system',
            'architecture_unchanged' => ($manifest['architecture_changed'] ?? true) === false,
            'preflight_ready' => ($preflight['ready'] ?? false) === true,
            'breaking_changes_forbidden' => true,
            'plan_preview_read_only' => $plan === null || (($plan['read_only'] ?? false) === true
                && ($plan['command_execution_allowed'] ?? true) === false
                && ($plan['writes_allowed'] ?? true) === false),
        ];

        return [
            'ready' => !in_array(false, $checks, true),
            'read_only' => true,
            'command_execution_allowed' => false,
            'writes_allowed' => false,
            'component' => 'DeploymentCore.php',
            'compatibility_guaranteed' => true,
            'breaking_changes_allowed' => false,
            'deployment_core_change_detected' => $deploymentCoreChangeDetected,
            'checks' => $checks,
            'manifest' => [
                'component' => $manifest['component'],
                'axis' => $manifest['axis'],
                'architecture_changed' => $manifest['architecture_changed'],
                'design_philosophy' => $manifest['design_philosophy'],
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
                'component' => 'DeploymentCore.php',
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
            'component' => 'DeploymentCore.php',
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
        $snapshot = $this->compatibilitySnapshot($sourceDir);
        $rollback = $this->rollbackPreview();
        $score = 100;
        $deductions = [];

        if (($snapshot['ready'] ?? false) !== true) {
            $score -= 40;
            $deductions['compatibility_snapshot'] = 40;
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

        $score = max(0, $score);

        return [
            'ready' => $score >= 70,
            'score' => $score,
            'grade' => $score >= 90 ? 'safe' : ($score >= 70 ? 'review' : 'blocked'),
            'read_only' => true,
            'command_execution_allowed' => false,
            'writes_allowed' => false,
            'component' => 'DeploymentCore.php',
            'deductions' => $deductions,
            'compatibility_snapshot_ready' => ($snapshot['ready'] ?? false) === true,
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
        return [
            'version' => self::CONTROL_VERSION,
            'read_only' => true,
            'command_execution_allowed' => false,
            'writes_allowed' => false,
            'component' => 'DeploymentCore.php',
            'preflight' => $this->preflight(),
            'plan_preview' => $sourceDir === null ? null : $this->planPreview($sourceDir),
            'compatibility_snapshot' => $this->compatibilitySnapshot($sourceDir),
            'rollback_preview' => $this->rollbackPreview(),
            'safety_score' => $this->deploymentSafetyScore($sourceDir),
            'safety_score_details' => $this->deploymentSafetyScoreDetails($sourceDir),
            'history' => $this->deploymentHistorySummary(),
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
        foreach (['preflight', 'compatibility_snapshot', 'rollback_preview', 'safety_score'] as $section) {
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
                    'compatibility_snapshot_ready' => $report['compatibility_snapshot']['ready'] ?? false,
                    'rollback_preview_ready' => $report['rollback_preview']['ready'] ?? false,
                    'deployment_safety_score' => $report['safety_score']['score'] ?? 0,
                ],
            ],
        ];
    }

    public function stableReleaseCandidateGate(?string $sourceDir = null): array
    {
        $bundle = $this->releaseEvidenceBundle($sourceDir);
        $inputs = $bundle['evidence']['release_gate_inputs'];
        $ready = ($inputs['compatibility_snapshot_ready'] ?? false) === true
            && ($inputs['rollback_preview_ready'] ?? false) === true
            && ($inputs['deployment_safety_score'] ?? 0) >= 70;

        return [
            'rc_ready' => $ready,
            'grade' => $ready ? 'release-candidate' : 'blocked',
            'read_only' => true,
            'command_execution_allowed' => false,
            'writes_allowed' => false,
            'inputs' => $inputs,
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
            if (fnmatch((string)$pattern, $file)) {
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
