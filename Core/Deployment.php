<?php

declare(strict_types=1);

final class AdlaireDeployment
{
    public static function state(): array
    {
        return [
            'system' => 'deployment_system',
            'version' => AdlaireProject::VERSION,
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
            'state_blank' => self::state()['state'] === 'blank',
            'execution_none' => self::state()['execution'] === 'none',
            'release_not_ready' => self::state()['release_ready'] === false,
        ];

        return [
            'ready' => self::all($checks),
            'checks' => $checks,
            'state' => self::state(),
        ];
    }

    private static function all(array $checks): bool
    {
        foreach ($checks as $passed) {
            if ($passed !== true) {
                return false;
            }
        }

        return true;
    }
}
