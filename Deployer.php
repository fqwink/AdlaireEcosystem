<?php

/**
 * Adlaire Ecosystem - Deployer.php
 *
 * @version v0.50
 * @php     >= 8.3
 */

declare(strict_types=1);

require_once __DIR__ . '/Logger.php';

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
