<?php

declare(strict_types=1);

final class AdlaireDatabase
{
    private static array $records = [];
    private static array $events = [];
    private static int $recordSequence = 0;
    private static int $eventSequence = 0;

    public static function plannedState(): array
    {
        return [
            'feature' => 'realtime_database',
            'version' => AdlaireProject::VERSION,
            'state' => 'planned',
            'adlaire_method' => true,
            'deployment_axis' => true,
            'mode' => 'event_log',
            'storage' => 'sqlite_libsql',
            'data_runtime' => 'in_memory',
            'sqlite' => true,
            'libsql' => true,
            'channels' => ['system', 'application'],
            'event_stream' => 'internal',
            'rollback_required' => true,
            'runtime_execution' => 'in_memory',
            'readiness_source' => 'deployment_release_gate',
        ];
    }

    public static function create(string $collection, array $data): array
    {
        self::assertCollection($collection);
        $id = 'rec_' . str_pad((string)++self::$recordSequence, 6, '0', STR_PAD_LEFT);
        $record = [
            'id' => $id,
            'collection' => $collection,
            'data' => self::stableData($data),
            'version' => 1,
        ];
        self::$records[$collection][$id] = $record;
        self::recordEvent($collection, $id, 'create', $record['version'], $record['data']);

        return $record;
    }

    public static function update(string $collection, string $id, array $data): array
    {
        self::assertCollection($collection);
        if (!isset(self::$records[$collection][$id])) {
            throw new InvalidArgumentException('Record not found.');
        }
        $record = self::$records[$collection][$id];
        $record['data'] = self::stableData(array_replace($record['data'], $data));
        $record['version'] = (int)$record['version'] + 1;
        self::$records[$collection][$id] = $record;
        self::recordEvent($collection, $id, 'update', $record['version'], $record['data']);

        return $record;
    }

    public static function delete(string $collection, string $id): array
    {
        self::assertCollection($collection);
        if (!isset(self::$records[$collection][$id])) {
            throw new InvalidArgumentException('Record not found.');
        }
        $record = self::$records[$collection][$id];
        unset(self::$records[$collection][$id]);
        self::recordEvent($collection, $id, 'delete', (int)$record['version'] + 1, $record['data']);

        return [
            'id' => $id,
            'collection' => $collection,
            'deleted' => true,
        ];
    }

    public static function snapshot(string $collection): array
    {
        self::assertCollection($collection);
        $records = array_values(self::$records[$collection] ?? []);
        usort($records, static fn(array $a, array $b): int => strcmp((string)$a['id'], (string)$b['id']));
        $version = count(array_filter(self::$events, static fn(array $event): bool => $event['collection'] === $collection));
        $payload = [
            'collection' => $collection,
            'records' => $records,
            'version' => $version,
        ];

        return $payload + [
            'fingerprint' => hash('sha256', json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)),
        ];
    }

    public static function events(?string $after = null): array
    {
        if ($after === null) {
            return array_values(self::$events);
        }

        $found = false;
        $events = [];
        foreach (self::$events as $event) {
            if ($found) {
                $events[] = $event;
                continue;
            }
            if ($event['id'] === $after) {
                $found = true;
            }
        }

        return $events;
    }

    public static function reset(): void
    {
        self::$records = [];
        self::$events = [];
        self::$recordSequence = 0;
        self::$eventSequence = 0;
    }

    public static function readiness(): array
    {
        $planned = self::plannedState();
        $checks = [
            'state_planned' => $planned['state'] === 'planned',
            'event_log_mode' => $planned['mode'] === 'event_log',
            'sqlite_libsql_storage' => $planned['storage'] === 'sqlite_libsql',
            'channels_defined' => $planned['channels'] !== [],
            'event_stream_internal' => $planned['event_stream'] === 'internal',
            'deployment_axis' => $planned['deployment_axis'] === true,
            'rollback_required' => $planned['rollback_required'] === true,
            'in_memory_runtime' => $planned['runtime_execution'] === 'in_memory',
        ];

        return [
            'ready' => self::all($checks),
            'checks' => $checks,
            'planned_state' => $planned,
            'fingerprint' => hash('sha256', json_encode($planned, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)),
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

    private static function assertCollection(string $collection): void
    {
        if (preg_match('/^[a-z][a-z0-9_]*$/', $collection) !== 1) {
            throw new InvalidArgumentException('Collection name must be a stable identifier.');
        }
    }

    private static function stableData(array $data): array
    {
        ksort($data);
        return $data;
    }

    private static function recordEvent(string $collection, string $recordId, string $type, int $version, array $payload): array
    {
        $event = [
            'id' => 'evt_' . str_pad((string)++self::$eventSequence, 6, '0', STR_PAD_LEFT),
            'collection' => $collection,
            'record_id' => $recordId,
            'type' => $type,
            'version' => $version,
            'payload_hash' => hash('sha256', json_encode(self::stableData($payload), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)),
        ];
        self::$events[] = $event;

        return $event;
    }
}
