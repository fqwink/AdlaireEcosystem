<?php

declare(strict_types=1);

require_once __DIR__ . '/../Runtime/Runtime.php';
require_once __DIR__ . '/../Database/Database.php';

final class AdlaireDeployment
{
    public const NAME = 'Adlaire Ecosystem';
    public const VERSION = 'v0.002';

    public static function manifest(): array
    {
        return [
            'name' => self::NAME,
            'version' => self::VERSION,
            'type' => 'BaaS Project',
            'zero_base_restart' => true,
            'compatibility' => false,
            'core_scope' => [
                'deployment_system',
                'realtime_database',
            ],
            'undefined_scope' => ['authentication', 'authorization', 'other_baas_features'],
            'allowed_directories' => ['Core', 'Applications', 'Docker', 'docs', 'tests'],
            'current_scope_only' => true,
            'project_boundary' => 'integrated_into_deployment_system',
        ];
    }

    public static function state(): array
    {
        return [
            'system' => 'deployment_system',
            'version' => self::VERSION,
            'state' => 'blank',
            'policy' => 'reset_from_basic_policy',
            'execution' => 'none',
            'release_ready' => false,
            'reason' => 'deployment_system_policy_reset',
        ];
    }

    public static function releaseGate(): array
    {
        $checks = [
            'state_blank' => self::state()['state'] === 'blank',
            'execution_none' => self::state()['execution'] === 'none',
            'release_not_ready' => self::state()['release_ready'] === false,
            'policy_reset' => self::state()['policy'] === 'reset_from_basic_policy',
        ];

        return [
            'ready' => false,
            'checks' => $checks,
            'state' => self::state(),
            'blocking_reason' => 'deployment_system_policy_reset',
        ];
    }

    public static function readiness(): array
    {
        $checks = [
            'deployment_identity' => self::NAME === 'Adlaire Ecosystem' && self::VERSION === 'v0.002',
            'state_blank' => self::state()['state'] === 'blank',
            'execution_none' => self::state()['execution'] === 'none',
            'release_not_ready' => self::state()['release_ready'] === false,
            'database' => AdlaireDatabase::readiness()['ready'] === true,
            'current_scope_only' => self::manifest()['current_scope_only'] === true,
        ];

        return [
            'ready' => AdlaireRuntime::all($checks),
            'version' => self::VERSION,
            'checks' => $checks,
            'state' => self::state(),
            'manifest' => self::manifest(),
            'fingerprint' => AdlaireRuntime::fingerprint($checks),
        ];
    }

    public static function release(): array
    {
        $readiness = self::readiness();
        $deploymentGate = self::releaseGate();

        return [
            'version' => self::VERSION,
            'release_ready' => $readiness['ready'] && $deploymentGate['ready'],
            'readiness' => $readiness,
            'deployment_gate' => $deploymentGate,
            'fingerprint' => AdlaireRuntime::fingerprint([
                self::manifest(),
                $readiness,
                $deploymentGate,
            ]),
        ];
    }
}
