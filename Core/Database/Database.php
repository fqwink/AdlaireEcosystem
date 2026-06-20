<?php

declare(strict_types=1);

final class AdlaireDatabase
{
    private static array $records = [];
    private static array $events = [];
    private static array $collections = [];
    private static ?PDO $pdo = null;
    private static ?string $sqlitePath = null;
    private static int $recordSequence = 0;
    private static int $eventSequence = 0;
    private static int $transactionSequence = 0;
    private static bool $maintenanceMode = false;

    public static function deployableUnit(): array
    {
        return [
            'unit' => 'realtime_database',
            'feature' => 'Realtime Database',
            'kind' => 'baas_core_feature',
            'version' => AdlaireDeployment::VERSION,
            'deployment_axis' => 'undefined',
            'runtime_execution' => 'sqlite_persistent',
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
            'version' => AdlaireDeployment::VERSION,
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
            'data_runtime' => 'sqlite_persistent',
            'fallback_runtime' => 'in_memory',
            'sqlite_persistence' => true,
            'wal_mode' => true,
            'integrity_check' => true,
            'backup_restore' => true,
            'restore_validation' => true,
            'operational_health' => true,
            'integrity_audit' => true,
            'diagnostics' => true,
            'write_policy' => true,
            'write_policy_enforcement' => true,
            'query_explain' => true,
            'import_validation' => true,
            'operational_guard' => true,
            'maintenance_mode' => true,
            'startup_self_check' => true,
            'backup_verification' => true,
            'restore_dry_run' => true,
            'recovery_check' => true,
            'event_log_consistency_check' => true,
            'cursor_safety' => true,
            'read_model_drift_detection' => true,
            'operational_metrics' => true,
            'operational_report' => true,
            'sqlite' => true,
            'libsql' => false,
            'libsql_runtime' => false,
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
            'query_explain' => true,
            'index_plan' => true,
            'migration_plan' => true,
            'event_payload_summary' => true,
            'subscription_model' => true,
            'transaction_boundary' => true,
            'snapshot_export' => true,
            'database_export' => true,
            'snapshot_restore' => true,
            'conflict_detection' => true,
            'event_replay' => true,
            'read_model_rebuild' => true,
            'integrity_audit' => true,
            'diagnostics' => true,
            'write_policy' => true,
            'import_validation' => true,
            'collection_lifecycle' => true,
            'schema_versioning' => true,
            'bulk_import_dry_run' => true,
            'bulk_write' => true,
            'record_restore' => true,
            'snapshot_compare' => true,
            'event_replay_range' => true,
            'query_cursor_pagination' => true,
            'collection_export_filter' => true,
            'data_redaction_export' => true,
            'record_ttl_plan' => true,
            'subscriber_checkpoint_plan' => true,
            'access_rules' => 'undefined',
            'realtime_adapter' => 'none',
            'stream_mode' => 'pull_cursor',
            'snapshot' => 'collection_state',
            'rollback_required' => true,
            'runtime_execution' => 'sqlite_persistent',
            'readiness_source' => 'realtime_database_core',
        ];
    }

    public static function enableSQLite(string $path): array
    {
        if ($path === '') {
            throw new InvalidArgumentException('SQLite path is required.');
        }

        $directory = dirname($path);
        if (!is_dir($directory)) {
            throw new InvalidArgumentException('SQLite directory does not exist.');
        }

        self::$sqlitePath = $path;
        self::$pdo = new PDO('sqlite:' . $path);
        self::$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        self::$pdo->exec('PRAGMA journal_mode = WAL');
        self::$pdo->exec('PRAGMA foreign_keys = ON');
        self::initializeSQLite();
        self::loadSQLite();
        self::persistDefaultCollections();
        self::writeMeta('schema_version', '1');
        self::writeMeta('selected_database', 'sqlite');

        return self::storageStatus();
    }

    public static function disableSQLite(): void
    {
        self::$pdo = null;
        self::$sqlitePath = null;
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
            'storage' => self::$pdo === null ? 'in_memory' : 'sqlite',
            'schema' => self::stableData($schema),
            'indexes' => array_values($indexes),
            'delete_mode' => $deleteMode,
        ];
        self::persistCollection(self::$collections[$collection]);

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
        self::assertWritesAllowed();
        $data = self::normalizeDataForSchema($collection, $data, true);
        self::enforceRecordSize($data);
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
        self::persistRecord($record);
        self::recordEvent($collection, $id, 'create', $record['version'], $record['data'], null);

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
                return self::matchesWhere($record, $where);
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
        $offset = $options['offset'] ?? 0;
        if (is_int($offset) && $offset > 0) {
            $records = array_slice($records, $offset);
        }
        if (is_int($limit) && $limit >= 0) {
            $records = array_slice($records, 0, $limit);
        }

        $total = count($records);
        $select = $options['select'] ?? null;
        if (is_array($select)) {
            $records = array_map(static function (array $record) use ($select): array {
                $selected = ['id' => $record['id']];
                foreach ($select as $field) {
                    $selected[(string)$field] = self::valueForQuery($record, (string)$field);
                }
                return $selected;
            }, $records);
        }

