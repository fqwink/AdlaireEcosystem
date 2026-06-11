<?php

/**
 * Adlaire Ecosystem - Deployment DeployConfig
 *
 * @version v0.277
 * @php     >= 8.3
 */

declare(strict_types=1);

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
        return [
            'axis' => 'deployment system',
            'repository' => $this->requiredString('repository'),
            'branch' => $this->requiredString('branch'),
            'target_dir' => $this->requiredString('target_dir'),
            'work_dir' => $this->requiredString('work_dir'),
            'backup_dir' => $this->requiredString('backup_dir'),
            'log_file' => $this->requiredString('log_file'),
            'deploy_allowlist' => $this->array('deploy_allowlist'),
            'application_boundary' => 'Applications',
            'application_dependency_allowed' => false,
            'legacy_modules_directory_allowed' => false,
            'autonomous_operation' => true,
            'architecture_changed' => true,
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
