<?php

declare(strict_types=1);

final class AdlaireDatabase
{
    private static array $records = [];
    private static array $events = [];
    private static array $collections = [];
    private static int $recordSequence = 0;
    private static int $eventSequence = 0;
    private static int $transactionSequence = 0;

    public static function deployableUnit(): array
    {
        return [
            'unit' => 'realtime_database',
            'feature' => 'Realtime Database',
            'kind' => 'baas_core_feature',
            'version' => AdlaireProject::VERSION,
            'deployment_axis' => 'undefined',
            'runtime_execution' => 'in_memory',
            'selected_database' => 'sqlite',
            'compatibility_target' => 'libsql',
            'storage_policy' => 'sqlite_primary_libsql_compatible',
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
            'selected_database' => 'sqlite',
            'compatibility_target' => 'libsql',
            'storage_policy' => 'sqlite_primary_libsql_compatible',
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
            'schema' => true,
            'record_metadata' => true,
            'query' => true,
            'index_plan' => true,
            'subscription_model' => true,
            'transaction_boundary' => true,
            'snapshot_export' => true,
            'snapshot' => 'collection_state',
            'rollback_required' => true,
            'runtime_execution' => 'in_memory',
            'readiness_source' => 'realtime_database_core',
        ];
    }

    public static function defineCollection(
        string $collection,
        string $channel,
        array $schema = [],
        array $indexes = [],
        string $deleteMode = 'hard'
    ): array {
        self::assertCollectionName($collection);
        self::assertChannel($channel);
        self::assertDeleteMode($deleteMode);

        self::$collections[$collection] = [
            'name' => $collection,
            'channel' => $channel,
            'storage' => 'in_memory',
            'schema' => self::stableData($schema),
            'indexes' => array_values($indexes),
            'delete_mode' => $deleteMode,
        ];

        return self::$collections[$collection];
    }

    public static function collections(): array
    {
        $collections = array_replace(self::defaultCollections(), self::$collections);
        ksort($collections);

        return $collections;
    }

    public static function create(string $collection, array $data): array
    {
        self::assertCollection($collection);
        self::assertDataMatchesSchema($collection, $data);
        $id = 'rec_' . str_pad((string)++self::$recordSequence, 6, '0', STR_PAD_LEFT);
        $sequence = self::$eventSequence + 1;
        $record = [
            'id' => $id,
            'collection' => $collection,
            'channel' => self::collections()[$collection]['channel'],
            'data' => self::stableData($data),
            'meta' => [
                'created_sequence' => $sequence,
                'updated_sequence' => $sequence,
                'deleted_sequence' => null,
                'revision' => 1,
            ],
            'version' => 1,
        ];
        self::$records[$collection][$id] = $record;
        self::recordEvent($collection, $id, 'create', $record['version'], $record['data']);

        return $record;
    }

    public static function get(string $collection, string $id): ?array
    {
        self::assertCollection($collection);
        $record = self::$records[$collection][$id] ?? null;
        if ($record === null || self::isDeleted($record)) {
            return null;
        }

        return $record;
    }

    public static function records(string $collection): array
    {
        self::assertCollection($collection);
        $records = array_values(array_filter(
            self::$records[$collection] ?? [],
            static fn(array $record): bool => !self::isDeleted($record)
        ));
        usort($records, static fn(array $a, array $b): int => strcmp((string)$a['id'], (string)$b['id']));

        return $records;
    }

    public static function query(string $collection, array $options = []): array
    {
        $records = self::records($collection);
        $where = $options['where'] ?? null;
        if (is_array($where)) {
            $records = array_values(array_filter($records, static function (array $record) use ($where): bool {
                if (isset($where['field'])) {
                    return self::valueForQuery($record, (string)$where['field']) === ($where['equals'] ?? null);
                }

                foreach ($where as $field => $expected) {
                    if (self::valueForQuery($record, (string)$field) !== $expected) {
                        return false;
                    }
                }

                return true;
            }));
        }

        $orderBy = $options['order_by'] ?? null;
        if (is_string($orderBy) && $orderBy !== '') {
            $direction = ($options['direction'] ?? 'asc') === 'desc' ? -1 : 1;
            usort($records, static function (array $a, array $b) use ($orderBy, $direction): int {
                return $direction * self::compareValues(self::valueForQuery($a, $orderBy), self::valueForQuery($b, $orderBy));
            });
        }

        $limit = $options['limit'] ?? null;
        if (is_int($limit) && $limit >= 0) {
            $records = array_slice($records, 0, $limit);
        }

        return [
            'collection' => $collection,
            'records' => $records,
            'count' => count($records),
        ];
    }

    public static function update(string $collection, string $id, array $data): array
    {
        self::assertCollection($collection);
        self::assertDataMatchesSchema($collection, $data);
        if (!isset(self::$records[$collection][$id]) || self::isDeleted(self::$records[$collection][$id])) {
            throw new InvalidArgumentException('Record not found.');
        }
        $record = self::$records[$collection][$id];
        $record['data'] = self::stableData(array_replace($record['data'], $data));
        $record['meta']['updated_sequence'] = self::$eventSequence + 1;
        $record['meta']['revision'] = (int)$record['meta']['revision'] + 1;
        $record['version'] = (int)$record['version'] + 1;
        self::$records[$collection][$id] = $record;
        self::recordEvent($collection, $id, 'update', $record['version'], $record['data']);

        return $record;
    }

