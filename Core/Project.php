<?php

declare(strict_types=1);

require_once __DIR__ . '/Deployment.php';
require_once __DIR__ . '/Database.php';

final class AdlaireProject
{
    public const NAME = 'Adlaire Ecosystem';
    public const VERSION = 'v0.001';

    public static function manifest(): array
    {
        return [
            'name' => self::NAME,
            'version' => self::VERSION,
            'type' => 'BaaS Project',
            'zero_base_restart' => true,
            'compatibility' => false,
            'core_scope' => [
                'deployment',
                'realtime_database',
            ],
            'undefined_scope' => ['authentication', 'authorization', 'other_baas_features'],
            'allowed_directories' => ['Core', 'Applications', 'docs', 'tests'],
            'current_scope_only' => true,
        ];
    }

    public static function readiness(): array
    {
        $checks = [
            'project_identity' => self::NAME === 'Adlaire Ecosystem' && self::VERSION === 'v0.001',
            'deployment' => AdlaireDeployment::readiness()['ready'] === true,
            'database' => AdlaireDatabase::readiness()['ready'] === true,
            'current_scope_only' => self::manifest()['current_scope_only'] === true,
        ];

        return [
            'ready' => self::all($checks),
            'version' => self::VERSION,
            'checks' => $checks,
            'fingerprint' => self::fingerprint($checks),
        ];
    }

    public static function release(): array
    {
        $readiness = self::readiness();

        return [
            'version' => self::VERSION,
            'release_ready' => $readiness['ready'],
            'readiness' => $readiness,
            'deployment_gate' => AdlaireDeployment::releaseGate(),
            'fingerprint' => self::fingerprint([
                self::manifest(),
                $readiness,
                AdlaireDeployment::releaseGate(),
            ]),
        ];
    }

    public static function all(array $checks): bool
    {
        foreach ($checks as $passed) {
            if ($passed !== true) {
                return false;
            }
        }

        return true;
    }

    public static function fingerprint(array $payload): string
    {
        ksort($payload);
        return hash('sha256', json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }
}
