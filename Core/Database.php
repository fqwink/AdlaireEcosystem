<?php

declare(strict_types=1);

final class AdlaireDatabase
{
    private static array $records = [];
    private static array $events = [];
    private static array $collections = [];
    private static int $recordSequence = 0;
    private static int $eventSequence = 0;

    public static function deployableUnit(): array
    {
        return [
            'unit' => 'realtime_database',
            'feature' => 'Realtime Database',
            'kind' => 'baas_core_feature',
            'version' => AdlaireProject::VERSION,
            'deployment_axis' => 'undefined',
            'runtime_execution' => 'in_memory',
            'storage_policy' => 'sqlite_libsql',
            'rollback_required' => true,
        ];
    }

    public static function plannedState(): array
    {
        return [
            'feature' => 'realtime_database',
            'version' => AdlaireProject::VERSION,
            'state' => 'planned',
            'kind' => 'baas_core_feature',
            'deployable_unit' => 'realtime_database',
            'adlaire_method' => true,
            'deployment_axis' => 'undefined',
            'mode' => 'event_log',
            'storage' => 'sqlite_libsql',
            'data_runtime' => 'in_memory',
            'sqlite' => true,
            'libsql' => true,
            'collections' => array_keys(self::collections()),
            'channels' => ['system', 'application'],
            'event_stream' => 'internal',
            'cursor' => 'event_id',
            'collection_stream' => true,
            'record_lookup' => true,
            'record_listing' => true,
            'snapshot' => 'collection_state',
            'rollback_required' => true,
            'runtime_execution' => 'in_memory',
            'readiness_source' => 'realtime_database_core',
        ];
    }

    public static function defineCollection(string $collection, string $channel): array
    {
        self::assertCollectionName($collection);
        self::assertChannel($channel);

        self::$collections[$collection] = [
            'name' => $collection,
            'channel' => $channel,
            'storage' => 'in_memory',
        ];

        return self::$collections[$collection];
    }

    public static function collections(): array
    {
        $collections = self::defaultCollections() + self::$collections;
        ksort($collections);

        return $collections;
    }

    public static function create(string $collection, array $data): array
    {
        self::assertCollection($collection);
        $id = 'rec_' . str_pad((string)++self::$recordSequence, 6, '0', STR_PAD_LEFT);
        $record = [
            'id' => $id,
            'collection' => $collection,
            'channel' => self::collections()[$collection]['channel'],
            'data' => self::stableData($data),
            'version' => 1,
        ];
        self::$records[$collection][$id] = $record;
        self::recordEvent($collection, $id, 'create', $record['version'], $record['data']);

        return $record;
    }

    public static function get(string $collection, string $id): ?array
    {
        self::assertCollection($collection);

        return self::$records[$collection][$id] ?? null;
    }

    public static function records(string $collection): array
    {
        self::assertCollection($collection);
        $records = array_values(self::$records[$collection] ?? []);
        usort($records, static fn(array $a, array $b): int => strcmp((string)$a['id'], (string)$b['id']));

        return $records;
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
            'channel' => self::collections()[$collection]['channel'],
            'deleted' => true,
        ];
    }

    public static function snapshot(string $collection): array
    {
        self::assertCollection($collection);
        $records = self::records($collection);
        $collectionEvents = self::events(null, $collection);
        $version = count($collectionEvents);
        $payload = [
            'collection' => $collection,
            'records' => $records,
            'version' => $version,
            'cursor' => self::lastEventId($collectionEvents),
        ];

        return $payload + [
            'fingerprint' => hash('sha256', json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)),
        ];
    }

    public static function events(?string $after = null, ?string $collection = null): array
    {
        if ($collection !== null) {
            self::assertCollection($collection);
        }

        $events = self::filterEvents($collection);
        if ($after === null) {
            return $events;
        }

        $found = false;
        $cursorEvents = [];
        foreach ($events as $event) {
            if ($found) {
                $cursorEvents[] = $event;
                continue;
            }
            if ($event['id'] === $after) {
                $found = true;
            }
        }

        return $cursorEvents;
    }

    public static function stream(string $collection, ?string $after = null): array
    {
        $events = self::events($after, $collection);

        return [
            'collection' => $collection,
            'events' => $events,
            'cursor' => self::lastEventId($events) ?? $after,
        ];
    }

    public static function cursor(): array
    {
        return [
            'after' => null,
            'latest' => self::lastEventId(self::$events),
        ];
    }

    public static function reset(): void
    {
        self::$records = [];
        self::$events = [];
        self::$collections = [];
        self::$recordSequence = 0;
        self::$eventSequence = 0;
    }

    public static function readiness(): array
    {
        $planned = self::plannedState();
        $checks = [
            'state_planned' => $planned['state'] === 'planned',
            'baas_core_feature' => $planned['kind'] === 'baas_core_feature',
            'deployable_unit' => $planned['deployable_unit'] === 'realtime_database',
            'event_log_mode' => $planned['mode'] === 'event_log',
            'sqlite_libsql_storage' => $planned['storage'] === 'sqlite_libsql',
            'collections_defined' => self::collections() !== [],
            'channels_defined' => $planned['channels'] !== [],
            'event_stream_internal' => $planned['event_stream'] === 'internal',
            'cursor_event_id' => $planned['cursor'] === 'event_id',
            'collection_stream' => $planned['collection_stream'] === true,
            'record_lookup' => $planned['record_lookup'] === true,
            'record_listing' => $planned['record_listing'] === true,
            'snapshot_collection_state' => $planned['snapshot'] === 'collection_state',
            'deployment_axis_undefined' => $planned['deployment_axis'] === 'undefined',
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
        self::assertCollectionName($collection);
        if (!isset(self::collections()[$collection])) {
            throw new InvalidArgumentException('Collection is not defined.');
        }
    }

    private static function assertCollectionName(string $collection): void
    {
        if (preg_match('/^[a-z][a-z0-9_]*$/', $collection) !== 1) {
            throw new InvalidArgumentException('Collection name must be a stable identifier.');
        }
    }

    private static function assertChannel(string $channel): void
    {
        if (!in_array($channel, ['system', 'application'], true)) {
            throw new InvalidArgumentException('Channel must be system or application.');
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
            'sequence' => self::$eventSequence,
            'collection' => $collection,
            'channel' => self::collections()[$collection]['channel'],
            'record_id' => $recordId,
            'type' => $type,
            'version' => $version,
            'payload_hash' => hash('sha256', json_encode(self::stableData($payload), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)),
        ];
        self::$events[] = $event;

        return $event;
    }

    private static function filterEvents(?string $collection): array
    {
        $events = $collection === null
            ? self::$events
            : array_filter(self::$events, static fn(array $event): bool => $event['collection'] === $collection);

        return array_values($events);
    }

    private static function lastEventId(array $events): ?string
    {
        if ($events === []) {
            return null;
        }

        $last = $events[array_key_last($events)];

        return is_array($last) ? (string)$last['id'] : null;
    }

    private static function defaultCollections(): array
    {
        return [
            'application' => [
                'name' => 'application',
                'channel' => 'application',
                'storage' => 'in_memory',
            ],
            'system' => [
                'name' => 'system',
                'channel' => 'system',
                'storage' => 'in_memory',
            ],
        ];
    }
}
