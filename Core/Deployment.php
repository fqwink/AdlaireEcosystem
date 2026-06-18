<?php

declare(strict_types=1);

require_once __DIR__ . '/Database.php';

final class AdlaireDeployment
{
    public static function preview(): array
    {
        return [
            'read_only' => true,
            'writes_allowed' => false,
            'command_execution_allowed' => false,
            'targets' => ['Core', 'Applications', 'docs', 'tests'],
        ];
    }

    public static function releaseGate(): array
    {
        $database = AdlaireDatabase::readiness();
        $checks = [
            'preview_read_only' => self::preview()['read_only'] === true,
            'database_ready' => $database['ready'] === true,
            'database_planned_state' => is_array($database['planned_state'] ?? null),
            'database_event_log_mode' => ($database['planned_state']['mode'] ?? null) === 'event_log',
            'database_in_memory_runtime' => ($database['planned_state']['runtime_execution'] ?? null) === 'in_memory',
            'adlaire_method_axis' => ($database['planned_state']['deployment_axis'] ?? false) === true,
            'rollback_view_ready' => self::rollbackView()['ready'] === true,
            'current_scope_ready' => true,
        ];

        return [
            'ready' => self::all($checks),
            'checks' => $checks,
            'rollback_view' => self::rollbackView(),
            'release_evidence' => self::evidence($checks),
        ];
    }

    public static function rollbackView(): array
    {
        return [
            'ready' => true,
            'read_only' => true,
            'rollback_supported' => true,
            'mode' => 'planned_state_revert',
            'target' => 'previous_planned_state',
            'writes_allowed' => false,
        ];
    }

    public static function evidence(array $releaseChecks): array
    {
        $payload = [
            'source' => 'deployment_release_gate',
            'planned_state' => AdlaireDatabase::plannedState(),
            'database_readiness' => AdlaireDatabase::readiness()['checks'],
            'database_snapshot' => AdlaireDatabase::snapshot('system'),
            'rollback_view' => self::rollbackView(),
            'release_gate_result' => $releaseChecks,
        ];

        return [
            'ready' => self::all($releaseChecks),
            'payload' => $payload,
            'fingerprint' => hash('sha256', json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)),
        ];
    }

    public static function readiness(): array
    {
        $gate = self::releaseGate();

        return [
            'ready' => $gate['ready'] === true,
            'preview' => self::preview(),
            'release_gate' => $gate,
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