    public static function delete(string $collection, string $id): array
    {
        self::assertCollection($collection);
        if (!isset(self::$records[$collection][$id]) || self::isDeleted(self::$records[$collection][$id])) {
            throw new InvalidArgumentException('Record not found.');
        }
        $record = self::$records[$collection][$id];
        $deleteVersion = (int)$record['version'] + 1;
        if (self::collections()[$collection]['delete_mode'] === 'soft') {
            $record['meta']['updated_sequence'] = self::$eventSequence + 1;
            $record['meta']['deleted_sequence'] = self::$eventSequence + 1;
            $record['meta']['revision'] = (int)$record['meta']['revision'] + 1;
            $record['version'] = $deleteVersion;
            self::$records[$collection][$id] = $record;
        } else {
            unset(self::$records[$collection][$id]);
        }
        self::recordEvent($collection, $id, 'delete', $deleteVersion, $record['data']);

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

    public static function exportSnapshot(string $collection): array
    {
        $snapshot = self::snapshot($collection);
        $payload = [
            'collection' => $collection,
            'definition' => self::collections()[$collection],
            'snapshot' => $snapshot,
            'cursor' => $snapshot['cursor'],
            'selected_database' => self::plannedState()['selected_database'],
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

    public static function subscribe(string $collection, ?string $after = null): array
    {
        return self::stream($collection, $after) + [
            'subscription' => 'collection_stream',
            'push' => false,
        ];
    }

    public static function transaction(array $operations): array
    {
        $before = self::cursor()['latest'];
        $results = [];
        foreach ($operations as $operation) {
            $type = $operation['type'] ?? null;
            $collection = (string)($operation['collection'] ?? '');
            if ($type === 'create') {
                $results[] = self::create($collection, $operation['data'] ?? []);
            } elseif ($type === 'update') {
                $results[] = self::update($collection, (string)($operation['id'] ?? ''), $operation['data'] ?? []);
            } elseif ($type === 'delete') {
                $results[] = self::delete($collection, (string)($operation['id'] ?? ''));
            } else {
                throw new InvalidArgumentException('Unsupported transaction operation.');
            }
        }

        $events = self::events($before);

        return [
            'id' => 'txn_' . str_pad((string)++self::$transactionSequence, 6, '0', STR_PAD_LEFT),
            'before' => $before,
            'results' => $results,
            'events' => $events,
            'cursor' => self::lastEventId($events) ?? $before,
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
        self::$transactionSequence = 0;
    }

    public static function indexPlan(): array
    {
        $custom = [];
        foreach (self::collections() as $collection => $definition) {
            $custom[$collection] = $definition['indexes'];
        }

        return [
            'selected_database' => 'sqlite',
            'primary' => ['id'],
            'collection' => ['collection'],
            'events' => ['sequence', 'collection', 'record_id'],
            'custom' => $custom,
        ];
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
            'sqlite_selected' => $planned['selected_database'] === 'sqlite',
            'libsql_compatibility_target' => $planned['compatibility_target'] === 'libsql',
            'sqlite_primary_policy' => $planned['storage_policy'] === 'sqlite_primary_libsql_compatible',
            'collections_defined' => self::collections() !== [],
            'channels_defined' => $planned['channels'] !== [],
            'event_stream_internal' => $planned['event_stream'] === 'internal',
            'cursor_event_id' => $planned['cursor'] === 'event_id',
            'collection_stream' => $planned['collection_stream'] === true,
            'record_lookup' => $planned['record_lookup'] === true,
            'record_listing' => $planned['record_listing'] === true,
            'schema' => $planned['schema'] === true,
            'record_metadata' => $planned['record_metadata'] === true,
            'query' => $planned['query'] === true,
            'index_plan' => $planned['index_plan'] === true,
            'subscription_model' => $planned['subscription_model'] === true,
            'transaction_boundary' => $planned['transaction_boundary'] === true,
            'snapshot_export' => $planned['snapshot_export'] === true,
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

    private static function assertDeleteMode(string $deleteMode): void
    {
        if (!in_array($deleteMode, ['hard', 'soft'], true)) {
            throw new InvalidArgumentException('Delete mode must be hard or soft.');
        }
    }

    private static function assertDataMatchesSchema(string $collection, array $data): void
    {
        $schema = self::collections()[$collection]['schema'];
        foreach ($schema as $field => $type) {
            if (array_key_exists($field, $data) && !self::matchesType($data[$field], (string)$type)) {
                throw new InvalidArgumentException('Record data does not match collection schema.');
            }
        }
    }

    private static function matchesType(mixed $value, string $type): bool
    {
        return match ($type) {
            'string' => is_string($value),
            'integer' => is_int($value),
            'float' => is_float($value) || is_int($value),
            'boolean' => is_bool($value),
            'array', 'map' => is_array($value),
            'mixed' => true,
            default => true,
        };
    }

    private static function stableData(array $data): array
    {
        ksort($data);
        return $data;
    }

    private static function isDeleted(array $record): bool
    {
        return ($record['meta']['deleted_sequence'] ?? null) !== null;
    }

    private static function valueForQuery(array $record, string $field): mixed
    {
        if (str_starts_with($field, 'meta.')) {
            return $record['meta'][substr($field, 5)] ?? null;
        }

        return $record['data'][$field] ?? $record[$field] ?? null;
    }

    private static function compareValues(mixed $left, mixed $right): int
    {
        if ((is_int($left) || is_float($left)) && (is_int($right) || is_float($right))) {
            return $left <=> $right;
        }

        return strcmp((string)$left, (string)$right);
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
                'schema' => [],
                'indexes' => ['id'],
                'delete_mode' => 'hard',
            ],
            'system' => [
                'name' => 'system',
                'channel' => 'system',
                'storage' => 'in_memory',
                'schema' => [],
                'indexes' => ['id'],
                'delete_mode' => 'hard',
            ],
        ];
    }
}