        return [
            'collection' => $collection,
            'records' => ($options['count_only'] ?? false) === true ? [] : $records,
            'count' => $total,
        ];
    }

    public static function queryExplain(string $collection, array $options = []): array
    {
        self::assertCollection($collection);
        $where = $options['where'] ?? null;
        $field = null;
        if (is_array($where) && isset($where['field'])) {
            $field = (string)$where['field'];
        } elseif (is_array($where) && count($where) === 1) {
            $field = (string)array_key_first($where);
        }

        $indexes = self::collections()[$collection]['indexes'];
        $usesIndex = $field !== null && in_array($field, $indexes, true);
        $orderBy = is_string($options['order_by'] ?? null) ? (string)$options['order_by'] : null;

        return [
            'collection' => $collection,
            'where_field' => $field,
            'order_by' => $orderBy,
            'index_candidate' => $field,
            'uses_index' => $usesIndex,
            'full_scan' => $field !== null && !$usesIndex,
            'estimated_records' => count(self::records($collection)),
            'warnings' => $field !== null && !$usesIndex ? ['missing_index'] : [],
            'hints' => $field !== null && !$usesIndex ? ['add_index:' . $field] : [],
        ];
    }

    public static function patch(string $collection, string $id, array $operations, ?int $expectedVersion = null): array
    {
        self::assertWritesAllowed();
        self::enforcePatchOperations($operations);
        $record = self::get($collection, $id);
        if ($record === null) {
            throw new InvalidArgumentException('Record not found.');
        }
        if ($expectedVersion !== null && (int)$record['version'] !== $expectedVersion) {
            return self::conflict($id, (int)$record['version'], $expectedVersion);
        }

        $data = $record['data'];
        foreach ($operations as $operation) {
            $type = $operation['type'] ?? null;
            $field = (string)($operation['field'] ?? '');
            if ($field === '') {
                throw new InvalidArgumentException('Patch operation field is required.');
            }

            if ($type === 'set') {
                $data[$field] = $operation['value'] ?? null;
            } elseif ($type === 'unset') {
                unset($data[$field]);
            } elseif ($type === 'increment') {
                $data[$field] = ($data[$field] ?? 0) + ($operation['value'] ?? 1);
            } elseif ($type === 'append') {
                $list = $data[$field] ?? [];
                if (!is_array($list)) {
                    throw new InvalidArgumentException('Patch append target must be an array.');
                }
                $list[] = $operation['value'] ?? null;
                $data[$field] = $list;
            } else {
                throw new InvalidArgumentException('Unsupported patch operation.');
            }
        }

        return self::update($collection, $id, $data, null, true);
    }

    public static function update(string $collection, string $id, array $data, ?int $expectedVersion = null, bool $replace = false): array
    {
        self::assertCollection($collection);
        self::assertWritesAllowed();
        self::enforceRecordSize($data);
        if (!isset(self::$records[$collection][$id]) || self::isDeleted(self::$records[$collection][$id])) {
            throw new InvalidArgumentException('Record not found.');
        }
        $record = self::$records[$collection][$id];
        if ($expectedVersion !== null && (int)$record['version'] !== $expectedVersion) {
            return self::conflict($id, (int)$record['version'], $expectedVersion);
        }
        $before = $record['data'];
        $data = self::normalizeDataForSchema($collection, $data, false);
        $record['data'] = self::stableData($replace ? $data : array_replace($record['data'], $data));
        $record['meta']['updated_sequence'] = self::$eventSequence + 1;
        $record['meta']['revision'] = (int)$record['meta']['revision'] + 1;
        $record['version'] = (int)$record['version'] + 1;
        self::$records[$collection][$id] = $record;
        self::persistRecord($record);
        self::recordEvent($collection, $id, 'update', $record['version'], $record['data'], $before);

        return $record;
    }

    public static function delete(string $collection, string $id): array
    {
        self::assertCollection($collection);
        self::assertWritesAllowed();
        if (!isset(self::$records[$collection][$id]) || self::isDeleted(self::$records[$collection][$id])) {
            throw new InvalidArgumentException('Record not found.');
        }
        $record = self::$records[$collection][$id];
        $before = $record['data'];
        $deleteVersion = (int)$record['version'] + 1;
        if (self::collections()[$collection]['delete_mode'] === 'soft') {
            $record['meta']['updated_sequence'] = self::$eventSequence + 1;
            $record['meta']['deleted_sequence'] = self::$eventSequence + 1;
            $record['meta']['revision'] = (int)$record['meta']['revision'] + 1;
            $record['version'] = $deleteVersion;
            self::$records[$collection][$id] = $record;
            self::persistRecord($record);
        } else {
            unset(self::$records[$collection][$id]);
            self::deletePersistedRecord($collection, $id);
        }
        self::recordEvent($collection, $id, 'delete', $deleteVersion, $record['data'], $before);

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

    public static function stats(string $collection): array
    {
        $snapshot = self::snapshot($collection);
        $definition = self::collections()[$collection];
        $schemaPayload = [
            'schema' => $definition['schema'],
            'indexes' => $definition['indexes'],
            'delete_mode' => $definition['delete_mode'],
        ];

        return [
            'collection' => $collection,
            'record_count' => count($snapshot['records']),
            'event_count' => count(self::events(null, $collection)),
            'latest_cursor' => $snapshot['cursor'],
            'schema_fingerprint' => hash('sha256', json_encode($schemaPayload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)),
            'snapshot_fingerprint' => $snapshot['fingerprint'],
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

    public static function exportDatabase(): array
    {
        $snapshots = [];
        foreach (array_keys(self::collections()) as $collection) {
            $snapshots[$collection] = self::snapshot($collection);
        }

        $payload = [
            'selected_database' => self::plannedState()['selected_database'],
            'collections' => self::collections(),
            'snapshots' => $snapshots,
            'events' => self::events(),
            'cursor' => self::cursor()['latest'],
            'storage_status' => self::storageStatus(),
        ];
        $fingerprintPayload = $payload;
        unset($fingerprintPayload['storage_status']['path'], $fingerprintPayload['storage_status']['file_size']);

        return $payload + [
            'fingerprint' => self::databaseFingerprint($fingerprintPayload),
        ];
    }

    public static function restoreDatabase(array $payload): array
    {
        $validation = self::validateDatabaseExport($payload);
        if ($validation['valid'] !== true) {
            throw new InvalidArgumentException('Database export payload is invalid.');
        }

        self::$records = [];
        self::$events = [];
        self::$collections = [];
        self::$recordSequence = 0;
        self::$eventSequence = 0;
        self::$transactionSequence = 0;

        if (self::$pdo !== null) {
            self::$pdo->exec('DELETE FROM records');
            self::$pdo->exec('DELETE FROM events');
            self::$pdo->exec('DELETE FROM collections');
        }

        foreach (($payload['collections'] ?? []) as $definition) {
            if (!is_array($definition) || !isset($definition['name'])) {
                continue;
            }
            self::defineCollection(
                (string)$definition['name'],
                (string)$definition['channel'],
                $definition['schema'] ?? [],
                $definition['indexes'] ?? [],
                (string)($definition['delete_mode'] ?? 'hard')
            );
        }

        foreach (($payload['snapshots'] ?? []) as $collection => $snapshot) {
            if (!is_array($snapshot) || !isset(self::collections()[(string)$collection])) {
                continue;
            }
            self::$records[(string)$collection] = [];
            foreach (($snapshot['records'] ?? []) as $record) {
                if (is_array($record) && isset($record['id'])) {
                    $record['collection'] = (string)$collection;
                    $record['channel'] = self::collections()[(string)$collection]['channel'];
                    self::$records[(string)$collection][(string)$record['id']] = $record;
                    self::persistRecord($record);
                }
            }
        }

        foreach (($payload['events'] ?? []) as $event) {
            if (is_array($event) && isset($event['id'])) {
                self::$events[] = $event;
                self::persistEvent($event);
            }
        }
        self::syncSequences();

        return self::exportDatabase();
    }

    public static function validateDatabaseExport(array $payload): array
    {
        $errors = [];
        foreach (['selected_database', 'collections', 'snapshots', 'events', 'cursor'] as $key) {
            if (!array_key_exists($key, $payload)) {
                $errors[] = 'missing_' . $key;
            }
        }
        if (($payload['selected_database'] ?? null) !== 'sqlite') {
            $errors[] = 'selected_database_must_be_sqlite';
        }
        if (isset($payload['collections']) && !is_array($payload['collections'])) {
            $errors[] = 'collections_must_be_array';
        }
        if (isset($payload['snapshots']) && !is_array($payload['snapshots'])) {
            $errors[] = 'snapshots_must_be_array';
        }
        if (isset($payload['events']) && !is_array($payload['events'])) {
            $errors[] = 'events_must_be_array';
        }
        if (isset($payload['fingerprint']) && is_string($payload['fingerprint']) && $errors === []) {
            $fingerprintPayload = $payload;
            unset($fingerprintPayload['fingerprint']);
            if (isset($fingerprintPayload['storage_status']) && is_array($fingerprintPayload['storage_status'])) {
                unset($fingerprintPayload['storage_status']['path'], $fingerprintPayload['storage_status']['file_size']);
            }
            if (self::databaseFingerprint($fingerprintPayload) !== $payload['fingerprint']) {
                $errors[] = 'fingerprint_mismatch';
            }
        }

        return [
            'valid' => $errors === [],
            'errors' => $errors,
        ];
    }

    public static function importValidation(string $collection, array $records): array
    {
        self::assertCollection($collection);
        $errors = [];
        $seen = [];
        foreach ($records as $position => $record) {
            if (!is_array($record)) {
                $errors[] = ['position' => $position, 'error' => 'record_must_be_array'];
                continue;
            }
            $id = (string)($record['id'] ?? '');
            if ($id !== '') {
                if (isset($seen[$id]) || self::get($collection, $id) !== null) {
                    $errors[] = ['position' => $position, 'error' => 'duplicate_id', 'id' => $id];
                }
                $seen[$id] = true;
            }
            try {
                self::normalizeDataForSchema($collection, $record['data'] ?? $record, true);
            } catch (Throwable $exception) {
                $errors[] = ['position' => $position, 'error' => 'schema_violation', 'message' => $exception->getMessage()];
            }
        }

        return [
            'collection' => $collection,
            'valid' => $errors === [],
            'record_count' => count($records),
            'errors' => $errors,
            'dry_run' => true,
        ];
    }

    public static function collectionLifecycle(string $collection): array
    {
        self::assertCollection($collection);
        $definition = self::collections()[$collection];
        $stats = self::stats($collection);

        return [
            'collection' => $collection,
            'state' => 'active',
            'channel' => $definition['channel'],
            'delete_mode' => $definition['delete_mode'],
            'record_count' => $stats['record_count'],
            'event_count' => $stats['event_count'],
            'latest_cursor' => $stats['latest_cursor'],
        ];
    }

    public static function schemaVersioning(string $collection): array
    {
        self::assertCollection($collection);
        $definition = self::collections()[$collection];
        $payload = [
            'schema' => $definition['schema'],
            'indexes' => $definition['indexes'],
            'delete_mode' => $definition['delete_mode'],
        ];

        return [
            'collection' => $collection,
            'schema_version' => max(1, count(self::events(null, $collection))),
            'schema_fingerprint' => hash('sha256', self::encodeJson($payload)),
            'selected_database' => 'sqlite',
        ];
    }

    public static function bulkImportDryRun(string $collection, array $records): array
    {
        return self::importValidation($collection, $records) + [
            'operation' => 'bulk_import',
            'will_write' => false,
        ];
    }

    public static function bulkWrite(array $operations): array
    {
        return self::transaction($operations) + [
            'operation' => 'bulk_write',
            'applied' => true,
        ];
    }

    public static function restoreRecord(string $collection, array $record): array
    {
        self::assertCollection($collection);
        self::assertWritesAllowed();
        if (!isset($record['id']) || !is_string($record['id']) || $record['id'] === '') {
            throw new InvalidArgumentException('Record restore requires a record id.');
        }
        $data = self::normalizeDataForSchema($collection, $record['data'] ?? [], false);
        self::enforceRecordSize($data);
        $id = $record['id'];
        $version = (int)($record['version'] ?? 1);
        $sequence = self::$eventSequence + 1;
        $restored = [
            'id' => $id,
            'collection' => $collection,
            'channel' => self::collections()[$collection]['channel'],
            'data' => self::stableData($data),
            'meta' => [
                'created_sequence' => (int)($record['meta']['created_sequence'] ?? $sequence),
                'updated_sequence' => $sequence,
                'deleted_sequence' => null,
                'revision' => (int)($record['meta']['revision'] ?? $version),
            ],
            'version' => $version,
        ];
        self::$records[$collection][$id] = $restored;
        self::persistRecord($restored);
        self::recordEvent($collection, $id, 'restore', $version, $restored['data'], null);
        self::syncSequences();

        return $restored;
    }

    public static function snapshotCompare(array $left, array $right): array
    {
        $leftIds = self::snapshotRecordIds($left);
        $rightIds = self::snapshotRecordIds($right);

        return [
            'same' => ($left['fingerprint'] ?? null) === ($right['fingerprint'] ?? null),
            'left_fingerprint' => $left['fingerprint'] ?? null,
            'right_fingerprint' => $right['fingerprint'] ?? null,
            'added' => array_values(array_diff($rightIds, $leftIds)),
            'removed' => array_values(array_diff($leftIds, $rightIds)),
            'left_count' => count($leftIds),
            'right_count' => count($rightIds),
        ];
    }

    public static function eventReplayRange(string $collection, ?string $from = null, ?string $to = null): array
    {
        self::assertCollection($collection);
        $range = [];
        foreach (self::events(null, $collection) as $event) {
            if ($from !== null && strcmp((string)$event['id'], $from) < 0) {
                continue;
            }
            if ($to !== null && strcmp((string)$event['id'], $to) > 0) {
                continue;
            }
            $range[] = $event;
        }

        return self::replay($collection, $range) + [
            'from' => $from,
            'to' => $to,
            'event_count' => count($range),
        ];
    }

    public static function queryCursor(string $collection, array $options = []): array
    {
        $limit = max(1, (int)($options['limit'] ?? 25));
        $after = is_string($options['after'] ?? null) ? (string)$options['after'] : null;
        $queryOptions = $options;
        unset($queryOptions['limit'], $queryOptions['after']);
        $records = self::query($collection, $queryOptions)['records'];
        if ($after !== null) {
            $found = false;
            $records = array_values(array_filter($records, static function (array $record) use (&$found, $after): bool {
                if ($found) {
                    return true;
                }
                if (($record['id'] ?? null) === $after) {
                    $found = true;
                }
                return false;
            }));
        }
        $page = array_slice($records, 0, $limit);

        return [
            'collection' => $collection,
            'records' => $page,
            'count' => count($page),
            'next_cursor' => count($records) > $limit ? (string)$page[array_key_last($page)]['id'] : null,
            'has_more' => count($records) > $limit,
        ];
    }

    public static function exportCollection(string $collection, array $options = []): array
    {
        self::assertCollection($collection);
        $records = self::query($collection, ['where' => $options['where'] ?? null])['records'];
        $redact = array_map('strval', $options['redact'] ?? []);
        if ($redact !== []) {
            $records = array_map(static function (array $record) use ($redact): array {
                foreach ($redact as $field) {
                    if (array_key_exists($field, $record['data'])) {
                        $record['data'][$field] = '[redacted]';
                    }
                }
                return $record;
            }, $records);
        }
        $payload = [
            'collection' => $collection,
            'definition' => self::collections()[$collection],
            'records' => $records,
            'filter_applied' => isset($options['where']),
            'redacted_fields' => $redact,
            'cursor' => self::cursor()['latest'],
            'selected_database' => 'sqlite',
        ];

        return $payload + [
            'fingerprint' => hash('sha256', self::encodeJson($payload)),
        ];
    }

    public static function dataRedactionExport(string $collection, array $fields): array
    {
        return self::exportCollection($collection, ['redact' => $fields]);
    }

    public static function restoreSnapshot(string $collection, array $payload): array
    {
        self::assertCollectionName($collection);
        $definition = $payload['definition'] ?? self::collections()[$collection] ?? null;
        if (!is_array($definition)) {
            throw new InvalidArgumentException('Snapshot definition is required.');
        }

        self::defineCollection(
            $collection,
            (string)$definition['channel'],
            $definition['schema'] ?? [],
            $definition['indexes'] ?? [],
            (string)($definition['delete_mode'] ?? 'hard')
        );

        self::$records[$collection] = [];
        self::clearPersistedCollectionRecords($collection);
        $snapshot = $payload['snapshot'] ?? $payload;
        foreach (($snapshot['records'] ?? []) as $record) {
            if (is_array($record) && isset($record['id'])) {
                $record['collection'] = $collection;
                $record['channel'] = self::collections()[$collection]['channel'];
                self::$records[$collection][(string)$record['id']] = $record;
                self::persistRecord($record);
            }
        }
        self::syncSequences();

        return self::snapshot($collection);
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

    public static function replay(string $collection, array $events): array
    {
        self::assertCollection($collection);
        $records = [];
        foreach ($events as $event) {
            if (($event['collection'] ?? null) !== $collection) {
                continue;
            }
            $recordId = (string)($event['record_id'] ?? '');
            if (($event['type'] ?? null) === 'delete') {
                unset($records[$recordId]);
                continue;
            }
            $payload = $event['payload'] ?? [];
            if (is_array($payload)) {
                $records[$recordId] = [
                    'id' => $recordId,
                    'collection' => $collection,
                    'channel' => self::collections()[$collection]['channel'],
                    'data' => self::stableData($payload),
                    'meta' => [
                        'created_sequence' => (int)($event['sequence'] ?? 0),
                        'updated_sequence' => (int)($event['sequence'] ?? 0),
                        'deleted_sequence' => null,
                        'revision' => (int)($event['version'] ?? 1),
                    ],
                    'version' => (int)($event['version'] ?? 1),
                ];
            }
        }

        $payload = [
            'collection' => $collection,
            'records' => array_values($records),
            'version' => count($events),
            'cursor' => self::lastEventId($events),
        ];

        return $payload + [
            'fingerprint' => hash('sha256', json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)),
        ];
    }

    public static function rebuildSnapshot(string $collection): array
    {
        return self::replay($collection, self::events(null, $collection));
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
        self::assertWritesAllowed();
        self::enforceTransactionOperations($operations);
        $before = self::cursor()['latest'];
        $recordsBefore = self::$records;
        $eventsBefore = self::$events;
        $collectionsBefore = self::$collections;
        $recordSequenceBefore = self::$recordSequence;
        $eventSequenceBefore = self::$eventSequence;
        $sqliteTransaction = self::$pdo !== null && !self::$pdo->inTransaction();
        if ($sqliteTransaction) {
            self::$pdo->beginTransaction();
        }

        $results = [];
        try {
            foreach ($operations as $operation) {
                $type = $operation['type'] ?? null;
                $collection = (string)($operation['collection'] ?? '');
                if ($type === 'create') {
                    $results[] = self::create($collection, $operation['data'] ?? []);
                } elseif ($type === 'update') {
                    $results[] = self::update($collection, (string)($operation['id'] ?? ''), $operation['data'] ?? []);
                } elseif ($type === 'patch') {
                    $results[] = self::patch($collection, (string)($operation['id'] ?? ''), $operation['operations'] ?? []);
                } elseif ($type === 'delete') {
                    $results[] = self::delete($collection, (string)($operation['id'] ?? ''));
                } else {
                    throw new InvalidArgumentException('Unsupported transaction operation.');
                }
            }
            if ($sqliteTransaction) {
                self::$pdo->commit();
            }
        } catch (Throwable $exception) {
            if ($sqliteTransaction && self::$pdo !== null && self::$pdo->inTransaction()) {
                self::$pdo->rollBack();
            }
            self::$records = $recordsBefore;
            self::$events = $eventsBefore;
            self::$collections = $collectionsBefore;
            self::$recordSequence = $recordSequenceBefore;
            self::$eventSequence = $eventSequenceBefore;
            throw $exception;
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
        self::disableSQLite();
        self::$recordSequence = 0;
        self::$eventSequence = 0;
        self::$transactionSequence = 0;
        self::$maintenanceMode = false;
    }

    public static function storageStatus(): array
    {
        $tables = ['collections', 'records', 'events', 'schema_versions', 'database_meta'];
        $status = [
            'selected_database' => 'sqlite',
            'runtime_execution' => self::$pdo === null ? 'in_memory' : 'sqlite_persistent',
            'enabled' => self::$pdo !== null,
            'path' => self::$sqlitePath,
            'tables' => $tables,
            'wal_mode' => false,
            'integrity_check' => 'not_checked',
        ];

        if (self::$pdo !== null) {
            $journalMode = self::$pdo->query('PRAGMA journal_mode')->fetchColumn();
            $integrity = self::$pdo->query('PRAGMA integrity_check')->fetchColumn();
            $status['wal_mode'] = strtolower((string)$journalMode) === 'wal';
            $status['journal_mode'] = strtolower((string)$journalMode);
            $status['integrity_check'] = (string)$integrity;
            $status['file_exists'] = self::$sqlitePath !== null && is_file(self::$sqlitePath);
            $status['file_size'] = $status['file_exists'] ? filesize((string)self::$sqlitePath) : 0;
        }

        return $status;
    }

    public static function operationalHealth(): array
    {
        $status = self::storageStatus();
        $audit = self::auditIntegrity();

        return [
            'ready' => $status['enabled'] === true
                && $status['wal_mode'] === true
                && $status['integrity_check'] === 'ok'
                && $audit['valid'] === true,
            'storage' => $status,
            'record_count' => array_sum(array_map('count', self::$records)),
            'event_count' => count(self::$events),
            'latest_cursor' => self::cursor()['latest'],
            'migration' => self::migrationPlan(),
            'audit' => $audit,
        ];
    }

    public static function setMaintenanceMode(bool $enabled): array
    {
        self::$maintenanceMode = $enabled;

        return self::maintenanceMode();
    }

    public static function maintenanceMode(): array
    {
        return [
            'enabled' => self::$maintenanceMode,
            'write_allowed' => self::$maintenanceMode === false,
            'mode' => self::$maintenanceMode ? 'maintenance' : 'normal',
        ];
    }

    public static function operationalGuard(): array
    {
        $audit = self::auditIntegrity();
        $maintenance = self::maintenanceMode();

        return [
            'ready' => $audit['valid'] === true && $maintenance['write_allowed'] === true,
            'maintenance_mode' => $maintenance,
            'write_policy_enforced' => true,
            'write_policy' => self::writePolicy(),
            'audit' => $audit,
            'remote_sync' => 'not_adopted',
            'external_dependency' => 'not_allowed',
        ];
    }

    public static function startupSelfCheck(): array
    {
        $storage = self::storageStatus();
        $audit = self::auditIntegrity();

        return [
            'ready' => $audit['valid'] === true,
            'storage_ready' => $storage['enabled'] === false || $storage['integrity_check'] === 'ok',
            'collections_ready' => self::collections() !== [],
            'event_log_ready' => self::eventLogConsistencyCheck()['valid'],
            'audit' => $audit,
        ];
    }

    public static function backupVerification(array $payload): array
    {
        $validation = self::validateDatabaseExport($payload);

        return [
            'valid' => $validation['valid'],
            'selected_database' => $payload['selected_database'] ?? null,
            'fingerprint_present' => isset($payload['fingerprint']) && is_string($payload['fingerprint']),
            'errors' => $validation['errors'],
        ];
    }

    public static function restoreDryRun(array $payload): array
    {
        $validation = self::validateDatabaseExport($payload);

        return [
            'valid' => $validation['valid'],
            'dry_run' => true,
            'will_restore' => $validation['valid'],
            'errors' => $validation['errors'],
            'collection_count' => is_array($payload['collections'] ?? null) ? count($payload['collections']) : 0,
        ];
    }

    public static function recoveryCheck(array $payload): array
    {
        $restore = self::restoreDryRun($payload);
        $current = self::exportDatabase();

        return [
            'recoverable' => $restore['valid'],
            'dry_run' => true,
            'current_fingerprint' => $current['fingerprint'],
            'backup_fingerprint' => $payload['fingerprint'] ?? null,
            'restore' => $restore,
        ];
    }

    public static function eventLogConsistencyCheck(): array
    {
        $errors = [];
        $previous = 0;
        $seen = [];
        foreach (self::$events as $event) {
            $id = (string)($event['id'] ?? '');
            $sequence = (int)($event['sequence'] ?? 0);
            if ($id === '' || isset($seen[$id])) {
                $errors[] = ['type' => 'duplicate_or_missing_event_id', 'event' => $id];
            }
            $seen[$id] = true;
            if ($sequence !== $previous + 1) {
                $errors[] = ['type' => 'event_sequence_gap', 'event' => $id, 'expected' => $previous + 1, 'actual' => $sequence];
            }
            $previous = $sequence;
        }

        return [
            'valid' => $errors === [],
            'errors' => $errors,
            'event_count' => count(self::$events),
            'latest_cursor' => self::cursor()['latest'],
        ];
    }

    public static function cursorSafety(?string $cursor = null): array
    {
        $ids = array_map(static fn(array $event): string => (string)$event['id'], self::$events);
        $known = $cursor === null || in_array($cursor, $ids, true);

        return [
            'safe' => $known,
            'cursor' => $cursor,
            'latest' => self::cursor()['latest'],
            'known' => $known,
        ];
    }

    public static function readModelDriftDetection(string $collection): array
    {
        $snapshot = self::snapshot($collection);
        $rebuilt = self::rebuildSnapshot($collection);
        $snapshotHash = hash('sha256', self::encodeJson(self::readModelPayload($snapshot)));
        $rebuiltHash = hash('sha256', self::encodeJson(self::readModelPayload($rebuilt)));

        return [
            'collection' => $collection,
            'drift' => $snapshotHash !== $rebuiltHash,
            'snapshot_fingerprint' => $snapshotHash,
            'rebuilt_fingerprint' => $rebuiltHash,
        ];
    }

    public static function operationalMetrics(): array
    {
        return [
            'collection_count' => count(self::collections()),
            'record_count' => array_sum(array_map('count', self::$records)),
            'event_count' => count(self::$events),
            'latest_cursor' => self::cursor()['latest'],
            'maintenance_mode' => self::$maintenanceMode,
            'storage' => self::storageStatus()['runtime_execution'],
        ];
    }

    public static function operationalReport(): array
    {
        return [
            'version' => AdlaireDeployment::VERSION,
            'guard' => self::operationalGuard(),
            'startup_self_check' => self::startupSelfCheck(),
            'metrics' => self::operationalMetrics(),
            'event_log' => self::eventLogConsistencyCheck(),
            'ttl_plan' => self::recordTtlPlan(),
            'subscriber_checkpoint_plan' => self::subscriberCheckpointPlan(),
        ];
    }

    public static function recordTtlPlan(): array
    {
        return [
            'planned' => true,
            'runtime_enforced' => false,
            'automatic_deletion' => false,
        ];
    }

    public static function subscriberCheckpointPlan(): array
    {
        return [
            'planned' => true,
            'runtime_enforced' => false,
            'checkpoint_source' => 'event_cursor',
        ];
    }

    public static function auditIntegrity(): array
    {
        $errors = [];
        $recordIds = [];
        foreach (self::$records as $collection => $records) {
            foreach ($records as $id => $record) {
                $key = $collection . ':' . $id;
                if (isset($recordIds[$key])) {
                    $errors[] = ['type' => 'duplicate_record_id', 'collection' => $collection, 'id' => $id];
                }
                $recordIds[$key] = true;
                if (($record['collection'] ?? null) !== $collection) {
                    $errors[] = ['type' => 'record_collection_mismatch', 'collection' => $collection, 'id' => $id];
                }
                try {
                    self::normalizeDataForSchema($collection, $record['data'] ?? [], false);
                } catch (Throwable $exception) {
                    $errors[] = ['type' => 'schema_violation', 'collection' => $collection, 'id' => $id, 'message' => $exception->getMessage()];
                }
            }
        }

        $lastSequence = 0;
        foreach (self::$events as $event) {
            $collection = (string)($event['collection'] ?? '');
            if ($collection === '' || !isset(self::collections()[$collection])) {
                $errors[] = ['type' => 'event_collection_missing', 'event' => $event['id'] ?? null];
            }
            if ((int)($event['sequence'] ?? 0) <= $lastSequence) {
                $errors[] = ['type' => 'event_sequence_not_increasing', 'event' => $event['id'] ?? null];
            }
            $lastSequence = (int)($event['sequence'] ?? 0);
            if (!isset($event['payload_hash'], $event['payload']) || !is_array($event['payload'])) {
                $errors[] = ['type' => 'event_payload_invalid', 'event' => $event['id'] ?? null];
                continue;
            }
            $payloadHash = hash('sha256', self::encodeJson(self::stableData($event['payload'])));
            if ($payloadHash !== $event['payload_hash']) {
                $errors[] = ['type' => 'event_payload_hash_mismatch', 'event' => $event['id'] ?? null];
            }
        }

        return [
            'valid' => $errors === [],
            'errors' => $errors,
            'record_count' => array_sum(array_map('count', self::$records)),
            'event_count' => count(self::$events),
            'latest_cursor' => self::cursor()['latest'],
        ];
    }

    public static function diagnostics(): array
    {
        $storage = self::storageStatus();
        $audit = self::auditIntegrity();

        return [
            'ready' => $audit['valid'] === true,
            'storage' => $storage,
            'schema' => [
                'collection_count' => count(self::collections()),
                'collections' => array_keys(self::collections()),
            ],
            'query' => [
                'index_plan' => self::indexPlan(),
            ],
            'event' => [
                'event_count' => count(self::$events),
                'latest_cursor' => self::cursor()['latest'],
            ],
            'backup' => [
                'export_ready' => true,
                'restore_validation' => true,
            ],
            'audit' => $audit,
            'release_readiness_hint' => $audit['valid'] === true ? 'database_ready' : 'database_needs_repair',
        ];
    }

    public static function writePolicy(): array
    {
        return [
            'max_record_size_bytes' => 65536,
            'max_collection_name_length' => 64,
            'allowed_schema_types' => ['string', 'integer', 'float', 'boolean', 'array', 'map', 'mixed'],
            'max_patch_operations' => 32,
            'max_transaction_operations' => 64,
            'write_mode' => 'validated',
        ];
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

    public static function migrationPlan(): array
    {
        return [
            'schema_version' => 2,
            'persistence_status' => 'planned',
            'selected_database' => 'sqlite',
            'compatibility_target' => 'libsql',
            'tables' => ['collections', 'records', 'events', 'schema_versions', 'database_meta'],
            'runtime_execution' => self::$pdo === null ? 'in_memory' : 'sqlite_persistent',
            'indexes' => self::indexPlan(),
            'dry_run' => true,
            'rollback_plan' => true,
            'history' => [],
        ];
    }

    public static function accessRules(): array
    {
        return [
            'authentication' => 'undefined',
            'authorization' => 'undefined',
            'access_rules' => 'undefined',
        ];
    }

    public static function realtimeAdapter(): array
    {
        return [
            'adapter' => 'none',
            'stream_mode' => 'pull_cursor',
            'future_adapter' => ['websocket', 'sse'],
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
            'sqlite_persistence' => $planned['sqlite_persistence'] === true,
            'wal_mode' => $planned['wal_mode'] === true,
            'integrity_check' => $planned['integrity_check'] === true,
            'backup_restore' => $planned['backup_restore'] === true,
            'restore_validation' => $planned['restore_validation'] === true,
            'operational_health' => $planned['operational_health'] === true,
            'integrity_audit' => $planned['integrity_audit'] === true,
            'diagnostics' => $planned['diagnostics'] === true,
            'write_policy' => $planned['write_policy'] === true,
            'write_policy_enforcement' => $planned['write_policy_enforcement'] === true,
            'query_explain' => $planned['query_explain'] === true,
            'import_validation' => $planned['import_validation'] === true,
            'operational_guard' => $planned['operational_guard'] === true,
            'maintenance_mode' => $planned['maintenance_mode'] === true,
            'startup_self_check' => $planned['startup_self_check'] === true,
            'backup_verification' => $planned['backup_verification'] === true,
            'restore_dry_run' => $planned['restore_dry_run'] === true,
            'recovery_check' => $planned['recovery_check'] === true,
            'event_log_consistency_check' => $planned['event_log_consistency_check'] === true,
            'cursor_safety' => $planned['cursor_safety'] === true,
            'read_model_drift_detection' => $planned['read_model_drift_detection'] === true,
            'operational_metrics' => $planned['operational_metrics'] === true,
            'operational_report' => $planned['operational_report'] === true,
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
            'migration_plan' => $planned['migration_plan'] === true,
            'event_payload_summary' => $planned['event_payload_summary'] === true,
            'subscription_model' => $planned['subscription_model'] === true,
            'transaction_boundary' => $planned['transaction_boundary'] === true,
            'snapshot_export' => $planned['snapshot_export'] === true,
            'database_export' => $planned['database_export'] === true,
            'snapshot_restore' => $planned['snapshot_restore'] === true,
            'conflict_detection' => $planned['conflict_detection'] === true,
            'event_replay' => $planned['event_replay'] === true,
            'read_model_rebuild' => $planned['read_model_rebuild'] === true,
            'collection_lifecycle' => $planned['collection_lifecycle'] === true,
            'schema_versioning' => $planned['schema_versioning'] === true,
            'bulk_import_dry_run' => $planned['bulk_import_dry_run'] === true,
            'bulk_write' => $planned['bulk_write'] === true,
            'record_restore' => $planned['record_restore'] === true,
            'snapshot_compare' => $planned['snapshot_compare'] === true,
            'event_replay_range' => $planned['event_replay_range'] === true,
            'query_cursor_pagination' => $planned['query_cursor_pagination'] === true,
            'collection_export_filter' => $planned['collection_export_filter'] === true,
            'data_redaction_export' => $planned['data_redaction_export'] === true,
            'record_ttl_plan' => $planned['record_ttl_plan'] === true,
            'subscriber_checkpoint_plan' => $planned['subscriber_checkpoint_plan'] === true,
            'access_rules_undefined' => $planned['access_rules'] === 'undefined',
            'realtime_adapter_none' => $planned['realtime_adapter'] === 'none',
            'stream_mode_pull_cursor' => $planned['stream_mode'] === 'pull_cursor',
            'snapshot_collection_state' => $planned['snapshot'] === 'collection_state',
            'deployment_axis_undefined' => $planned['deployment_axis'] === 'undefined',
            'rollback_required' => $planned['rollback_required'] === true,
            'sqlite_persistent_runtime' => $planned['runtime_execution'] === 'sqlite_persistent',
            'in_memory_fallback' => $planned['fallback_runtime'] === 'in_memory',
        ];

        return [
            'ready' => self::all($checks),
            'checks' => $checks,
            'planned_state' => $planned,
            'fingerprint' => hash('sha256', json_encode($planned, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)),
        ];
    }

    private static function initializeSQLite(): void
    {
        if (self::$pdo === null) {
            return;
        }

        self::$pdo->exec('CREATE TABLE IF NOT EXISTS collections (
            name TEXT PRIMARY KEY,
            channel TEXT NOT NULL,
            storage TEXT NOT NULL,
            schema_json TEXT NOT NULL,
            indexes_json TEXT NOT NULL,
            delete_mode TEXT NOT NULL
        )');
        self::$pdo->exec('CREATE TABLE IF NOT EXISTS records (
            id TEXT NOT NULL,
            collection TEXT NOT NULL,
            channel TEXT NOT NULL,
            data_json TEXT NOT NULL,
            meta_json TEXT NOT NULL,
            version INTEGER NOT NULL,
            PRIMARY KEY (collection, id)
        )');
        self::$pdo->exec('CREATE TABLE IF NOT EXISTS events (
            id TEXT PRIMARY KEY,
            sequence INTEGER NOT NULL,
            collection TEXT NOT NULL,
            channel TEXT NOT NULL,
            record_id TEXT NOT NULL,
            type TEXT NOT NULL,
            version INTEGER NOT NULL,
            payload_hash TEXT NOT NULL,
            before_hash TEXT,
            after_hash TEXT,
            changed_fields_json TEXT NOT NULL,
            payload_json TEXT NOT NULL
        )');
        self::$pdo->exec('CREATE TABLE IF NOT EXISTS schema_versions (
            version INTEGER PRIMARY KEY,
            applied_sequence INTEGER NOT NULL
        )');
        self::$pdo->exec('CREATE TABLE IF NOT EXISTS database_meta (
            key TEXT PRIMARY KEY,
            value TEXT NOT NULL
        )');
        self::$pdo->exec('CREATE INDEX IF NOT EXISTS idx_records_collection ON records (collection)');
        self::$pdo->exec('CREATE INDEX IF NOT EXISTS idx_events_sequence ON events (sequence)');
        self::$pdo->exec('CREATE INDEX IF NOT EXISTS idx_events_collection_record ON events (collection, record_id)');
        self::$pdo->exec('INSERT OR IGNORE INTO schema_versions (version, applied_sequence) VALUES (1, 0)');
    }

    private static function loadSQLite(): void
    {
        if (self::$pdo === null) {
            return;
        }

        self::$collections = [];
        self::$records = [];
        self::$events = [];

        $collectionRows = self::$pdo->query('SELECT * FROM collections ORDER BY name')->fetchAll(PDO::FETCH_ASSOC);
        foreach ($collectionRows as $row) {
            self::$collections[(string)$row['name']] = [
                'name' => (string)$row['name'],
                'channel' => (string)$row['channel'],
                'storage' => (string)$row['storage'],
                'schema' => self::decodeJson((string)$row['schema_json']),
                'indexes' => self::decodeJson((string)$row['indexes_json']),
                'delete_mode' => (string)$row['delete_mode'],
            ];
        }

        $recordRows = self::$pdo->query('SELECT * FROM records ORDER BY collection, id')->fetchAll(PDO::FETCH_ASSOC);
        foreach ($recordRows as $row) {
            $collection = (string)$row['collection'];
            self::$records[$collection][(string)$row['id']] = [
                'id' => (string)$row['id'],
                'collection' => $collection,
                'channel' => (string)$row['channel'],
                'data' => self::decodeJson((string)$row['data_json']),
                'meta' => self::decodeJson((string)$row['meta_json']),
                'version' => (int)$row['version'],
            ];
        }

        $eventRows = self::$pdo->query('SELECT * FROM events ORDER BY sequence')->fetchAll(PDO::FETCH_ASSOC);
        foreach ($eventRows as $row) {
            self::$events[] = [
                'id' => (string)$row['id'],
                'sequence' => (int)$row['sequence'],
                'collection' => (string)$row['collection'],
                'channel' => (string)$row['channel'],
                'record_id' => (string)$row['record_id'],
                'type' => (string)$row['type'],
                'version' => (int)$row['version'],
                'payload_hash' => (string)$row['payload_hash'],
                'before_hash' => $row['before_hash'] === null ? null : (string)$row['before_hash'],
                'after_hash' => $row['after_hash'] === null ? null : (string)$row['after_hash'],
                'changed_fields' => self::decodeJson((string)$row['changed_fields_json']),
                'payload' => self::decodeJson((string)$row['payload_json']),
            ];
        }

        self::syncSequences();
    }

    private static function persistCollection(array $definition): void
    {
        if (self::$pdo === null) {
            return;
        }

        $statement = self::$pdo->prepare('INSERT OR REPLACE INTO collections
            (name, channel, storage, schema_json, indexes_json, delete_mode)
            VALUES (:name, :channel, :storage, :schema_json, :indexes_json, :delete_mode)');
        $statement->execute([
            ':name' => $definition['name'],
            ':channel' => $definition['channel'],
            ':storage' => 'sqlite',
            ':schema_json' => self::encodeJson($definition['schema']),
            ':indexes_json' => self::encodeJson($definition['indexes']),
            ':delete_mode' => $definition['delete_mode'],
        ]);
    }

    private static function persistDefaultCollections(): void
    {
        if (self::$pdo === null) {
            return;
        }

        foreach (self::defaultCollections() as $collection => $definition) {
            if (!isset(self::$collections[$collection])) {
                self::persistCollection($definition);
            }
        }
    }

    private static function persistRecord(array $record): void
    {
        if (self::$pdo === null) {
            return;
        }

        $statement = self::$pdo->prepare('INSERT OR REPLACE INTO records
            (id, collection, channel, data_json, meta_json, version)
            VALUES (:id, :collection, :channel, :data_json, :meta_json, :version)');
        $statement->execute([
            ':id' => $record['id'],
            ':collection' => $record['collection'],
            ':channel' => $record['channel'],
            ':data_json' => self::encodeJson($record['data']),
            ':meta_json' => self::encodeJson($record['meta']),
            ':version' => $record['version'],
        ]);
    }

    private static function deletePersistedRecord(string $collection, string $id): void
    {
        if (self::$pdo === null) {
            return;
        }

        $statement = self::$pdo->prepare('DELETE FROM records WHERE collection = :collection AND id = :id');
        $statement->execute([':collection' => $collection, ':id' => $id]);
    }

    private static function clearPersistedCollectionRecords(string $collection): void
    {
        if (self::$pdo === null) {
            return;
        }

        $statement = self::$pdo->prepare('DELETE FROM records WHERE collection = :collection');
        $statement->execute([':collection' => $collection]);
    }

    private static function persistEvent(array $event): void
    {
        if (self::$pdo === null) {
            return;
        }

        $statement = self::$pdo->prepare('INSERT OR REPLACE INTO events
            (id, sequence, collection, channel, record_id, type, version, payload_hash, before_hash, after_hash, changed_fields_json, payload_json)
            VALUES (:id, :sequence, :collection, :channel, :record_id, :type, :version, :payload_hash, :before_hash, :after_hash, :changed_fields_json, :payload_json)');
        $statement->execute([
            ':id' => $event['id'],
            ':sequence' => $event['sequence'],
            ':collection' => $event['collection'],
            ':channel' => $event['channel'],
            ':record_id' => $event['record_id'],
            ':type' => $event['type'],
            ':version' => $event['version'],
            ':payload_hash' => $event['payload_hash'],
            ':before_hash' => $event['before_hash'],
            ':after_hash' => $event['after_hash'],
            ':changed_fields_json' => self::encodeJson($event['changed_fields']),
            ':payload_json' => self::encodeJson($event['payload']),
        ]);
    }

    private static function writeMeta(string $key, string $value): void
    {
        if (self::$pdo === null) {
            return;
        }

        $statement = self::$pdo->prepare('INSERT OR REPLACE INTO database_meta (key, value) VALUES (:key, :value)');
        $statement->execute([':key' => $key, ':value' => $value]);
    }

    private static function syncSequences(): void
    {
        self::$recordSequence = 0;
        foreach (self::$records as $records) {
            foreach ($records as $record) {
                self::$recordSequence = max(self::$recordSequence, self::sequenceFromId((string)$record['id']));
            }
        }

        self::$eventSequence = 0;
        foreach (self::$events as $event) {
            self::$eventSequence = max(self::$eventSequence, (int)$event['sequence'], self::sequenceFromId((string)$event['id']));
        }
    }

    private static function sequenceFromId(string $id): int
    {
        if (preg_match('/_(\d+)$/', $id, $matches) !== 1) {
            return 0;
        }

        return (int)$matches[1];
    }

    private static function encodeJson(array $payload): string
    {
        $encoded = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if (!is_string($encoded)) {
            throw new RuntimeException('Failed to encode JSON payload.');
        }

        return $encoded;
    }

    private static function decodeJson(string $payload): array
    {
        $decoded = json_decode($payload, true);
        return is_array($decoded) ? $decoded : [];
    }

    private static function databaseFingerprint(array $payload): string
    {
        return hash('sha256', self::encodeJson($payload));
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

    private static function normalizeDataForSchema(string $collection, array $data, bool $applyDefaults): array
    {
        $schema = self::collections()[$collection]['schema'];
        foreach ($schema as $field => $rule) {
            $definition = self::schemaDefinition($rule);
            $hasValue = array_key_exists($field, $data);
            if (!$hasValue && $applyDefaults && array_key_exists('default', $definition)) {
                $data[$field] = $definition['default'];
                $hasValue = true;
            }
            if (!$hasValue) {
                if (($definition['required'] ?? false) === true && $applyDefaults) {
                    throw new InvalidArgumentException('Required schema field is missing.');
                }
                continue;
            }
            if ($data[$field] === null && ($definition['nullable'] ?? false) === true) {
                continue;
            }
            if (!self::matchesType($data[$field], (string)$definition['type'])) {
                throw new InvalidArgumentException('Record data does not match collection schema.');
            }
            if (isset($definition['enum']) && is_array($definition['enum']) && !in_array($data[$field], $definition['enum'], true)) {
                throw new InvalidArgumentException('Record data does not match collection enum.');
            }
            if (isset($definition['min']) && is_numeric($data[$field]) && $data[$field] < $definition['min']) {
                throw new InvalidArgumentException('Record data is below schema min.');
            }
            if (isset($definition['max']) && is_numeric($data[$field]) && $data[$field] > $definition['max']) {
                throw new InvalidArgumentException('Record data is above schema max.');
            }
        }

        return self::stableData($data);
    }

    private static function schemaDefinition(mixed $rule): array
    {
        if (is_array($rule)) {
            $definition = [
                'type' => $rule['type'] ?? 'mixed',
                'required' => $rule['required'] ?? false,
                'nullable' => $rule['nullable'] ?? false,
            ];
            foreach (['default', 'enum', 'min', 'max'] as $key) {
                if (array_key_exists($key, $rule)) {
                    $definition[$key] = $rule[$key];
                }
            }
            return $definition;
        }

        return ['type' => (string)$rule, 'required' => false, 'nullable' => false];
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

    private static function matchesWhere(array $record, array $where): bool
    {
        if (isset($where['field'])) {
            $value = self::valueForQuery($record, (string)$where['field']);
            $operator = (string)($where['operator'] ?? (array_key_exists('equals', $where) ? 'equals' : 'equals'));
            $expected = $where['value'] ?? ($where['equals'] ?? null);
            return self::compareWhereValue($value, $operator, $expected);
        }

        foreach ($where as $field => $expected) {
            if (!self::compareWhereValue(self::valueForQuery($record, (string)$field), 'equals', $expected)) {
                return false;
            }
        }

        return true;
    }

    private static function compareWhereValue(mixed $value, string $operator, mixed $expected): bool
    {
        return match ($operator) {
            'equals' => $value === $expected,
            'not_equals' => $value !== $expected,
            'gt' => self::compareValues($value, $expected) > 0,
            'gte' => self::compareValues($value, $expected) >= 0,
            'lt' => self::compareValues($value, $expected) < 0,
            'lte' => self::compareValues($value, $expected) <= 0,
            'contains' => is_array($value) ? in_array($expected, $value, true) : str_contains((string)$value, (string)$expected),
            default => false,
        };
    }

    private static function conflict(string $id, int $currentVersion, int $expectedVersion): array
    {
        return [
            'conflict' => true,
            'id' => $id,
            'current_version' => $currentVersion,
            'expected_version' => $expectedVersion,
        ];
    }

    private static function assertWritesAllowed(): void
    {
        if (self::$maintenanceMode) {
            throw new RuntimeException('Realtime Database is in maintenance mode.');
        }
    }

    private static function enforceRecordSize(array $data): void
    {
        $size = strlen(self::encodeJson(self::stableData($data)));
        if ($size > self::writePolicy()['max_record_size_bytes']) {
            throw new InvalidArgumentException('Record exceeds write policy size.');
        }
    }

    private static function enforcePatchOperations(array $operations): void
    {
        if (count($operations) > self::writePolicy()['max_patch_operations']) {
            throw new InvalidArgumentException('Patch operations exceed write policy limit.');
        }
    }

    private static function enforceTransactionOperations(array $operations): void
    {
        if (count($operations) > self::writePolicy()['max_transaction_operations']) {
            throw new InvalidArgumentException('Transaction operations exceed write policy limit.');
        }
    }

    private static function snapshotRecordIds(array $snapshot): array
    {
        $records = $snapshot['records'] ?? ($snapshot['snapshot']['records'] ?? []);
        $ids = [];
        foreach ($records as $record) {
            if (is_array($record) && isset($record['id'])) {
                $ids[] = (string)$record['id'];
            }
        }
        sort($ids);

        return $ids;
    }

    private static function readModelPayload(array $snapshot): array
    {
        $records = [];
        foreach (($snapshot['records'] ?? []) as $record) {
            if (!is_array($record) || !isset($record['id'])) {
                continue;
            }
            $records[(string)$record['id']] = [
                'id' => (string)$record['id'],
                'data' => self::stableData($record['data'] ?? []),
                'version' => (int)($record['version'] ?? 1),
            ];
        }
        ksort($records);

        return [
            'collection' => (string)($snapshot['collection'] ?? ''),
            'records' => array_values($records),
        ];
    }

    private static function changedFields(?array $before, array $after): array
    {
        $fields = array_unique(array_merge(array_keys($before ?? []), array_keys($after)));
        $changed = [];
        foreach ($fields as $field) {
            if (($before[$field] ?? null) !== ($after[$field] ?? null)) {
                $changed[] = $field;
            }
        }
        sort($changed);
        return $changed;
    }

    private static function recordEvent(string $collection, string $recordId, string $type, int $version, array $payload, ?array $before): array
    {
        $afterHash = hash('sha256', json_encode(self::stableData($payload), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        $event = [
            'id' => 'evt_' . str_pad((string)++self::$eventSequence, 6, '0', STR_PAD_LEFT),
            'sequence' => self::$eventSequence,
            'collection' => $collection,
            'channel' => self::collections()[$collection]['channel'],
            'record_id' => $recordId,
            'type' => $type,
            'version' => $version,
            'payload_hash' => $afterHash,
            'before_hash' => $before === null ? null : hash('sha256', json_encode(self::stableData($before), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)),
            'after_hash' => $type === 'delete' ? null : $afterHash,
            'changed_fields' => self::changedFields($before, $payload),
            'payload' => self::stableData($payload),
        ];
        self::$events[] = $event;
        self::persistEvent($event);

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
        $storage = self::$pdo === null ? 'in_memory' : 'sqlite';

        return [
            'application' => [
                'name' => 'application',
                'channel' => 'application',
                'storage' => $storage,
                'schema' => [],
                'indexes' => ['id'],
                'delete_mode' => 'hard',
            ],
            'system' => [
                'name' => 'system',
                'channel' => 'system',
                'storage' => $storage,
                'schema' => [],
                'indexes' => ['id'],
                'delete_mode' => 'hard',
            ],
        ];
    }
}
