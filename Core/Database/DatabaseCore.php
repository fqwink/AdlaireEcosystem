<?php

declare(strict_types=1);

require_once __DIR__ . '/../EventLog.php';

final class AdlaireDatabase
{
    public const VERSION = 'v0.017';

    private static array $records = [];
    private static array $events = [];
    private static array $collections = [];
    private static ?PDO $pdo = null;
    private static ?string $sqlitePath = null;
    private static int $recordSequence = 0;
    private static int $eventSequence = 0;
    private static int $transactionSequence = 0;
    private static bool $maintenanceMode = false;
    private static bool $safeMode = false;
    private static bool $degradedMode = false;
    private static array $collectionLocks = [];
    private static array $writeIntents = [];

    public static function deployableUnit(): array
    {
        return [
            'unit' => 'realtime_database',
            'feature' => 'Realtime Database',
            'kind' => 'baas_core_feature',
            'version' => self::VERSION,
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
            'version' => self::VERSION,
            'state' => 'planned',
            'kind' => 'baas_core_feature',
            'deployable_unit' => 'realtime_database',
            'adlaire_method' => true,
            'deployment_axis' => 'undefined',
            'mode' => 'event_log',
            'core_root_policy' => 'common_foundation_and_entrypoints',
            'entrypoint_policy' => 'single_file_principle',
            'event_log_policy' => 'single_file_principle',
            'event_log_file' => 'Core/EventLog.php',
            'event_log_folder' => 'prohibited',
            'event_log_role' => 'common_foundation',
            'event_log_common_foundation' => true,
            'event_log_single_file' => true,
            'event_log_entrypoint' => false,
            'event_log_shared_by' => ['realtime_database', 'authentication', 'authorization'],
            'event_log_message_broker' => false,
            'event_log_remote_sync' => false,
            'event_log_automatic_repair' => false,
            'event_log_automatic_compaction' => false,
            'event_log_automatic_delete' => false,
            'event_envelope' => true,
            'event_domain_source' => true,
            'event_metadata' => true,
            'event_type_registry' => true,
            'event_chain_hash' => true,
            'event_validation' => true,
            'event_replay_scope' => true,
            'event_evidence' => true,
            'event_snapshot_link' => true,
            'event_replay_verification' => true,
            'event_cursor_contract' => true,
            'event_import_validation' => true,
            'event_export_packet' => true,
            'event_retention_view' => true,
            'event_risk_report' => true,
            'event_operation_journal' => true,
            'storage' => 'sqlite_libsql',
            'selected_database' => 'sqlite',
            'compatibility_target' => 'libsql',
            'storage_policy' => 'sqlite_primary_libsql_compatible',
            'data_runtime' => 'sqlite_persistent',
            'fallback_runtime' => 'in_memory',
            'sqlite_persistence' => true,
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
            'change_feed_filter' => true,
            'record_version_history' => true,
            'record_diff' => true,
            'snapshot_retention_plan' => true,
            'backup_manifest' => true,
            'restore_preview' => true,
            'collection_lock' => true,
            'write_quota_guard' => true,
            'event_checkpoint' => true,
            'operational_incident_report' => true,
            'query_cursor_enhancement' => true,
            'import_validation_enhancement' => true,
            'audit_integrity_enhancement' => true,
            'operational_report_enhancement' => true,
            'data_redaction_export_enhancement' => true,
            'schema_versioning_enhancement' => true,
            'health_baseline' => true,
            'drift_baseline_compare' => true,
            'write_safety_preflight' => true,
            'restore_safety_gate' => true,
            'backup_consistency_report' => true,
            'event_gap_report' => true,
            'corruption_suspect_report' => true,
            'operational_risk_score' => true,
            'recovery_decision_report' => true,
            'safe_mode' => true,
            'readonly_runtime_report' => true,
            'incident_timeline' => true,
            'write_intent_log' => true,
            'write_commit_verification' => true,
            'recovery_simulation' => true,
            'restore_impact_report' => true,
            'event_chain_integrity' => true,
            'snapshot_integrity_seal' => true,
            'operational_runbook_report' => true,
            'degraded_mode' => true,
            'critical_operation_guard' => true,
            'operational_evidence_bundle' => true,
            'pre_write_risk_evaluation' => true,
            'critical_write_two_step_guard' => true,
            'backup_restore_compatibility_check' => true,
            'snapshot_seal_verification' => true,
            'operational_degradation_reason' => true,
            'incident_severity_classification' => true,
            'recovery_readiness_report' => true,
            'operation_freeze_policy' => true,
            'data_durability_report' => true,
            'release_safety_evidence' => true,
            'operational_slo_report' => true,
            'write_failure_classification' => true,
            'backup_freshness_report' => true,
            'restore_candidate_ranking' => true,
            'read_model_confidence_report' => true,
            'operational_window_policy' => true,
            'recovery_drill_report' => true,
            'incident_evidence_digest' => true,
            'data_lifecycle_guard' => true,
            'operational_handoff_report' => true,
            'operational_baseline_snapshot' => true,
            'write_anomaly_detector' => true,
            'recovery_priority_report' => true,
            'operational_risk_timeline' => true,
            'data_consistency_score' => true,
            'backup_candidate_validation_matrix' => true,
            'write_safety_threshold_policy' => true,
            'incident_replay_summary' => true,
            'production_readiness_gate' => true,
            'operator_action_checklist' => true,
            'operational_drift_budget' => true,
            'write_blast_radius_report' => true,
            'recovery_path_comparison' => true,
            'data_integrity_attestation' => true,
            'incident_containment_policy' => true,
            'operational_regression_guard' => true,
            'backup_rotation_policy_report' => true,
            'state_transition_audit' => true,
            'critical_collection_profile' => true,
            'production_incident_packet' => true,
            'operational_health_trend' => true,
            'write_quarantine_recommendation' => true,
            'read_model_rebuild_safety_report' => true,
            'backup_trust_score' => true,
            'event_gap_detection' => true,
            'operational_saturation_report' => true,
            'safe_maintenance_window_report' => true,
            'data_recovery_confidence' => true,
            'incident_root_cause_hints' => true,
            'production_operation_summary' => true,
            'operation_readiness_ledger' => true,
            'write_admission_control_report' => true,
            'critical_record_watchlist' => true,
            'schema_stability_report' => true,
            'event_replay_feasibility_report' => true,
            'restore_dry_run_evidence' => true,
            'sqlite_operational_limits_report' => true,
            'incident_communication_summary' => true,
            'release_regression_evidence' => true,
            'production_safety_board' => true,
            'operational_control_tower' => true,
            'write_pressure_report' => true,
            'failure_recurrence_detector' => true,
            'restore_decision_checklist' => true,
            'event_chain_trust_report' => true,
            'read_consistency_verification' => true,
            'operational_evidence_timeline' => true,
            'degraded_mode_exit_criteria' => true,
            'backup_exposure_report' => true,
            'production_operations_packet' => true,
            'database_state_digest' => true,
            'write_readiness_check' => true,
            'restore_candidate_inspector' => true,
            'event_stream_integrity_summary' => true,
            'operational_status_board' => true,
            'maintenance_decision_report' => true,
            'backup_rotation_view' => true,
            'data_mutation_risk_report' => true,
            'read_model_rebuild_safety_check' => true,
            'incident_recovery_packet' => true,
            'operation_journal' => true,
            'recovery_confidence_score' => true,
            'schema_drift_guard' => true,
            'event_replay_proof' => true,
            'backup_trust_ledger' => true,
            'operational_freeze_reason' => true,
            'critical_path_check' => true,
            'data_loss_exposure_report' => true,
            'operator_handoff_note' => true,
            'write_contract_validator' => true,
            'event_causality_chain' => true,
            'snapshot_recovery_point' => true,
            'restore_conflict_preview' => true,
            'read_consistency_window' => true,
            'backup_completeness_check' => true,
            'operational_mode_matrix' => true,
            'critical_operation_approval_token' => true,
            'data_retention_policy_view' => true,
            'event_gap_repair_plan' => true,
            'schema_compatibility_matrix' => true,
            'recovery_timeline_simulator' => true,
            'incident_containment_view' => true,
            'production_readiness_ledger' => true,
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
        self::assertCollectionWritesAllowed($collection);
        self::recordWriteIntent('create', $collection, null, $data, false);
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
        self::assertCollectionWritesAllowed($collection);
        self::enforcePatchOperations($operations);
        self::recordWriteIntent('patch', $collection, $id, ['operations' => $operations], false);
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
        self::assertCollectionWritesAllowed($collection);
        self::recordWriteIntent('update', $collection, $id, $data, false);
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
        self::assertCollectionWritesAllowed($collection);
        self::assertCriticalOperationAllowed('delete', $collection);
        self::recordWriteIntent('delete', $collection, $id, [], true);
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
        self::assertCriticalOperationAllowed('restore_database');
        self::recordWriteIntent('restore_database', null, null, [
            'fingerprint' => $payload['fingerprint'] ?? null,
            'collection_count' => is_array($payload['collections'] ?? null) ? count($payload['collections']) : 0,
        ], true);
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
        $summary = [
            'duplicate_id' => 0,
            'schema_violation' => 0,
            'size_violation' => 0,
            'unknown_field' => 0,
        ];
        $schemaFields = array_keys(self::collections()[$collection]['schema']);
        foreach ($records as $position => $record) {
            if (!is_array($record)) {
                $errors[] = ['position' => $position, 'error' => 'record_must_be_array'];
                continue;
            }
            $id = (string)($record['id'] ?? '');
            if ($id !== '') {
                if (isset($seen[$id]) || self::get($collection, $id) !== null) {
                    $errors[] = ['position' => $position, 'error' => 'duplicate_id', 'id' => $id];
                    $summary['duplicate_id']++;
                }
                $seen[$id] = true;
            }
            $data = $record['data'] ?? $record;
            if (is_array($data) && $schemaFields !== []) {
                foreach (array_keys($data) as $field) {
                    if (!in_array((string)$field, $schemaFields, true) && $field !== 'id') {
                        $errors[] = ['position' => $position, 'error' => 'unknown_field', 'field' => (string)$field];
                        $summary['unknown_field']++;
                    }
                }
            }
            if (is_array($data) && strlen(self::encodeJson(self::stableData($data))) > self::writePolicy()['max_record_size_bytes']) {
                $errors[] = ['position' => $position, 'error' => 'size_violation'];
                $summary['size_violation']++;
            }
            try {
                self::normalizeDataForSchema($collection, is_array($data) ? $data : [], true);
            } catch (Throwable $exception) {
                $errors[] = ['position' => $position, 'error' => 'schema_violation', 'message' => $exception->getMessage()];
                $summary['schema_violation']++;
            }
        }

        return [
            'collection' => $collection,
            'valid' => $errors === [],
            'record_count' => count($records),
            'errors' => $errors,
            'summary' => $summary,
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
            'compatibility' => 'compatible',
            'migration_risk' => 'low',
            'previous_fingerprint' => null,
        ];
    }

    public static function changeFeedFilter(array $options = []): array
    {
        $collection = is_string($options['collection'] ?? null) ? (string)$options['collection'] : null;
        $events = self::events($options['after'] ?? null, $collection);
        $events = array_values(array_filter($events, static function (array $event) use ($options): bool {
            if (isset($options['type']) && $event['type'] !== $options['type']) {
                return false;
            }
            if (isset($options['record_id']) && $event['record_id'] !== $options['record_id']) {
                return false;
            }
            if (isset($options['from']) && strcmp((string)$event['id'], (string)$options['from']) < 0) {
                return false;
            }
            if (isset($options['to']) && strcmp((string)$event['id'], (string)$options['to']) > 0) {
                return false;
            }
            return true;
        }));

        return [
            'events' => $events,
            'count' => count($events),
            'cursor' => self::lastEventId($events) ?? ($options['after'] ?? null),
            'filter' => self::stableData($options),
        ];
    }

    public static function recordVersionHistory(string $collection, string $id): array
    {
        self::assertCollection($collection);
        $events = array_values(array_filter(
            self::events(null, $collection),
            static fn(array $event): bool => $event['record_id'] === $id
        ));

        return [
            'collection' => $collection,
            'id' => $id,
            'versions' => array_map(static fn(array $event): array => [
                'event_id' => $event['id'],
                'type' => $event['type'],
                'version' => $event['version'],
                'payload' => $event['payload'],
                'changed_fields' => $event['changed_fields'],
            ], $events),
            'count' => count($events),
            'latest_version' => $events === [] ? null : $events[array_key_last($events)]['version'],
        ];
    }

    public static function recordDiff(string $collection, string $id, int $leftVersion, int $rightVersion): array
    {
        $history = self::recordVersionHistory($collection, $id);
        $left = self::payloadForVersion($history['versions'], $leftVersion);
        $right = self::payloadForVersion($history['versions'], $rightVersion);
        $fields = array_unique(array_merge(array_keys($left), array_keys($right)));
        sort($fields);
        $diff = [];
        foreach ($fields as $field) {
            if (($left[$field] ?? null) !== ($right[$field] ?? null)) {
                $diff[$field] = [
                    'from' => $left[$field] ?? null,
                    'to' => $right[$field] ?? null,
                ];
            }
        }

        return [
            'collection' => $collection,
            'id' => $id,
            'left_version' => $leftVersion,
            'right_version' => $rightVersion,
            'changed_fields' => array_keys($diff),
            'diff' => $diff,
        ];
    }

    public static function snapshotRetentionPlan(): array
    {
        return [
            'planned' => true,
            'automatic_deletion' => false,
            'retention_basis' => 'snapshot_fingerprint_and_cursor',
        ];
    }

    public static function backupManifest(?array $payload = null): array
    {
        $payload ??= self::exportDatabase();

        return [
            'selected_database' => $payload['selected_database'] ?? null,
            'fingerprint' => $payload['fingerprint'] ?? null,
            'collection_count' => is_array($payload['collections'] ?? null) ? count($payload['collections']) : 0,
            'event_count' => is_array($payload['events'] ?? null) ? count($payload['events']) : 0,
            'cursor' => $payload['cursor'] ?? null,
            'valid' => self::validateDatabaseExport($payload)['valid'],
        ];
    }

    public static function restorePreview(array $payload): array
    {
        $validation = self::validateDatabaseExport($payload);
        if ($validation['valid'] !== true) {
            return ['valid' => false, 'dry_run' => true, 'errors' => $validation['errors']];
        }

        $current = self::exportDatabase();
        $summary = [];
        foreach (($payload['snapshots'] ?? []) as $collection => $snapshot) {
            $currentRecords = self::recordsById($current['snapshots'][$collection]['records'] ?? []);
            $nextRecords = self::recordsById(is_array($snapshot) ? ($snapshot['records'] ?? []) : []);
            $added = array_diff(array_keys($nextRecords), array_keys($currentRecords));
            $removed = array_diff(array_keys($currentRecords), array_keys($nextRecords));
            $updated = [];
            foreach (array_intersect(array_keys($currentRecords), array_keys($nextRecords)) as $id) {
                if (self::stableData($currentRecords[$id]['data'] ?? []) !== self::stableData($nextRecords[$id]['data'] ?? [])) {
                    $updated[] = $id;
                }
            }
            $summary[(string)$collection] = [
                'added' => count($added),
                'updated' => count($updated),
                'removed' => count($removed),
            ];
        }

        return [
            'valid' => true,
            'dry_run' => true,
            'collections' => $summary,
            'will_restore' => true,
        ];
    }

    public static function setCollectionLock(string $collection, bool $locked): array
    {
        self::assertCollection($collection);
        self::$collectionLocks[$collection] = $locked;

        return self::collectionLock($collection);
    }

    public static function collectionLock(string $collection): array
    {
        self::assertCollection($collection);
        $locked = self::$collectionLocks[$collection] ?? false;

        return [
            'collection' => $collection,
            'locked' => $locked,
            'write_allowed' => $locked === false,
        ];
    }

    public static function writeQuotaGuard(array $payload = []): array
    {
        $record = is_array($payload['record'] ?? null) ? $payload['record'] : [];
        $patchOperations = is_array($payload['patch_operations'] ?? null) ? $payload['patch_operations'] : [];
        $transactionOperations = is_array($payload['transaction_operations'] ?? null) ? $payload['transaction_operations'] : [];
        $policy = self::writePolicy();
        $recordSize = strlen(self::encodeJson(self::stableData($record)));

        return [
            'allowed' => $recordSize <= $policy['max_record_size_bytes']
                && count($patchOperations) <= $policy['max_patch_operations']
                && count($transactionOperations) <= $policy['max_transaction_operations'],
            'record_size_bytes' => $recordSize,
            'record_size_ok' => $recordSize <= $policy['max_record_size_bytes'],
            'patch_operations_ok' => count($patchOperations) <= $policy['max_patch_operations'],
            'transaction_operations_ok' => count($transactionOperations) <= $policy['max_transaction_operations'],
            'policy' => $policy,
        ];
    }

    public static function eventCheckpoint(?string $cursor = null): array
    {
        return AdlaireEventLog::eventCheckpoint(self::$events, $cursor);
    }

    public static function eventTypeRegistry(): array
    {
        return AdlaireEventLog::typeRegistry();
    }

    public static function eventCursorContract(?string $eventId = null): array
    {
        return AdlaireEventLog::cursorContract(self::$events, $eventId);
    }

    public static function eventReplayScope(
        ?string $domain = null,
        ?string $collection = null,
        ?string $recordId = null,
        ?int $fromSequence = null,
        ?int $toSequence = null
    ): array {
        return AdlaireEventLog::replayScope(self::$events, $domain, $collection, $recordId, $fromSequence, $toSequence);
    }

    public static function eventEvidence(): array
    {
        return AdlaireEventLog::evidence(self::$events);
    }

    public static function eventSnapshotLink(string $eventId, string $snapshotFingerprint): array
    {
        return AdlaireEventLog::snapshotLink(self::$events, $eventId, $snapshotFingerprint);
    }

    public static function eventImportValidation(array $events): array
    {
        return AdlaireEventLog::importValidation($events);
    }

    public static function eventExportPacket(): array
    {
        return AdlaireEventLog::exportPacket(self::$events);
    }

    public static function eventRetentionView(): array
    {
        return AdlaireEventLog::retentionView(self::$events);
    }

    public static function eventRiskReport(): array
    {
        return AdlaireEventLog::riskReport(self::$events);
    }

    public static function eventOperationJournal(string $operation, array $result = []): array
    {
        return AdlaireEventLog::operationJournal($operation, self::$events, $result);
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
        self::assertCriticalOperationAllowed('bulk_write');
        self::recordWriteIntent('bulk_write', null, null, ['operation_count' => count($operations)], true);
        return self::transaction($operations) + [
            'operation' => 'bulk_write',
            'applied' => true,
        ];
    }

    public static function restoreRecord(string $collection, array $record): array
    {
        self::assertCollection($collection);
        self::assertWritesAllowed();
        self::assertCollectionWritesAllowed($collection);
        self::assertCriticalOperationAllowed('record_restore', $collection);
        self::recordWriteIntent('record_restore', $collection, is_string($record['id'] ?? null) ? (string)$record['id'] : null, $record, true);
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
            'limit' => $limit,
            'previous_cursor' => $after,
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
            'redaction_policy_preview' => [
                'mode' => $redact === [] ? 'none' : 'field',
                'fields' => $redact,
            ],
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
        self::assertCriticalOperationAllowed('restore_snapshot', $collection);
        self::recordWriteIntent('restore_snapshot', $collection, null, [
            'fingerprint' => $payload['fingerprint'] ?? null,
            'record_count' => is_array($payload['snapshot']['records'] ?? null) ? count($payload['snapshot']['records']) : 0,
        ], true);
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

        return AdlaireEventLog::events(self::$events, $after, $collection);
    }

    public static function replay(string $collection, array $events): array
    {
        self::assertCollection($collection);
        return AdlaireEventLog::replay($collection, $events, self::collections()[$collection]);
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
        return AdlaireEventLog::cursor(self::$events);
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
        self::$safeMode = false;
        self::$degradedMode = false;
        self::$collectionLocks = [];
        self::$writeIntents = [];
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

    public static function setSafeMode(bool $enabled): array
    {
        self::$safeMode = $enabled;

        return self::readonlyRuntimeReport();
    }

    public static function setDegradedMode(bool $enabled): array
    {
        self::$degradedMode = $enabled;

        return self::degradedMode();
    }

    public static function maintenanceMode(): array
    {
        return [
            'enabled' => self::$maintenanceMode,
            'write_allowed' => self::$maintenanceMode === false && self::$safeMode === false,
            'mode' => self::$maintenanceMode ? 'maintenance' : 'normal',
        ];
    }

    public static function readonlyRuntimeReport(): array
    {
        return [
            'safe_mode' => self::$safeMode,
            'degraded_mode' => self::$degradedMode,
            'maintenance_mode' => self::$maintenanceMode,
            'collection_locks' => self::$collectionLocks,
            'storage' => self::storageStatus()['runtime_execution'],
            'write_allowed' => self::$safeMode === false
                && self::$maintenanceMode === false
                && !in_array(true, self::$collectionLocks, true),
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
            'degraded_mode' => self::degradedMode(),
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

    public static function healthBaseline(): array
    {
        $collections = [];
        foreach (array_keys(self::collections()) as $collection) {
            $stats = self::stats($collection);
            $collections[$collection] = [
                'record_count' => $stats['record_count'],
                'event_count' => $stats['event_count'],
                'latest_cursor' => $stats['latest_cursor'],
                'schema_fingerprint' => $stats['schema_fingerprint'],
                'snapshot_fingerprint' => $stats['snapshot_fingerprint'],
            ];
        }

        return [
            'record_count' => array_sum(array_map('count', self::$records)),
            'event_count' => count(self::$events),
            'latest_cursor' => self::cursor()['latest'],
            'collections' => $collections,
            'fingerprint' => hash('sha256', self::encodeJson($collections)),
        ];
    }

    public static function driftBaselineCompare(array $baseline): array
    {
        $current = self::healthBaseline();
        $changes = [];
        foreach (($current['collections'] ?? []) as $collection => $currentState) {
            $baselineState = $baseline['collections'][$collection] ?? null;
            if (!is_array($baselineState)) {
                $changes[$collection] = 'missing_in_baseline';
                continue;
            }
            if ($baselineState !== $currentState) {
                $changes[$collection] = [
                    'baseline' => $baselineState,
                    'current' => $currentState,
                ];
            }
        }

        return [
            'drift' => $changes !== [] || ($baseline['fingerprint'] ?? null) !== $current['fingerprint'],
            'changes' => $changes,
            'baseline_fingerprint' => $baseline['fingerprint'] ?? null,
            'current_fingerprint' => $current['fingerprint'],
        ];
    }

    public static function writeSafetyPreflight(string $collection, array $data = [], array $operations = []): array
    {
        self::assertCollection($collection);
        $quota = self::writeQuotaGuard([
            'record' => $data,
            'patch_operations' => $operations,
            'transaction_operations' => $operations,
        ]);
        $schemaValid = true;
        $schemaError = null;
        try {
            self::normalizeDataForSchema($collection, $data, true);
        } catch (Throwable $exception) {
            $schemaValid = false;
            $schemaError = $exception->getMessage();
        }
        $checks = [
            'safe_mode_off' => self::$safeMode === false,
            'maintenance_mode_off' => self::$maintenanceMode === false,
            'collection_unlocked' => (self::$collectionLocks[$collection] ?? false) === false,
            'quota_allowed' => $quota['allowed'] === true,
            'schema_valid' => $schemaValid,
            'cursor_safe' => self::cursorSafety(self::cursor()['latest'])['safe'] === true,
        ];

        return [
            'allowed' => self::all($checks),
            'collection' => $collection,
            'checks' => $checks,
            'quota' => $quota,
            'schema_error' => $schemaError,
        ];
    }

    public static function restoreSafetyGate(array $payload): array
    {
        $manifest = self::backupManifest($payload);
        $preview = self::restorePreview($payload);
        $consistency = self::backupConsistencyReport($payload);
        $checks = [
            'manifest_valid' => $manifest['valid'] === true,
            'preview_valid' => ($preview['valid'] ?? false) === true,
            'fingerprint_present' => isset($payload['fingerprint']) && is_string($payload['fingerprint']),
            'backup_consistent' => $consistency['consistent'] === true,
        ];

        return [
            'allowed' => self::all($checks),
            'checks' => $checks,
            'manifest' => $manifest,
            'preview' => $preview,
            'consistency' => $consistency,
        ];
    }

    public static function backupConsistencyReport(array $payload): array
    {
        $validation = self::validateDatabaseExport($payload);
        $eventReport = self::payloadEventGapReport($payload['events'] ?? []);
        $snapshotCount = is_array($payload['snapshots'] ?? null) ? count($payload['snapshots']) : 0;
        $collectionCount = is_array($payload['collections'] ?? null) ? count($payload['collections']) : 0;
        $errors = $validation['errors'];
        if ($snapshotCount !== $collectionCount) {
            $errors[] = 'collection_snapshot_count_mismatch';
        }
        if ($eventReport['valid'] !== true) {
            $errors[] = 'event_gap_detected';
        }

        return [
            'consistent' => $errors === [],
            'errors' => $errors,
            'collection_count' => $collectionCount,
            'snapshot_count' => $snapshotCount,
            'event_report' => $eventReport,
            'fingerprint_present' => isset($payload['fingerprint']),
        ];
    }

    public static function eventGapReport(): array
    {
        return AdlaireEventLog::gapReport(self::$events);
    }

    public static function corruptionSuspectReport(): array
    {
        $audit = self::auditIntegrity();
        $suspects = [];
        foreach ($audit['errors'] as $error) {
            $type = (string)($error['type'] ?? 'unknown');
            $suspects[] = [
                'type' => $type,
                'severity' => str_contains($type, 'hash') || str_contains($type, 'schema') ? 'high' : 'medium',
                'detail' => $error,
            ];
        }

        return [
            'suspected' => $suspects !== [],
            'suspects' => $suspects,
            'audit' => $audit,
        ];
    }

    public static function operationalRiskScore(): array
    {
        $score = 0;
        $audit = self::auditIntegrity();
        $eventGap = self::eventGapReport();
        if ($audit['valid'] !== true) {
            $score += 40;
        }
        if ($eventGap['valid'] !== true) {
            $score += 25;
        }
        if (self::$safeMode) {
            $score += 15;
        }
        if (self::$maintenanceMode) {
            $score += 10;
        }
        if (self::$degradedMode) {
            $score += 10;
        }
        if (in_array(true, self::$collectionLocks, true)) {
            $score += 10;
        }
        $score = min(100, $score);

        return [
            'score' => $score,
            'level' => $score >= 70 ? 'high' : ($score >= 30 ? 'medium' : 'low'),
            'audit_valid' => $audit['valid'],
            'event_gap_valid' => $eventGap['valid'],
            'safe_mode' => self::$safeMode,
            'maintenance_mode' => self::$maintenanceMode,
            'degraded_mode' => self::$degradedMode,
        ];
    }

    public static function recoveryDecisionReport(array $payload): array
    {
        $gate = self::restoreSafetyGate($payload);
        $risk = self::operationalRiskScore();
        $decision = 'continue_observation';
        if ($gate['allowed'] === true && $risk['score'] >= 70) {
            $decision = 'restore_candidate';
        } elseif ($risk['score'] >= 30) {
            $decision = 'keep_readonly_and_investigate';
        }

        return [
            'decision' => $decision,
            'restore_gate' => $gate,
            'risk' => $risk,
        ];
    }

    public static function eventLogConsistencyCheck(): array
    {
        return AdlaireEventLog::consistencyCheck(self::$events);
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
            'version' => self::VERSION,
            'guard' => self::operationalGuard(),
            'startup_self_check' => self::startupSelfCheck(),
            'metrics' => self::operationalMetrics(),
            'event_log' => self::eventLogConsistencyCheck(),
            'ttl_plan' => self::recordTtlPlan(),
            'subscriber_checkpoint_plan' => self::subscriberCheckpointPlan(),
            'incident_report' => self::operationalIncidentReport(),
            'snapshot_retention_plan' => self::snapshotRetentionPlan(),
            'event_chain_integrity' => self::eventChainIntegrity(),
            'runbook' => self::operationalRunbookReport(),
            'degradation_reason' => self::operationalDegradationReason(),
            'freeze_policy' => self::operationFreezePolicy(),
            'durability' => self::dataDurabilityReport(),
            'slo' => self::operationalSloReport(),
            'handoff' => self::operationalHandoffReport(),
        ];
    }

    public static function operationalIncidentReport(): array
    {
        return [
            'version' => self::VERSION,
            'audit' => self::auditIntegrity(),
            'event_log' => self::eventLogConsistencyCheck(),
            'cursor' => self::cursor(),
            'storage' => self::storageStatus(),
            'maintenance_mode' => self::maintenanceMode(),
            'readonly_runtime' => self::readonlyRuntimeReport(),
            'degraded_mode' => self::degradedMode(),
            'collection_locks' => self::$collectionLocks,
            'metrics' => self::operationalMetrics(),
        ];
    }

    public static function incidentTimeline(): array
    {
        $items = [];
        foreach (self::events() as $event) {
            $items[] = [
                'kind' => 'event',
                'sequence' => (int)$event['sequence'],
                'id' => $event['id'],
                'collection' => $event['collection'],
                'type' => $event['type'],
            ];
        }
        $items[] = [
            'kind' => 'runtime_state',
            'sequence' => count($items) + 1,
            'safe_mode' => self::$safeMode,
            'degraded_mode' => self::$degradedMode,
            'maintenance_mode' => self::$maintenanceMode,
            'collection_locks' => self::$collectionLocks,
            'checkpoint' => self::eventCheckpoint(),
        ];

        return [
            'items' => $items,
            'count' => count($items),
            'latest_cursor' => self::cursor()['latest'],
        ];
    }

    public static function writeIntentLog(): array
    {
        return [
            'intents' => self::$writeIntents,
            'count' => count(self::$writeIntents),
            'latest_intent' => self::$writeIntents === [] ? null : self::$writeIntents[array_key_last(self::$writeIntents)],
        ];
    }

    public static function writeCommitVerification(?array $intent = null): array
    {
        $intent ??= self::$writeIntents === [] ? [] : self::$writeIntents[array_key_last(self::$writeIntents)];
        $audit = self::auditIntegrity();
        $chain = self::eventChainIntegrity();
        $cursor = self::cursor()['latest'];
        $intentSequence = (int)($intent['event_sequence_before'] ?? 0);
        $committed = $intent === [] || self::$eventSequence > $intentSequence || ($intent['operation'] ?? null) === 'restore_snapshot';
        $checks = [
            'audit_valid' => $audit['valid'] === true,
            'event_chain_valid' => $chain['valid'] === true,
            'cursor_present_after_write' => $cursor !== null || $intent === [],
            'intent_committed' => $committed,
        ];

        return [
            'verified' => self::all($checks),
            'checks' => $checks,
            'intent' => $intent,
            'latest_cursor' => $cursor,
            'audit' => $audit,
            'event_chain' => $chain,
            'safe_mode_recommended' => !self::all($checks),
        ];
    }

    public static function recoverySimulation(array $payload): array
    {
        $validation = self::validateDatabaseExport($payload);
        $eventCount = is_array($payload['events'] ?? null) ? count($payload['events']) : 0;
        $recordCount = 0;
        foreach (($payload['snapshots'] ?? []) as $snapshot) {
            if (is_array($snapshot) && is_array($snapshot['records'] ?? null)) {
                $recordCount += count($snapshot['records']);
            }
        }
        $simulationPayload = [
            'selected_database' => $payload['selected_database'] ?? null,
            'collection_count' => is_array($payload['collections'] ?? null) ? count($payload['collections']) : 0,
            'record_count' => $recordCount,
            'event_count' => $eventCount,
            'cursor' => $payload['cursor'] ?? null,
        ];

        return [
            'valid' => $validation['valid'],
            'dry_run' => true,
            'will_restore' => false,
            'simulated' => $simulationPayload,
            'simulated_fingerprint' => hash('sha256', self::encodeJson($simulationPayload)),
            'errors' => $validation['errors'],
        ];
    }

    public static function restoreImpactReport(array $payload): array
    {
        $preview = self::restorePreview($payload);
        $totals = ['added' => 0, 'updated' => 0, 'removed' => 0];
        foreach (($preview['collections'] ?? []) as $summary) {
            if (!is_array($summary)) {
                continue;
            }
            foreach (array_keys($totals) as $key) {
                $totals[$key] += (int)($summary[$key] ?? 0);
            }
        }

        return [
            'valid' => ($preview['valid'] ?? false) === true,
            'dry_run' => true,
            'collections' => $preview['collections'] ?? [],
            'totals' => $totals,
            'will_restore' => false,
        ];
    }

    public static function eventChainIntegrity(): array
    {
        return AdlaireEventLog::eventChainIntegrity(self::$events);
    }

    public static function snapshotIntegritySeal(string $collection): array
    {
        self::assertCollection($collection);
        $snapshot = self::snapshot($collection);
        $stats = self::stats($collection);
        $payload = [
            'collection' => $collection,
            'record_fingerprint' => $snapshot['fingerprint'],
            'event_cursor' => $snapshot['cursor'],
            'schema_fingerprint' => $stats['schema_fingerprint'],
        ];

        return $payload + [
            'seal' => hash('sha256', self::encodeJson($payload)),
            'selected_database' => 'sqlite',
        ];
    }

    public static function operationalRunbookReport(): array
    {
        $risk = self::operationalRiskScore();
        $corruption = self::corruptionSuspectReport();
        $steps = [];
        if ($corruption['suspected'] === true || $risk['level'] === 'high') {
            $steps[] = 'enable_safe_mode';
            $steps[] = 'verify_backup';
            $steps[] = 'manual_restore_review';
        } elseif (self::$degradedMode || $risk['level'] === 'medium') {
            $steps[] = 'keep_degraded_mode';
            $steps[] = 'review_operational_evidence';
        } else {
            $steps[] = 'continue_observation';
        }

        return [
            'action' => $steps[0],
            'steps' => $steps,
            'risk' => $risk,
            'corruption' => $corruption,
            'automatic_repair' => false,
        ];
    }

    public static function degradedMode(): array
    {
        return [
            'enabled' => self::$degradedMode,
            'write_allowed' => self::$safeMode === false && self::$maintenanceMode === false,
            'critical_operations_allowed' => self::$degradedMode === false,
            'blocked_operations' => self::$degradedMode ? self::criticalOperations() : [],
        ];
    }

    public static function criticalOperationGuard(string $operation, ?string $collection = null): array
    {
        if ($collection !== null) {
            self::assertCollection($collection);
        }
        $critical = in_array($operation, self::criticalOperations(), true);
        $checks = [
            'safe_mode_off' => self::$safeMode === false,
            'maintenance_mode_off' => self::$maintenanceMode === false,
            'degraded_allows_operation' => !$critical || self::$degradedMode === false,
            'audit_valid' => self::auditIntegrity()['valid'] === true,
            'event_chain_valid' => self::eventChainIntegrity()['valid'] === true,
        ];
        if ($collection !== null) {
            $checks['collection_unlocked'] = (self::$collectionLocks[$collection] ?? false) === false;
        }

        return [
            'allowed' => self::all($checks),
            'operation' => $operation,
            'critical' => $critical,
            'collection' => $collection,
            'checks' => $checks,
        ];
    }

    public static function operationalEvidenceBundle(?array $backupPayload = null): array
    {
        $backupPayload ??= self::exportDatabase();

        return [
            'version' => self::VERSION,
            'baseline' => self::healthBaseline(),
            'risk' => self::operationalRiskScore(),
            'event_gap' => self::eventGapReport(),
            'event_chain' => self::eventChainIntegrity(),
            'backup_consistency' => self::backupConsistencyReport($backupPayload),
            'restore_impact' => self::restoreImpactReport($backupPayload),
            'timeline' => self::incidentTimeline(),
            'runbook' => self::operationalRunbookReport(),
            'fingerprint' => hash('sha256', self::encodeJson([
                'cursor' => self::cursor()['latest'],
                'risk' => self::operationalRiskScore()['score'],
                'event_chain' => self::eventChainIntegrity()['tip'],
                'backup' => $backupPayload['fingerprint'] ?? null,
            ])),
        ];
    }

    public static function preWriteRiskEvaluation(string $collection, array $data = [], array $operations = []): array
    {
        self::assertCollection($collection);
        $preflight = self::writeSafetyPreflight($collection, $data, $operations);
        $risk = self::operationalRiskScore();
        $chain = self::eventChainIntegrity();
        $schema = self::schemaVersioning($collection);
        $checks = [
            'preflight_allowed' => $preflight['allowed'] === true,
            'risk_not_high' => !in_array($risk['level'], ['high', 'critical'], true),
            'safe_mode_off' => self::$safeMode === false,
            'degraded_mode_off' => self::$degradedMode === false,
            'collection_unlocked' => (self::$collectionLocks[$collection] ?? false) === false,
            'event_chain_valid' => $chain['valid'] === true,
            'schema_compatible' => ($schema['compatibility'] ?? null) === 'compatible',
        ];

        return [
            'allowed' => self::all($checks),
            'collection' => $collection,
            'checks' => $checks,
            'preflight' => $preflight,
            'risk' => $risk,
            'event_chain' => $chain,
            'schema' => $schema,
        ];
    }

    public static function criticalWriteTwoStepGuard(string $operation, ?string $collection = null, ?array $intent = null): array
    {
        $guard = self::criticalOperationGuard($operation, $collection);
        $intent ??= self::writeIntentLog()['latest_intent'];
        $intentMatches = $intent === null || (
            ($intent['operation'] ?? null) === $operation
            && ($collection === null || ($intent['collection'] ?? null) === $collection)
        );
        $checks = $guard['checks'] + [
            'intent_present' => $intent !== null,
            'intent_matches_operation' => $intentMatches,
            'two_step_required' => $guard['critical'] === true,
        ];

        return [
            'allowed' => $guard['allowed'] === true && $intent !== null && $intentMatches === true,
            'operation' => $operation,
            'collection' => $collection,
            'critical' => $guard['critical'],
            'checks' => $checks,
            'intent' => $intent,
        ];
    }

    public static function backupRestoreCompatibilityCheck(array $payload): array
    {
        $validation = self::validateDatabaseExport($payload);
        $errors = $validation['errors'];
        $warnings = [];
        if (($payload['selected_database'] ?? null) !== 'sqlite') {
            $errors[] = 'selected_database_incompatible';
        }
        $currentCollections = array_keys(self::collections());
        $backupCollections = is_array($payload['collections'] ?? null) ? array_keys($payload['collections']) : [];
        sort($currentCollections);
        sort($backupCollections);
        if ($backupCollections === []) {
            $errors[] = 'backup_collections_missing';
        }
        $missingCurrent = array_values(array_diff($currentCollections, $backupCollections));
        $extraBackup = array_values(array_diff($backupCollections, $currentCollections));
        if ($missingCurrent !== [] || $extraBackup !== []) {
            $warnings[] = 'collection_set_differs';
        }
        $cursorSafe = self::cursorSafety(is_string($payload['cursor'] ?? null) ? (string)$payload['cursor'] : null);
        $snapshotSeals = [];
        foreach (($payload['snapshots'] ?? []) as $collection => $snapshot) {
            if (is_array($snapshot) && isset(self::collections()[(string)$collection])) {
                $snapshotSeals[(string)$collection] = self::snapshotIntegritySeal((string)$collection)['seal'];
            }
        }

        return [
            'compatible' => $errors === [],
            'valid' => $validation['valid'],
            'errors' => array_values(array_unique($errors)),
            'warnings' => $warnings,
            'current_collections' => $currentCollections,
            'backup_collections' => $backupCollections,
            'cursor_safe' => $cursorSafe['safe'],
            'snapshot_seals' => $snapshotSeals,
        ];
    }

    public static function snapshotSealVerification(string $collection, array $seal): array
    {
        self::assertCollection($collection);
        $current = self::snapshotIntegritySeal($collection);
        $checks = [
            'collection_matches' => ($seal['collection'] ?? null) === $collection,
            'seal_matches' => ($seal['seal'] ?? null) === $current['seal'],
            'schema_matches' => ($seal['schema_fingerprint'] ?? null) === $current['schema_fingerprint'],
            'cursor_matches' => ($seal['event_cursor'] ?? null) === $current['event_cursor'],
            'record_fingerprint_matches' => ($seal['record_fingerprint'] ?? null) === $current['record_fingerprint'],
        ];

        return [
            'valid' => self::all($checks),
            'collection' => $collection,
            'checks' => $checks,
            'expected' => $current,
            'actual' => $seal,
        ];
    }

    public static function operationalDegradationReason(?array $backupPayload = null): array
    {
        $backupPayload ??= self::exportDatabase();
        $reasons = [];
        if (self::$safeMode) {
            $reasons[] = 'safe_mode_enabled';
        }
        if (self::$degradedMode) {
            $reasons[] = 'degraded_mode_enabled';
        }
        if (self::eventChainIntegrity()['valid'] !== true) {
            $reasons[] = 'event_chain_invalid';
        }
        if (self::backupConsistencyReport($backupPayload)['consistent'] !== true) {
            $reasons[] = 'backup_inconsistent';
        }
        if (self::operationalRiskScore()['level'] !== 'low') {
            $reasons[] = 'operational_risk_not_low';
        }
        foreach (array_keys(self::collections()) as $collection) {
            if (self::readModelDriftDetection($collection)['drift'] === true) {
                $reasons[] = 'schema_or_read_model_drift';
                break;
            }
        }

        return [
            'degraded' => $reasons !== [],
            'reasons' => array_values(array_unique($reasons)),
            'safe_mode' => self::$safeMode,
            'degraded_mode' => self::$degradedMode,
        ];
    }

    public static function incidentSeverityClassification(): array
    {
        $risk = self::operationalRiskScore();
        $corruption = self::corruptionSuspectReport();
        $gap = self::eventGapReport();
        $severity = 'low';
        if ($corruption['suspected'] === true || $risk['score'] >= 90) {
            $severity = 'critical';
        } elseif ($risk['score'] >= 70 || $gap['valid'] !== true) {
            $severity = 'high';
        } elseif ($risk['score'] >= 30 || self::$degradedMode) {
            $severity = 'medium';
        }

        return [
            'severity' => $severity,
            'risk' => $risk,
            'corruption' => $corruption,
            'event_gap' => $gap,
            'timeline_count' => self::incidentTimeline()['count'],
        ];
    }

    public static function recoveryReadinessReport(array $payload): array
    {
        $gate = self::restoreSafetyGate($payload);
        $consistency = self::backupConsistencyReport($payload);
        $impact = self::restoreImpactReport($payload);
        $simulation = self::recoverySimulation($payload);
        $chain = self::eventChainIntegrity();
        $compatibility = self::backupRestoreCompatibilityCheck($payload);
        $checks = [
            'restore_gate_allowed' => $gate['allowed'] === true,
            'backup_consistent' => $consistency['consistent'] === true,
            'impact_valid' => $impact['valid'] === true,
            'simulation_valid' => $simulation['valid'] === true,
            'event_chain_valid' => $chain['valid'] === true,
            'compatible' => $compatibility['compatible'] === true,
        ];
        $status = self::all($checks) ? 'ready' : ($simulation['valid'] === true ? 'manual_review_required' : 'blocked');

        return [
            'status' => $status,
            'checks' => $checks,
            'restore_gate' => $gate,
            'backup_consistency' => $consistency,
            'restore_impact' => $impact,
            'recovery_simulation' => $simulation,
            'event_chain' => $chain,
            'compatibility' => $compatibility,
        ];
    }

    public static function operationFreezePolicy(): array
    {
        $risk = self::operationalRiskScore();
        $criticalBlocked = self::$safeMode || self::$degradedMode || $risk['level'] !== 'low';

        return [
            'read_allowed' => true,
            'normal_write_allowed' => self::$safeMode === false && self::$maintenanceMode === false,
            'critical_write_allowed' => $criticalBlocked === false,
            'restore_allowed' => $criticalBlocked === false,
            'blocked_operations' => $criticalBlocked ? self::criticalOperations() : [],
            'risk' => $risk,
        ];
    }

    public static function dataDurabilityReport(?array $backupPayload = null): array
    {
        $backupPayload ??= self::exportDatabase();
        $storage = self::storageStatus();
        $seals = [];
        foreach (array_keys(self::collections()) as $collection) {
            $seals[$collection] = self::snapshotIntegritySeal($collection);
        }
        $checks = [
            'sqlite_selected' => $storage['selected_database'] === 'sqlite',
            'runtime_available' => in_array($storage['runtime_execution'], ['sqlite_persistent', 'in_memory'], true),
            'wal_ok_or_in_memory' => $storage['runtime_execution'] === 'in_memory' || $storage['wal_mode'] === true,
            'integrity_ok_or_not_checked' => in_array($storage['integrity_check'], ['ok', 'not_checked'], true),
            'backup_fingerprint_present' => isset($backupPayload['fingerprint']) && is_string($backupPayload['fingerprint']),
            'event_chain_valid' => self::eventChainIntegrity()['valid'] === true,
        ];

        return [
            'durable' => self::all($checks),
            'checks' => $checks,
            'storage' => $storage,
            'event_count' => count(self::$events),
            'snapshot_seals' => $seals,
            'backup_fingerprint' => $backupPayload['fingerprint'] ?? null,
        ];
    }

    public static function releaseSafetyEvidence(?array $backupPayload = null): array
    {
        $backupPayload ??= self::exportDatabase();
        $readiness = self::readiness();
        $risk = self::operationalRiskScore();
        $chain = self::eventChainIntegrity();
        $consistency = self::backupConsistencyReport($backupPayload);
        $durability = self::dataDurabilityReport($backupPayload);
        $runbook = self::operationalRunbookReport();
        $checks = [
            'readiness_ready' => $readiness['ready'] === true,
            'risk_low' => $risk['level'] === 'low',
            'event_chain_valid' => $chain['valid'] === true,
            'backup_consistent' => $consistency['consistent'] === true,
            'durable' => $durability['durable'] === true,
        ];

        return [
            'safe' => self::all($checks),
            'version' => self::VERSION,
            'checks' => $checks,
            'readiness' => $readiness,
            'risk' => $risk,
            'event_chain' => $chain,
            'backup_consistency' => $consistency,
            'durability' => $durability,
            'runbook' => $runbook,
            'fingerprint' => hash('sha256', self::encodeJson([
                'version' => self::VERSION,
                'checks' => $checks,
                'cursor' => self::cursor()['latest'],
                'backup' => $backupPayload['fingerprint'] ?? null,
            ])),
        ];
    }

    public static function operationalSloReport(?array $backupPayload = null): array
    {
        $backupPayload ??= self::exportDatabase();
        $items = [
            'readiness' => self::readiness()['ready'] === true,
            'event_chain' => self::eventChainIntegrity()['valid'] === true,
            'write_safety' => self::operationFreezePolicy()['normal_write_allowed'] === true,
            'durability' => self::dataDurabilityReport($backupPayload)['durable'] === true,
            'backup_consistency' => self::backupConsistencyReport($backupPayload)['consistent'] === true,
        ];
        $failed = array_keys(array_filter($items, static fn(bool $passed): bool => $passed === false));
        $status = $failed === [] ? 'met' : (count($failed) <= 1 ? 'warning' : 'breach');

        return [
            'status' => $status,
            'items' => $items,
            'failed' => $failed,
        ];
    }

    public static function writeFailureClassification(Throwable|string $failure): array
    {
        $message = $failure instanceof Throwable ? $failure->getMessage() : $failure;
        $normalized = strtolower($message);
        $classification = 'unknown';
        if (str_contains($normalized, 'schema')) {
            $classification = 'schema_error';
        } elseif (str_contains($normalized, 'locked')) {
            $classification = 'locked';
        } elseif (str_contains($normalized, 'safe mode')) {
            $classification = 'safe_mode';
        } elseif (str_contains($normalized, 'maintenance')) {
            $classification = 'policy_violation';
        } elseif (str_contains($normalized, 'critical operation')) {
            $classification = 'critical_guard';
        } elseif (str_contains($normalized, 'policy') || str_contains($normalized, 'limit') || str_contains($normalized, 'exceed')) {
            $classification = 'policy_violation';
        }

        return [
            'classification' => $classification,
            'message' => $message,
            'retryable' => in_array($classification, ['locked', 'safe_mode', 'critical_guard'], true),
        ];
    }

    public static function backupFreshnessReport(array $payload): array
    {
        $validation = self::validateDatabaseExport($payload);
        if ($validation['valid'] !== true) {
            return [
                'status' => 'invalid',
                'valid' => false,
                'errors' => $validation['errors'],
            ];
        }
        $current = self::exportDatabase();
        $backupEventCount = is_array($payload['events'] ?? null) ? count($payload['events']) : 0;
        $currentEventCount = count($current['events']);
        $fresh = ($payload['cursor'] ?? null) === $current['cursor']
            && $backupEventCount === $currentEventCount
            && ($payload['fingerprint'] ?? null) === $current['fingerprint'];

        return [
            'status' => $fresh ? 'fresh' : 'stale',
            'valid' => true,
            'backup_cursor' => $payload['cursor'] ?? null,
            'current_cursor' => $current['cursor'],
            'backup_event_count' => $backupEventCount,
            'current_event_count' => $currentEventCount,
            'fingerprint_match' => ($payload['fingerprint'] ?? null) === $current['fingerprint'],
        ];
    }

    public static function restoreCandidateRanking(array $candidates): array
    {
        $ranked = [];
        foreach ($candidates as $index => $candidate) {
            if (!is_array($candidate)) {
                $ranked[] = ['index' => $index, 'score' => -100, 'valid' => false, 'status' => 'invalid'];
                continue;
            }
            $freshness = self::backupFreshnessReport($candidate);
            $compatibility = self::backupRestoreCompatibilityCheck($candidate);
            $impact = self::restoreImpactReport($candidate);
            $score = 0;
            $score += ($freshness['valid'] ?? false) === true ? 40 : -40;
            $score += ($freshness['status'] ?? null) === 'fresh' ? 30 : 0;
            $score += $compatibility['compatible'] === true ? 20 : -20;
            $score -= array_sum(array_map('intval', $impact['totals'] ?? []));
            $ranked[] = [
                'index' => $index,
                'score' => $score,
                'valid' => ($freshness['valid'] ?? false) === true,
                'status' => $freshness['status'] ?? 'invalid',
                'freshness' => $freshness,
                'compatibility' => $compatibility,
                'impact' => $impact,
            ];
        }
        usort($ranked, static fn(array $a, array $b): int => ($b['score'] <=> $a['score']) ?: ($a['index'] <=> $b['index']));

        return [
            'candidates' => $ranked,
            'best' => $ranked[0] ?? null,
            'automatic_restore' => false,
        ];
    }

    public static function readModelConfidenceReport(string $collection): array
    {
        self::assertCollection($collection);
        $drift = self::readModelDriftDetection($collection);
        $seal = self::snapshotIntegritySeal($collection);
        $verification = self::snapshotSealVerification($collection, $seal);
        $chain = self::eventChainIntegrity();
        $confidence = 'high';
        if ($drift['drift'] === true || $verification['valid'] !== true) {
            $confidence = 'low';
        } elseif ($chain['valid'] !== true) {
            $confidence = 'medium';
        }

        return [
            'collection' => $collection,
            'confidence' => $confidence,
            'drift' => $drift,
            'snapshot_seal' => $seal,
            'seal_verification' => $verification,
            'event_chain' => $chain,
        ];
    }

    public static function operationalWindowPolicy(?array $backupPayload = null): array
    {
        $backupPayload ??= self::exportDatabase();
        $severity = self::incidentSeverityClassification();
        $freeze = self::operationFreezePolicy();
        $backupFreshness = self::backupFreshnessReport($backupPayload);
        $normalWriteAllowed = $freeze['normal_write_allowed'] === true && in_array($severity['severity'], ['low', 'medium'], true);
        $criticalAllowed = $freeze['critical_write_allowed'] === true && $severity['severity'] === 'low';

        return [
            'normal_write_allowed' => $normalWriteAllowed,
            'critical_operation_allowed' => $criticalAllowed,
            'restore_allowed' => $criticalAllowed && $backupFreshness['status'] !== 'invalid',
            'backup_verification_allowed' => true,
            'severity' => $severity,
            'freeze_policy' => $freeze,
            'backup_freshness' => $backupFreshness,
        ];
    }

    public static function recoveryDrillReport(array $payload): array
    {
        return [
            'drill' => true,
            'will_restore' => false,
            'restore_safety' => self::restoreSafetyGate($payload),
            'simulation' => self::recoverySimulation($payload),
            'impact' => self::restoreImpactReport($payload),
            'compatibility' => self::backupRestoreCompatibilityCheck($payload),
            'readiness' => self::recoveryReadinessReport($payload),
        ];
    }

    public static function incidentEvidenceDigest(?array $backupPayload = null): array
    {
        $backupPayload ??= self::exportDatabase();
        $durability = self::dataDurabilityReport($backupPayload);

        return [
            'severity' => self::incidentSeverityClassification()['severity'],
            'risk' => self::operationalRiskScore(),
            'latest_cursor' => self::cursor()['latest'],
            'event_chain_tip' => self::eventChainIntegrity()['tip'],
            'durable' => $durability['durable'],
            'freeze_policy' => self::operationFreezePolicy(),
        ];
    }

    public static function dataLifecycleGuard(string $operation, ?string $collection = null): array
    {
        $critical = in_array($operation, self::criticalOperations(), true);
        $guard = self::criticalOperationGuard($operation, $collection);
        $freeze = self::operationFreezePolicy();
        $checks = $guard['checks'] + [
            'lifecycle_operation' => $critical,
            'freeze_allows_critical' => !$critical || $freeze['critical_write_allowed'] === true,
        ];

        return [
            'allowed' => self::all($checks),
            'operation' => $operation,
            'collection' => $collection,
            'checks' => $checks,
            'ttl_runtime_enforced' => false,
            'automatic_delete' => false,
            'guard' => $guard,
            'freeze_policy' => $freeze,
        ];
    }

    public static function operationalHandoffReport(?array $backupPayload = null): array
    {
        $backupPayload ??= self::exportDatabase();
        $severity = self::incidentSeverityClassification();
        $runbook = self::operationalRunbookReport();
        $readiness = self::recoveryReadinessReport($backupPayload);
        $freeze = self::operationFreezePolicy();
        $nextAction = $runbook['action'];
        if ($severity['severity'] === 'critical') {
            $nextAction = 'escalate_and_freeze';
        } elseif ($readiness['status'] !== 'ready') {
            $nextAction = 'manual_recovery_review';
        }

        return [
            'current_status' => self::operationalSloReport($backupPayload)['status'],
            'severity' => $severity,
            'freeze_policy' => $freeze,
            'runbook' => $runbook,
            'recovery_readiness' => $readiness,
            'next_action' => $nextAction,
        ];
    }

    public static function operationalBaselineSnapshot(?array $backupPayload = null): array
    {
        $backupPayload ??= self::exportDatabase();

        return [
            'version' => self::VERSION,
            'baseline' => self::healthBaseline(),
            'event_chain' => self::eventChainIntegrity(),
            'snapshot_seals' => self::snapshotSeals(),
            'slo' => self::operationalSloReport($backupPayload),
            'freeze_policy' => self::operationFreezePolicy(),
            'fingerprint' => hash('sha256', self::encodeJson([
                'baseline' => self::healthBaseline()['fingerprint'],
                'event_chain' => self::eventChainIntegrity()['tip'],
                'slo' => self::operationalSloReport($backupPayload)['status'],
            ])),
        ];
    }

    public static function writeAnomalyDetector(): array
    {
        $intents = self::writeIntentLog()['intents'];
        $critical = 0;
        $byOperation = [];
        $byCollection = [];
        foreach ($intents as $intent) {
            $operation = (string)($intent['operation'] ?? 'unknown');
            $collection = (string)($intent['collection'] ?? 'none');
            $byOperation[$operation] = ($byOperation[$operation] ?? 0) + 1;
            $byCollection[$collection] = ($byCollection[$collection] ?? 0) + 1;
            if (($intent['critical'] ?? false) === true) {
                $critical++;
            }
        }
        $failures = array_values(array_filter($intents, static fn(array $intent): bool => ($intent['committed'] ?? true) === false));
        $anomalies = [];
        if (count($failures) > 0) {
            $anomalies[] = 'write_failure_present';
        }
        if ($critical >= 10) {
            $anomalies[] = 'critical_write_pressure';
        }
        if (count($intents) >= 50) {
            $anomalies[] = 'high_write_volume';
        }

        return [
            'status' => $anomalies === [] ? 'normal' : 'watch',
            'anomalies' => $anomalies,
            'intent_count' => count($intents),
            'failure_count' => count($failures),
            'critical_count' => $critical,
            'by_operation' => $byOperation,
            'by_collection' => $byCollection,
            'automatic_repair' => false,
        ];
    }

    public static function recoveryPriorityReport(?array $backupPayload = null): array
    {
        $backupPayload ??= self::exportDatabase();
        $items = [];
        foreach (array_keys(self::collections()) as $collection) {
            $profile = self::criticalCollectionProfile($collection);
            $items[] = [
                'collection' => $collection,
                'priority' => $profile['priority'],
                'priority_score' => $profile['priority_score'],
                'risk' => $profile['risk'],
                'record_count' => $profile['record_count'],
                'restore_impact' => $profile['restore_impact'],
            ];
        }
        usort($items, static fn(array $a, array $b): int => ($b['priority_score'] ?? 0) <=> ($a['priority_score'] ?? 0));

        return [
            'items' => $items,
            'backup_freshness' => self::backupFreshnessReport($backupPayload),
            'automatic_restore' => false,
        ];
    }

    public static function operationalRiskTimeline(?array $backupPayload = null): array
    {
        $backupPayload ??= self::exportDatabase();
        $items = self::incidentTimeline()['items'];
        $items[] = ['kind' => 'slo', 'sequence' => count($items) + 1, 'status' => self::operationalSloReport($backupPayload)['status']];
        $items[] = ['kind' => 'freeze_policy', 'sequence' => count($items) + 1, 'critical_write_allowed' => self::operationFreezePolicy()['critical_write_allowed']];
        $items[] = ['kind' => 'backup_freshness', 'sequence' => count($items) + 1, 'status' => self::backupFreshnessReport($backupPayload)['status']];

        return [
            'items' => $items,
            'count' => count($items),
            'latest_cursor' => self::cursor()['latest'],
        ];
    }

    public static function dataConsistencyScore(?array $backupPayload = null): array
    {
        $backupPayload ??= self::exportDatabase();
        $checks = [
            'audit' => self::auditIntegrity()['valid'] === true,
            'event_chain' => self::eventChainIntegrity()['valid'] === true,
            'event_gap' => self::eventGapReport()['valid'] === true,
            'backup_fresh' => self::backupFreshnessReport($backupPayload)['status'] === 'fresh',
            'durable' => self::dataDurabilityReport($backupPayload)['durable'] === true,
        ];
        $score = 0;
        foreach ($checks as $passed) {
            $score += $passed ? 20 : 0;
        }

        return [
            'score' => $score,
            'status' => $score >= 90 ? 'strong' : ($score >= 70 ? 'caution' : 'weak'),
            'checks' => $checks,
        ];
    }

    public static function backupCandidateValidationMatrix(array $candidates): array
    {
        $rows = [];
        foreach ($candidates as $index => $candidate) {
            if (!is_array($candidate)) {
                $rows[] = ['index' => $index, 'valid' => false, 'status' => 'invalid'];
                continue;
            }
            $freshness = self::backupFreshnessReport($candidate);
            $compatibility = self::backupRestoreCompatibilityCheck($candidate);
            $trust = self::backupTrustScore($candidate);
            $rows[] = [
                'index' => $index,
                'valid' => ($freshness['valid'] ?? false) === true,
                'freshness' => $freshness['status'] ?? 'invalid',
                'compatible' => $compatibility['compatible'],
                'trust_score' => $trust['score'],
                'status' => $trust['status'],
            ];
        }

        return [
            'matrix' => $rows,
            'ranking' => self::restoreCandidateRanking($candidates),
            'automatic_selection' => false,
        ];
    }

    public static function writeSafetyThresholdPolicy(): array
    {
        $anomaly = self::writeAnomalyDetector();
        $risk = self::operationalRiskScore();
        $checks = [
            'risk_below_high' => $risk['level'] !== 'high',
            'failure_count_below_threshold' => $anomaly['failure_count'] < 3,
            'critical_count_below_threshold' => $anomaly['critical_count'] < 10,
            'safe_mode_off' => self::$safeMode === false,
        ];

        return [
            'status' => self::all($checks) ? 'within_threshold' : 'threshold_exceeded',
            'checks' => $checks,
            'risk' => $risk,
            'anomaly' => $anomaly,
            'freeze_recommended' => !self::all($checks),
        ];
    }

    public static function incidentReplaySummary(): array
    {
        return [
            'summary_only' => true,
            'will_replay' => false,
            'event_count' => count(self::$events),
            'latest_cursor' => self::cursor()['latest'],
            'timeline' => self::incidentTimeline(),
            'event_gap' => self::eventGapReport(),
        ];
    }

    public static function productionReadinessGate(?array $backupPayload = null): array
    {
        $backupPayload ??= self::exportDatabase();
        $checks = [
            'readiness' => self::readiness()['ready'] === true,
            'slo_met' => self::operationalSloReport($backupPayload)['status'] === 'met',
            'durable' => self::dataDurabilityReport($backupPayload)['durable'] === true,
            'backup_fresh' => self::backupFreshnessReport($backupPayload)['status'] === 'fresh',
            'release_safe' => self::releaseSafetyEvidence($backupPayload)['safe'] === true,
            'lifecycle_guard' => self::dataLifecycleGuard('record_restore', null)['allowed'] === true,
        ];
        $failed = array_keys(array_filter($checks, static fn(bool $passed): bool => !$passed));
        $status = $failed === [] ? 'ready' : (count($failed) <= 1 ? 'caution' : 'blocked');

        return [
            'status' => $status,
            'checks' => $checks,
            'failed' => $failed,
            'automatic_release' => false,
        ];
    }

    public static function operatorActionChecklist(?array $backupPayload = null): array
    {
        $backupPayload ??= self::exportDatabase();
        $gate = self::productionReadinessGate($backupPayload);
        $actions = ['review_safety_board'];
        if ($gate['status'] !== 'ready') {
            $actions[] = 'review_failed_readiness_checks';
        }
        if (self::operationFreezePolicy()['critical_write_allowed'] !== true) {
            $actions[] = 'keep_critical_operations_paused';
        }
        if (self::backupFreshnessReport($backupPayload)['status'] !== 'fresh') {
            $actions[] = 'verify_backup_candidate';
        }
        if ($actions === ['review_safety_board']) {
            $actions[] = 'continue_observation';
        }

        return [
            'actions' => array_values(array_unique($actions)),
            'gate' => $gate,
            'automatic_execution' => false,
        ];
    }

    public static function operationalDriftBudget(?array $backupPayload = null): array
    {
        $backupPayload ??= self::exportDatabase();
        $driftCount = 0;
        foreach (array_keys(self::collections()) as $collection) {
            if (self::readModelDriftDetection($collection)['drift'] === true) {
                $driftCount++;
            }
        }
        $signals = [
            'read_model_drift' => $driftCount,
            'backup_stale' => self::backupFreshnessReport($backupPayload)['status'] === 'stale' ? 1 : 0,
            'slo_warning' => self::operationalSloReport($backupPayload)['status'] === 'warning' ? 1 : 0,
            'write_anomaly' => self::writeAnomalyDetector()['status'] === 'watch' ? 1 : 0,
        ];
        $used = array_sum($signals);

        return [
            'status' => $used === 0 ? 'within_budget' : ($used <= 2 ? 'near_limit' : 'exceeded'),
            'used' => $used,
            'limit' => 2,
            'signals' => $signals,
        ];
    }

    public static function writeBlastRadiusReport(string $collection, ?string $recordId = null): array
    {
        self::assertCollection($collection);

        return [
            'collection' => $collection,
            'record_id' => $recordId,
            'affected_collection' => $collection,
            'affected_record' => $recordId,
            'event_count' => count(self::events(null, $collection)),
            'snapshot' => self::snapshot($collection)['fingerprint'],
            'read_model_confidence' => self::readModelConfidenceReport($collection)['confidence'],
        ];
    }

    public static function recoveryPathComparison(array $payload): array
    {
        return [
            'paths' => [
                'backup_restore' => self::recoveryReadinessReport($payload)['status'],
                'snapshot_rebuild' => self::eventReplayFeasibilityReport()['status'],
                'read_model_rebuild' => self::readModelRebuildSafetyReport(array_key_first(self::collections()))['status'],
                'manual_verification' => 'available',
            ],
            'recommended' => self::dataRecoveryConfidence($payload)['confidence'] === 'high' ? 'manual_verified_restore_candidate' : 'manual_review',
            'automatic_execution' => false,
        ];
    }

    public static function dataIntegrityAttestation(?array $backupPayload = null): array
    {
        $backupPayload ??= self::exportDatabase();

        return [
            'valid' => self::dataConsistencyScore($backupPayload)['score'] >= 90,
            'event_chain' => self::eventChainIntegrity(),
            'snapshot_seals' => self::snapshotSeals(),
            'backup_fingerprint' => $backupPayload['fingerprint'] ?? null,
            'schema_state' => self::migrationPlan(),
            'fingerprint' => hash('sha256', self::encodeJson([
                self::eventChainIntegrity()['tip'],
                $backupPayload['fingerprint'] ?? null,
                self::snapshotSeals(),
            ])),
        ];
    }

    public static function incidentContainmentPolicy(?array $backupPayload = null): array
    {
        $backupPayload ??= self::exportDatabase();
        $severity = self::incidentSeverityClassification()['severity'];
        $freeze = self::operationFreezePolicy();
        $policy = 'observe';
        if (in_array($severity, ['high', 'critical'], true) || $freeze['critical_write_allowed'] !== true) {
            $policy = 'contain_writes';
        } elseif (self::backupFreshnessReport($backupPayload)['status'] !== 'fresh') {
            $policy = 'verify_backup_before_critical_write';
        }

        return [
            'policy' => $policy,
            'severity' => $severity,
            'freeze_policy' => $freeze,
            'automatic_freeze' => false,
        ];
    }

    public static function operationalRegressionGuard(?array $baseline = null): array
    {
        $baseline ??= self::healthBaseline();
        $drift = self::driftBaselineCompare($baseline);
        $checks = [
            'readiness_ready' => self::readiness()['ready'] === true,
            'durability_ok' => self::dataDurabilityReport()['durable'] === true,
            'release_evidence_safe' => self::releaseSafetyEvidence()['safe'] === true,
            'baseline_not_degraded' => $drift['drift'] === false,
        ];

        return [
            'regressed' => !self::all($checks),
            'checks' => $checks,
            'drift' => $drift,
        ];
    }

    public static function backupRotationPolicyReport(array $candidates): array
    {
        $matrix = self::backupCandidateValidationMatrix($candidates);
        $freshCount = count(array_filter($matrix['matrix'], static fn(array $row): bool => ($row['freshness'] ?? null) === 'fresh'));

        return [
            'candidate_count' => count($candidates),
            'fresh_count' => $freshCount,
            'status' => $freshCount > 0 ? 'covered' : 'needs_backup_review',
            'matrix' => $matrix,
            'automatic_delete' => false,
        ];
    }

    public static function stateTransitionAudit(): array
    {
        $state = 'normal';
        $reason = 'healthy';
        if (self::$safeMode) {
            $state = 'frozen';
            $reason = 'safe_mode_enabled';
        } elseif (self::$degradedMode) {
            $state = 'degraded';
            $reason = 'degraded_mode_enabled';
        } elseif (self::operationalRiskScore()['level'] !== 'low') {
            $state = 'recovery_required';
            $reason = 'risk_not_low';
        }

        return [
            'state' => $state,
            'reason' => $reason,
            'automatic_transition' => false,
        ];
    }

    public static function criticalCollectionProfile(string $collection): array
    {
        self::assertCollection($collection);
        $stats = self::stats($collection);
        $riskScore = self::operationalRiskScore()['score'] + min(30, $stats['event_count']);

        return [
            'collection' => $collection,
            'priority' => $riskScore >= 50 ? 'high' : ($riskScore >= 20 ? 'medium' : 'low'),
            'priority_score' => $riskScore,
            'risk' => self::operationalRiskScore(),
            'record_count' => $stats['record_count'],
            'event_count' => $stats['event_count'],
            'restore_impact' => self::restoreImpactReport(self::exportDatabase())['totals'],
            'read_model_confidence' => self::readModelConfidenceReport($collection)['confidence'],
        ];
    }

    public static function productionIncidentPacket(?array $backupPayload = null): array
    {
        $backupPayload ??= self::exportDatabase();

        return [
            'severity' => self::incidentSeverityClassification(),
            'risk_timeline' => self::operationalRiskTimeline($backupPayload),
            'evidence_digest' => self::incidentEvidenceDigest($backupPayload),
            'restore_candidate_ranking' => self::restoreCandidateRanking([$backupPayload]),
            'operator_checklist' => self::operatorActionChecklist($backupPayload),
            'external_send' => false,
        ];
    }

    public static function operationalHealthTrend(?array $backupPayload = null): array
    {
        $backupPayload ??= self::exportDatabase();
        $signals = [
            'slo' => self::operationalSloReport($backupPayload)['status'],
            'write_anomaly' => self::writeAnomalyDetector()['status'],
            'backup_freshness' => self::backupFreshnessReport($backupPayload)['status'],
            'drift_budget' => self::operationalDriftBudget($backupPayload)['status'],
            'freeze_recommended' => self::writeSafetyThresholdPolicy()['freeze_recommended'],
        ];

        return [
            'trend' => in_array('exceeded', $signals, true) || $signals['freeze_recommended'] === true ? 'worsening' : 'stable',
            'signals' => $signals,
        ];
    }

    public static function writeQuarantineRecommendation(): array
    {
        $recommendations = [];
        foreach (array_keys(self::collections()) as $collection) {
            $profile = self::criticalCollectionProfile($collection);
            if ($profile['priority'] !== 'low') {
                $recommendations[] = ['collection' => $collection, 'reason' => 'elevated_priority'];
            }
        }

        return [
            'recommended' => $recommendations !== [],
            'targets' => $recommendations,
            'automatic_quarantine' => false,
        ];
    }

    public static function readModelRebuildSafetyReport(string $collection): array
    {
        self::assertCollection($collection);
        $checks = [
            'event_chain_valid' => self::eventChainIntegrity()['valid'] === true,
            'schema_compatible' => self::schemaVersioning($collection)['compatibility'] === 'compatible',
            'snapshot_seal_valid' => self::snapshotSealVerification($collection, self::snapshotIntegritySeal($collection))['valid'] === true,
            'no_drift' => self::readModelDriftDetection($collection)['drift'] === false,
        ];

        return [
            'collection' => $collection,
            'status' => self::all($checks) ? 'safe' : (self::eventChainIntegrity()['valid'] ? 'caution' : 'blocked'),
            'checks' => $checks,
            'will_rebuild' => false,
        ];
    }

    public static function backupTrustScore(array $payload): array
    {
        $freshness = self::backupFreshnessReport($payload);
        $compatibility = self::backupRestoreCompatibilityCheck($payload);
        $score = 0;
        $score += ($freshness['valid'] ?? false) === true ? 30 : 0;
        $score += ($freshness['status'] ?? null) === 'fresh' ? 30 : 0;
        $score += $compatibility['compatible'] === true ? 25 : 0;
        $score += self::backupConsistencyReport($payload)['consistent'] === true ? 15 : 0;

        return [
            'score' => $score,
            'status' => $score >= 90 ? 'trusted' : ($score >= 60 ? 'review' : 'untrusted'),
            'freshness' => $freshness,
            'compatibility' => $compatibility,
        ];
    }

    public static function eventGapDetection(): array
    {
        return self::eventGapReport() + [
            'duplicate_detection' => self::eventLogConsistencyCheck()['valid'] === false,
            'automatic_repair' => false,
        ];
    }

    public static function operationalSaturationReport(): array
    {
        $metrics = self::operationalMetrics();
        $score = 0;
        $score += $metrics['record_count'] >= 10000 ? 40 : 0;
        $score += $metrics['event_count'] >= 50000 ? 40 : 0;
        $score += self::writeAnomalyDetector()['intent_count'] >= 100 ? 20 : 0;

        return [
            'status' => $score >= 70 ? 'high' : ($score >= 30 ? 'medium' : 'low'),
            'score' => $score,
            'metrics' => $metrics,
        ];
    }

    public static function safeMaintenanceWindowReport(?array $backupPayload = null): array
    {
        $backupPayload ??= self::exportDatabase();
        $checks = [
            'slo_met' => self::operationalSloReport($backupPayload)['status'] === 'met',
            'risk_low' => self::operationalRiskScore()['level'] === 'low',
            'backup_fresh' => self::backupFreshnessReport($backupPayload)['status'] === 'fresh',
        ];

        return [
            'status' => self::all($checks) ? 'safe' : 'caution',
            'checks' => $checks,
            'automatic_scheduling' => false,
        ];
    }

    public static function dataRecoveryConfidence(array $payload): array
    {
        $readiness = self::recoveryReadinessReport($payload);
        $trust = self::backupTrustScore($payload);
        $confidence = 'blocked';
        if ($readiness['status'] === 'ready' && $trust['score'] >= 90) {
            $confidence = 'high';
        } elseif ($readiness['status'] !== 'blocked' && $trust['score'] >= 60) {
            $confidence = 'medium';
        } elseif ($trust['score'] > 0) {
            $confidence = 'low';
        }

        return [
            'confidence' => $confidence,
            'readiness' => $readiness,
            'backup_trust' => $trust,
        ];
    }

    public static function incidentRootCauseHints(): array
    {
        $hints = [];
        if (self::eventGapReport()['valid'] !== true) {
            $hints[] = 'event_gap';
        }
        if (self::auditIntegrity()['valid'] !== true) {
            $hints[] = 'integrity_audit';
        }
        if (self::writeAnomalyDetector()['status'] !== 'normal') {
            $hints[] = 'write_anomaly';
        }

        return [
            'hints' => $hints === [] ? ['no_current_root_cause_hint'] : $hints,
            'diagnostic_only' => true,
        ];
    }

    public static function productionOperationSummary(?array $backupPayload = null): array
    {
        $backupPayload ??= self::exportDatabase();

        return [
            'health_trend' => self::operationalHealthTrend($backupPayload),
            'risk' => self::operationalRiskScore(),
            'slo' => self::operationalSloReport($backupPayload),
            'backup_trust' => self::backupTrustScore($backupPayload),
            'recovery_confidence' => self::dataRecoveryConfidence($backupPayload),
            'operator_checklist' => self::operatorActionChecklist($backupPayload),
        ];
    }

    public static function operationReadinessLedger(?array $backupPayload = null): array
    {
        $backupPayload ??= self::exportDatabase();

        return [
            'entries' => [
                'readiness' => self::readiness()['ready'],
                'slo' => self::operationalSloReport($backupPayload)['status'],
                'backup_freshness' => self::backupFreshnessReport($backupPayload)['status'],
                'event_integrity' => self::eventChainIntegrity()['valid'],
                'freeze_recommended' => self::writeSafetyThresholdPolicy()['freeze_recommended'],
                'release_evidence' => self::releaseSafetyEvidence($backupPayload)['safe'],
            ],
            'fingerprint' => self::operationalBaselineSnapshot($backupPayload)['fingerprint'],
        ];
    }

    public static function writeAdmissionControlReport(?array $backupPayload = null): array
    {
        $backupPayload ??= self::exportDatabase();
        $threshold = self::writeSafetyThresholdPolicy();
        $gate = self::productionReadinessGate($backupPayload);
        $decision = 'admit';
        if ($threshold['status'] === 'threshold_exceeded' || $gate['status'] === 'blocked') {
            $decision = 'reject_recommended';
        } elseif ($gate['status'] === 'caution') {
            $decision = 'caution';
        }

        return [
            'decision' => $decision,
            'threshold' => $threshold,
            'production_gate' => $gate,
            'enforced' => false,
        ];
    }

    public static function criticalRecordWatchlist(): array
    {
        $items = [];
        foreach (self::$records as $collection => $records) {
            foreach ($records as $record) {
                if ((int)($record['version'] ?? 1) > 1) {
                    $items[] = [
                        'collection' => $collection,
                        'record_id' => $record['id'] ?? null,
                        'revision' => $record['version'] ?? 1,
                    ];
                }
            }
        }

        return [
            'items' => $items,
            'count' => count($items),
        ];
    }

    public static function schemaStabilityReport(): array
    {
        $collections = [];
        foreach (array_keys(self::collections()) as $collection) {
            $collections[$collection] = self::schemaVersioning($collection);
        }

        return [
            'status' => self::auditIntegrity()['valid'] ? 'stable' : 'unstable',
            'migration' => self::migrationPlan(),
            'collections' => $collections,
            'write_failures' => self::writeAnomalyDetector()['failure_count'],
        ];
    }

    public static function eventReplayFeasibilityReport(): array
    {
        $chain = self::eventChainIntegrity();

        return [
            'status' => $chain['valid'] === true ? 'safe' : 'blocked',
            'event_count' => count(self::$events),
            'latest_cursor' => self::cursor()['latest'],
            'will_replay' => false,
            'event_chain' => $chain,
        ];
    }

    public static function restoreDryRunEvidence(array $payload): array
    {
        return [
            'restore_simulation' => self::recoverySimulation($payload),
            'compatibility' => self::backupRestoreCompatibilityCheck($payload),
            'impact' => self::restoreImpactReport($payload),
            'candidate_ranking' => self::restoreCandidateRanking([$payload]),
            'recovery_confidence' => self::dataRecoveryConfidence($payload),
            'will_restore' => false,
        ];
    }

    public static function sqliteOperationalLimitsReport(): array
    {
        $metrics = self::operationalMetrics();
        $warnings = [];
        if ($metrics['record_count'] >= 10000) {
            $warnings[] = 'record_count_high';
        }
        if ($metrics['event_count'] >= 50000) {
            $warnings[] = 'event_count_high';
        }

        return [
            'status' => $warnings === [] ? 'within_limits' : 'review_limits',
            'warnings' => $warnings,
            'metrics' => $metrics,
            'sqlite_runtime' => self::storageStatus()['runtime_execution'],
        ];
    }

    public static function incidentCommunicationSummary(?array $backupPayload = null): array
    {
        $backupPayload ??= self::exportDatabase();

        return [
            'severity' => self::incidentSeverityClassification()['severity'],
            'impact' => self::restoreImpactReport($backupPayload)['totals'],
            'containment' => self::incidentContainmentPolicy($backupPayload)['policy'],
            'next_action' => self::operatorActionChecklist($backupPayload)['actions'][0],
            'external_send' => false,
        ];
    }

    public static function releaseRegressionEvidence(?array $baseline = null): array
    {
        return self::operationalRegressionGuard($baseline);
    }

    public static function productionSafetyBoard(?array $backupPayload = null): array
    {
        $backupPayload ??= self::exportDatabase();

        return [
            'readiness' => self::readiness()['ready'],
            'slo' => self::operationalSloReport($backupPayload)['status'],
            'integrity' => self::dataIntegrityAttestation($backupPayload)['valid'],
            'durability' => self::dataDurabilityReport($backupPayload)['durable'],
            'recovery_confidence' => self::dataRecoveryConfidence($backupPayload)['confidence'],
            'admission_control' => self::writeAdmissionControlReport($backupPayload)['decision'],
        ];
    }

    public static function operationalControlTower(?array $backupPayload = null): array
    {
        $backupPayload ??= self::exportDatabase();

        return [
            'readiness' => self::readiness()['ready'],
            'slo' => self::operationalSloReport($backupPayload)['status'],
            'drift_budget' => self::operationalDriftBudget($backupPayload)['status'],
            'saturation' => self::operationalSaturationReport()['status'],
            'backup_trust' => self::backupTrustScore($backupPayload)['status'],
            'recovery_confidence' => self::dataRecoveryConfidence($backupPayload)['confidence'],
        ];
    }

    public static function writePressureReport(): array
    {
        $anomaly = self::writeAnomalyDetector();
        $intentCount = max(1, $anomaly['intent_count']);

        return [
            'status' => $anomaly['intent_count'] >= 50 || $anomaly['failure_count'] > 0 ? 'watch' : 'normal',
            'intent_count' => $anomaly['intent_count'],
            'failure_rate' => $anomaly['failure_count'] / $intentCount,
            'critical_rate' => $anomaly['critical_count'] / $intentCount,
            'by_operation' => $anomaly['by_operation'],
        ];
    }

    public static function failureRecurrenceDetector(): array
    {
        $recurrence = [];
        foreach (self::writeIntentLog()['intents'] as $intent) {
            if (($intent['committed'] ?? true) === true) {
                continue;
            }
            $key = ($intent['operation'] ?? 'unknown') . ':' . ($intent['collection'] ?? 'none');
            $recurrence[$key] = ($recurrence[$key] ?? 0) + 1;
        }

        return [
            'recurrent' => array_filter($recurrence, static fn(int $count): bool => $count > 1),
            'automatic_fix' => false,
        ];
    }

    public static function restoreDecisionChecklist(array $payload): array
    {
        return [
            'items' => [
                'verify_backup_trust' => self::backupTrustScore($payload)['status'],
                'review_restore_impact' => self::restoreImpactReport($payload)['valid'],
                'confirm_freshness' => self::backupFreshnessReport($payload)['status'],
                'confirm_compatibility' => self::backupRestoreCompatibilityCheck($payload)['compatible'],
                'confirm_recovery_confidence' => self::dataRecoveryConfidence($payload)['confidence'],
            ],
            'will_restore' => false,
        ];
    }

    public static function eventChainTrustReport(): array
    {
        $chain = self::eventChainIntegrity();

        return [
            'trusted' => $chain['valid'] === true,
            'event_count' => $chain['event_count'],
            'latest_cursor' => self::cursor()['latest'],
            'tip' => $chain['tip'],
            'errors' => $chain['errors'],
        ];
    }

    public static function readConsistencyVerification(): array
    {
        $collections = [];
        $valid = true;
        foreach (array_keys(self::collections()) as $collection) {
            $snapshot = self::snapshot($collection);
            $rebuilt = self::rebuildSnapshot($collection);
            $snapshotHash = hash('sha256', self::encodeJson(self::readModelPayload($snapshot)));
            $rebuiltHash = hash('sha256', self::encodeJson(self::readModelPayload($rebuilt)));
            $same = $snapshotHash === $rebuiltHash;
            $valid = $valid && $same;
            $collections[$collection] = [
                'consistent' => $same,
                'snapshot_fingerprint' => $snapshotHash,
                'rebuilt_fingerprint' => $rebuiltHash,
            ];
        }

        return [
            'consistent' => $valid,
            'collections' => $collections,
            'automatic_repair' => false,
        ];
    }

    public static function operationalEvidenceTimeline(?array $backupPayload = null): array
    {
        $backupPayload ??= self::exportDatabase();

        return [
            'items' => [
                ['kind' => 'release_evidence', 'safe' => self::releaseSafetyEvidence($backupPayload)['safe']],
                ['kind' => 'incident_digest', 'severity' => self::incidentEvidenceDigest($backupPayload)['severity']],
                ['kind' => 'backup_validation', 'status' => self::backupFreshnessReport($backupPayload)['status']],
                ['kind' => 'slo', 'status' => self::operationalSloReport($backupPayload)['status']],
            ],
        ];
    }

    public static function degradedModeExitCriteria(?array $backupPayload = null): array
    {
        $backupPayload ??= self::exportDatabase();
        $checks = [
            'risk_low' => self::operationalRiskScore()['level'] === 'low',
            'slo_met' => self::operationalSloReport($backupPayload)['status'] === 'met',
            'event_chain_valid' => self::eventChainIntegrity()['valid'] === true,
            'backup_fresh' => self::backupFreshnessReport($backupPayload)['status'] === 'fresh',
        ];

        return [
            'can_exit' => self::all($checks),
            'checks' => $checks,
            'automatic_transition' => false,
        ];
    }

    public static function backupExposureReport(array $payload): array
    {
        $freshness = self::backupFreshnessReport($payload);
        $currentEvents = count(self::$events);
        $backupEvents = is_array($payload['events'] ?? null) ? count($payload['events']) : 0;

        return [
            'status' => $freshness['status'] === 'fresh' ? 'covered' : 'exposed',
            'possible_event_loss' => max(0, $currentEvents - $backupEvents),
            'freshness' => $freshness,
        ];
    }

    public static function productionOperationsPacket(?array $backupPayload = null): array
    {
        $backupPayload ??= self::exportDatabase();

        return [
            'control_tower' => self::operationalControlTower($backupPayload),
            'write_pressure' => self::writePressureReport(),
            'event_trust' => self::eventChainTrustReport(),
            'read_consistency' => self::readConsistencyVerification(),
            'restore_checklist' => self::restoreDecisionChecklist($backupPayload),
            'degraded_exit_criteria' => self::degradedModeExitCriteria($backupPayload),
        ];
    }

    public static function databaseStateDigest(): array
    {
        $collections = [];
        foreach (array_keys(self::collections()) as $collection) {
            $stats = self::stats($collection);
            $collections[$collection] = [
                'record_count' => $stats['record_count'],
                'event_count' => $stats['event_count'],
                'latest_cursor' => $stats['latest_cursor'],
                'schema_fingerprint' => $stats['schema_fingerprint'],
                'snapshot_seal' => self::snapshotIntegritySeal($collection)['seal'],
            ];
        }

        return [
            'version' => self::VERSION,
            'collection_count' => count($collections),
            'record_count' => array_sum(array_map('count', self::$records)),
            'event_count' => count(self::$events),
            'latest_cursor' => self::cursor()['latest'],
            'collections' => $collections,
            'fingerprint' => hash('sha256', self::encodeJson($collections)),
        ];
    }

    public static function writeReadinessCheck(string $collection, array $data = [], array $operations = []): array
    {
        $preflight = self::writeSafetyPreflight($collection, $data, $operations);
        $risk = self::preWriteRiskEvaluation($collection, $data, $operations);
        $contract = self::writeContractValidator($collection, 'write', $data, $operations);
        $checks = [
            'preflight_allowed' => $preflight['allowed'] === true,
            'risk_allowed' => $risk['allowed'] === true,
            'contract_valid' => $contract['valid'] === true,
            'freeze_allows_write' => self::operationFreezePolicy()['normal_write_allowed'] === true,
        ];
        $status = self::all($checks) ? 'ready' : (self::$safeMode || self::$maintenanceMode ? 'stopped' : 'manual_review');

        return [
            'status' => $status,
            'allowed' => self::all($checks),
            'collection' => $collection,
            'checks' => $checks,
            'preflight' => $preflight,
            'risk' => $risk,
            'contract' => $contract,
        ];
    }

    public static function restoreCandidateInspector(array $payload): array
    {
        return [
            'valid' => self::validateDatabaseExport($payload)['valid'],
            'dry_run' => true,
            'will_restore' => false,
            'freshness' => self::backupFreshnessReport($payload),
            'compatibility' => self::backupRestoreCompatibilityCheck($payload),
            'impact' => self::restoreImpactReport($payload),
            'confidence' => self::recoveryConfidenceScore($payload),
            'conflicts' => self::restoreConflictPreview($payload),
            'data_loss_exposure' => self::dataLossExposureReport($payload),
        ];
    }

    public static function eventStreamIntegritySummary(): array
    {
        return AdlaireEventLog::streamIntegritySummary(self::$events);
    }

    public static function operationalStatusBoard(?array $backupPayload = null): array
    {
        $backupPayload ??= self::exportDatabase();

        return [
            'state_digest' => self::databaseStateDigest(),
            'readiness' => self::readiness()['ready'],
            'risk' => self::operationalRiskScore(),
            'durability' => self::dataDurabilityReport($backupPayload),
            'backup_freshness' => self::backupFreshnessReport($backupPayload),
            'event_integrity' => self::eventStreamIntegritySummary(),
            'freeze_reason' => self::operationalFreezeReason(),
        ];
    }

    public static function maintenanceDecisionReport(?array $backupPayload = null): array
    {
        $backupPayload ??= self::exportDatabase();
        $risk = self::operationalRiskScore();
        $event = self::eventStreamIntegritySummary();
        $backup = self::backupFreshnessReport($backupPayload);
        $reasons = [];
        if ($risk['level'] !== 'low') {
            $reasons[] = 'risk_not_low';
        }
        if ($event['valid'] !== true) {
            $reasons[] = 'event_integrity_invalid';
        }
        if (($backup['status'] ?? null) !== 'fresh') {
            $reasons[] = 'backup_not_fresh';
        }

        return [
            'decision' => $reasons === [] ? 'maintenance_not_required' : 'maintenance_review',
            'reasons' => $reasons,
            'release_conditions' => [
                'risk_low' => $risk['level'] === 'low',
                'event_integrity_valid' => $event['valid'] === true,
                'backup_fresh' => ($backup['status'] ?? null) === 'fresh',
            ],
            'automatic_transition' => false,
        ];
    }

    public static function backupRotationView(array $candidates): array
    {
        $items = [];
        foreach ($candidates as $index => $candidate) {
            if (!is_array($candidate)) {
                $items[] = ['index' => $index, 'valid' => false, 'status' => 'invalid'];
                continue;
            }
            $items[] = [
                'index' => $index,
                'valid' => self::validateDatabaseExport($candidate)['valid'],
                'cursor' => $candidate['cursor'] ?? null,
                'event_count' => is_array($candidate['events'] ?? null) ? count($candidate['events']) : 0,
                'fingerprint' => $candidate['fingerprint'] ?? null,
                'freshness' => self::backupFreshnessReport($candidate)['status'] ?? 'invalid',
                'trust' => self::backupTrustScore($candidate)['status'],
            ];
        }

        return [
            'items' => $items,
            'count' => count($items),
            'automatic_delete' => false,
            'automatic_scheduling' => false,
        ];
    }

    public static function dataMutationRiskReport(string $operation, ?string $collection = null, ?array $payload = null): array
    {
        $collectionCount = $collection === null ? count(self::collections()) : 1;
        $recordCount = 0;
        if ($collection !== null && isset(self::collections()[$collection])) {
            $recordCount = count(self::records($collection));
        } elseif (is_array($payload)) {
            foreach (($payload['snapshots'] ?? []) as $snapshot) {
                if (is_array($snapshot) && is_array($snapshot['records'] ?? null)) {
                    $recordCount += count($snapshot['records']);
                }
            }
        }
        $critical = in_array($operation, self::criticalOperations(), true);
        $risk = $critical && $recordCount > 0 ? 'medium' : ($critical ? 'low' : 'low');

        return [
            'operation' => $operation,
            'collection' => $collection,
            'critical' => $critical,
            'risk_level' => $risk,
            'affected_collection_count' => $collectionCount,
            'estimated_record_count' => $recordCount,
            'rollback_view' => 'backup_or_snapshot_restore_review',
            'will_mutate' => false,
        ];
    }

    public static function readModelRebuildSafetyCheck(string $collection): array
    {
        $report = self::readModelRebuildSafetyReport($collection);

        return $report + [
            'event_replay_proof' => self::eventReplayProof($collection),
        ];
    }

    public static function incidentRecoveryPacket(?array $backupPayload = null): array
    {
        $backupPayload ??= self::exportDatabase();

        return [
            'severity' => self::incidentSeverityClassification(),
            'risk' => self::operationalRiskScore(),
            'event_integrity' => self::eventStreamIntegritySummary(),
            'restore_candidate' => self::restoreCandidateInspector($backupPayload),
            'next_action' => self::operationalHandoffReport($backupPayload)['next_action'],
            'automatic_restore' => false,
        ];
    }

    public static function operationJournal(): array
    {
        return [
            'entries' => self::writeIntentLog()['intents'],
            'count' => self::writeIntentLog()['count'],
            'diagnostic_only' => true,
        ];
    }

    public static function recoveryConfidenceScore(array $payload): array
    {
        $confidence = self::dataRecoveryConfidence($payload);
        $score = match ($confidence['confidence']) {
            'high' => 100,
            'medium' => 70,
            'low' => 40,
            default => 0,
        };

        return [
            'score' => $score,
            'level' => $confidence['confidence'],
            'readiness' => $confidence['readiness'],
            'backup_trust' => $confidence['backup_trust'],
        ];
    }

    public static function schemaDriftGuard(): array
    {
        $collections = [];
        $drift = false;
        foreach (array_keys(self::collections()) as $collection) {
            $state = [
                'schema' => self::schemaVersioning($collection),
                'read_model' => self::readModelDriftDetection($collection),
            ];
            $state['drift'] = $state['read_model']['drift'] === true;
            $drift = $drift || $state['drift'];
            $collections[$collection] = $state;
        }

        return [
            'drift' => $drift,
            'collections' => $collections,
            'write_review_required' => $drift,
        ];
    }

    public static function eventReplayProof(string $collection): array
    {
        self::assertCollection($collection);
        $snapshot = self::snapshot($collection);
        $rebuilt = self::rebuildSnapshot($collection);
        $proof = AdlaireEventLog::replayProof($collection, $snapshot, $rebuilt);
        $proof['event_count'] = count(self::events(null, $collection));

        return $proof;
    }

    public static function eventReplayVerification(string $collection): array
    {
        self::assertCollection($collection);

        return AdlaireEventLog::replayVerification(
            $collection,
            self::snapshot($collection),
            self::rebuildSnapshot($collection)
        );
    }

    public static function backupTrustLedger(array $candidates): array
    {
        $entries = [];
        foreach ($candidates as $index => $candidate) {
            if (!is_array($candidate)) {
                $entries[] = ['index' => $index, 'trusted' => false, 'status' => 'invalid'];
                continue;
            }
            $trust = self::backupTrustScore($candidate);
            $entries[] = [
                'index' => $index,
                'cursor' => $candidate['cursor'] ?? null,
                'event_count' => is_array($candidate['events'] ?? null) ? count($candidate['events']) : 0,
                'fingerprint' => $candidate['fingerprint'] ?? null,
                'verification' => self::backupVerification($candidate),
                'compatibility' => self::backupRestoreCompatibilityCheck($candidate),
                'freshness' => self::backupFreshnessReport($candidate),
                'trust' => $trust,
                'trusted' => $trust['status'] === 'trusted',
            ];
        }

        return [
            'entries' => $entries,
            'automatic_backup' => false,
            'automatic_delete' => false,
        ];
    }

    public static function operationalFreezeReason(): array
    {
        $reasons = [];
        if (self::$safeMode) {
            $reasons[] = 'safe_mode';
        }
        if (self::$maintenanceMode) {
            $reasons[] = 'maintenance_mode';
        }
        if (self::$degradedMode) {
            $reasons[] = 'degraded_mode';
        }
        if (in_array(true, self::$collectionLocks, true)) {
            $reasons[] = 'collection_lock';
        }
        if (self::operationalRiskScore()['level'] !== 'low') {
            $reasons[] = 'risk_not_low';
        }

        return [
            'frozen' => $reasons !== [],
            'reasons' => $reasons,
            'standardized' => true,
        ];
    }

    public static function criticalPathCheck(?array $backupPayload = null): array
    {
        $backupPayload ??= self::exportDatabase();
        $firstCollection = array_key_first(self::collections());
        $checks = [
            'readiness' => self::readiness()['ready'] === true,
            'event_append_available' => self::eventStreamIntegritySummary()['valid'] === true,
            'snapshot_export_available' => $firstCollection !== null && is_array(self::exportSnapshot((string)$firstCollection)),
            'backup_verification' => self::backupVerification($backupPayload)['valid'] === true,
            'restore_dry_run' => self::restoreDryRun($backupPayload)['valid'] === true,
        ];

        return [
            'status' => self::all($checks) ? 'ready' : 'blocked',
            'checks' => $checks,
            'will_write' => false,
        ];
    }

    public static function dataLossExposureReport(array $payload): array
    {
        $exposure = self::backupExposureReport($payload);

        return $exposure + [
            'current_cursor' => self::cursor()['latest'],
            'backup_cursor' => $payload['cursor'] ?? null,
            'possible_record_loss' => max(0, self::databaseStateDigest()['record_count'] - self::payloadRecordCount($payload)),
        ];
    }

    public static function operatorHandoffNote(?array $backupPayload = null): array
    {
        $handoff = self::operationalHandoffReport($backupPayload);

        return [
            'current_status' => $handoff['current_status'],
            'severity' => $handoff['severity']['severity'],
            'freeze_state' => self::operationalFreezeReason(),
            'next_action' => $handoff['next_action'],
            'brief' => $handoff['current_status'] . ':' . $handoff['next_action'],
        ];
    }

    public static function writeContractValidator(string $collection, string $operation = 'write', array $data = [], array $operations = []): array
    {
        self::assertCollection($collection);
        $errors = [];
        try {
            self::normalizeDataForSchema($collection, $data, $operation === 'create');
        } catch (Throwable $exception) {
            $errors[] = $exception->getMessage();
        }
        $quota = self::writeQuotaGuard([
            'record' => $data,
            'patch_operations' => $operations,
            'transaction_operations' => $operations,
        ]);
        if ($quota['allowed'] !== true) {
            $errors[] = 'write_quota_exceeded';
        }

        return [
            'valid' => $errors === [],
            'operation' => $operation,
            'collection' => $collection,
            'critical' => in_array($operation, self::criticalOperations(), true),
            'errors' => $errors,
            'quota' => $quota,
        ];
    }

    public static function eventCausalityChain(): array
    {
        return AdlaireEventLog::causalityChain(self::$events);
    }

    public static function snapshotRecoveryPoint(string $collection): array
    {
        self::assertCollection($collection);
        $snapshot = self::snapshot($collection);
        $seal = self::snapshotIntegritySeal($collection);

        return [
            'collection' => $collection,
            'cursor' => $snapshot['cursor'],
            'event_count' => count(self::events(null, $collection)),
            'schema_fingerprint' => $seal['schema_fingerprint'],
            'seal' => $seal['seal'],
            'created_sequence' => self::$eventSequence,
        ];
    }

    public static function restoreConflictPreview(array $payload): array
    {
        $conflicts = [];
        foreach (($payload['snapshots'] ?? []) as $collection => $snapshot) {
            if (!is_array($snapshot)) {
                continue;
            }
            $current = isset(self::collections()[(string)$collection]) ? self::records((string)$collection) : [];
            $currentById = self::recordsById($current);
            $backupById = self::recordsById($snapshot['records'] ?? []);
            foreach ($backupById as $id => $record) {
                if (!isset($currentById[$id])) {
                    $conflicts[] = ['collection' => (string)$collection, 'record_id' => $id, 'type' => 'missing_current'];
                    continue;
                }
                if (($currentById[$id]['version'] ?? null) !== ($record['version'] ?? null)) {
                    $conflicts[] = ['collection' => (string)$collection, 'record_id' => $id, 'type' => 'stale_or_overwrite'];
                }
            }
        }

        return [
            'conflict' => $conflicts !== [],
            'conflicts' => $conflicts,
            'will_restore' => false,
        ];
    }

    public static function readConsistencyWindow(): array
    {
        return [
            'consistent' => self::readConsistencyVerification()['consistent'],
            'from_cursor' => null,
            'to_cursor' => self::cursor()['latest'],
            'collections' => self::readConsistencyVerification()['collections'],
            'risk_level' => self::operationalRiskScore()['level'],
        ];
    }

    public static function backupCompletenessCheck(array $payload): array
    {
        $checks = [
            'collections_present' => is_array($payload['collections'] ?? null),
            'snapshots_present' => is_array($payload['snapshots'] ?? null),
            'events_present' => is_array($payload['events'] ?? null),
            'cursor_present' => array_key_exists('cursor', $payload),
            'fingerprint_present' => isset($payload['fingerprint']) && is_string($payload['fingerprint']),
            'selected_database_sqlite' => ($payload['selected_database'] ?? null) === 'sqlite',
        ];

        return [
            'complete' => self::all($checks),
            'checks' => $checks,
            'collection_count' => is_array($payload['collections'] ?? null) ? count($payload['collections']) : 0,
            'event_count' => is_array($payload['events'] ?? null) ? count($payload['events']) : 0,
        ];
    }

    public static function operationalModeMatrix(): array
    {
        $freeze = self::operationFreezePolicy();

        return [
            'normal' => ['read' => true, 'write' => true, 'critical' => true, 'restore' => true],
            'maintenance' => ['read' => true, 'write' => false, 'critical' => false, 'restore' => false],
            'degraded' => ['read' => true, 'write' => true, 'critical' => false, 'restore' => false],
            'safe' => ['read' => true, 'write' => false, 'critical' => false, 'restore' => false],
            'readonly' => ['read' => true, 'write' => false, 'critical' => false, 'restore' => false],
            'current' => [
                'read' => $freeze['read_allowed'],
                'write' => $freeze['normal_write_allowed'],
                'critical' => $freeze['critical_write_allowed'],
                'restore' => $freeze['restore_allowed'],
            ],
        ];
    }

    public static function criticalOperationApprovalToken(string $operation, ?string $collection = null): array
    {
        $guard = self::criticalOperationGuard($operation, $collection);
        $payload = [
            'operation' => $operation,
            'collection' => $collection,
            'cursor' => self::cursor()['latest'],
            'allowed' => $guard['allowed'],
        ];

        return [
            'token' => hash('sha256', self::encodeJson($payload)),
            'payload' => $payload,
            'guard' => $guard,
            'external_auth' => false,
        ];
    }

    public static function dataRetentionPolicyView(): array
    {
        return [
            'snapshot' => self::snapshotRetentionPlan(),
            'event' => ['retention' => 'manual_policy', 'automatic_delete' => false],
            'backup' => ['retention' => 'operator_managed', 'automatic_delete' => false],
            'soft_deleted_record' => ['visible' => false, 'automatic_delete' => false],
            'runtime_enforced' => false,
        ];
    }

    public static function eventGapRepairPlan(): array
    {
        $gap = AdlaireEventLog::gapReport(self::$events);

        return [
            'needed' => $gap['valid'] !== true,
            'gaps' => $gap['gaps'],
            'manual_actions' => $gap['valid'] === true ? ['continue_observation'] : ['inspect_backup', 'compare_snapshot', 'manual_rebuild_review'],
            'automatic_repair' => false,
        ];
    }

    public static function schemaCompatibilityMatrix(array $payload): array
    {
        $matrix = [];
        foreach (self::collections() as $collection => $definition) {
            $backup = $payload['collections'][$collection] ?? null;
            $matrix[$collection] = [
                'current_schema_fingerprint' => self::schemaVersioning((string)$collection)['schema_fingerprint'],
                'backup_present' => is_array($backup),
                'compatible' => is_array($backup) && ($backup['schema'] ?? null) == $definition['schema'],
            ];
        }

        return [
            'compatible' => self::all(array_map(static fn(array $row): bool => $row['compatible'], $matrix)),
            'collections' => $matrix,
        ];
    }

    public static function recoveryTimelineSimulator(array $payload): array
    {
        $impact = self::restoreImpactReport($payload);

        return [
            'valid' => $impact['valid'],
            'will_restore' => false,
            'timeline' => [
                ['step' => 'validate_backup', 'valid' => self::backupVerification($payload)['valid']],
                ['step' => 'inspect_conflicts', 'conflict' => self::restoreConflictPreview($payload)['conflict']],
                ['step' => 'estimate_impact', 'totals' => $impact['totals']],
                ['step' => 'operator_decision', 'automatic_restore' => false],
            ],
        ];
    }

    public static function incidentContainmentView(?array $backupPayload = null): array
    {
        $backupPayload ??= self::exportDatabase();
        $policy = self::incidentContainmentPolicy($backupPayload);

        return [
            'policy' => $policy,
            'freeze_reason' => self::operationalFreezeReason(),
            'mode_matrix' => self::operationalModeMatrix(),
            'automatic_freeze' => false,
        ];
    }

    public static function productionReadinessLedger(?array $backupPayload = null): array
    {
        $backupPayload ??= self::exportDatabase();
        $entries = [
            'readiness' => self::readiness()['ready'],
            'event_replay_proof' => self::eventReplayProof((string)array_key_first(self::collections()))['proved'],
            'backup_completeness' => self::backupCompletenessCheck($backupPayload)['complete'],
            'restore_dry_run' => self::restoreDryRun($backupPayload)['valid'],
            'critical_path' => self::criticalPathCheck($backupPayload)['status'],
            'risk_level' => self::operationalRiskScore()['level'],
        ];

        return [
            'ready' => $entries['readiness'] === true
                && $entries['event_replay_proof'] === true
                && $entries['backup_completeness'] === true
                && $entries['restore_dry_run'] === true
                && $entries['critical_path'] === 'ready'
                && $entries['risk_level'] === 'low',
            'entries' => $entries,
            'fingerprint' => hash('sha256', self::encodeJson($entries)),
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
        foreach (self::eventLogConsistencyCheck()['errors'] as $eventError) {
            $errors[] = ['type' => 'event_log_consistency', 'detail' => $eventError];
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
            'core_root_policy' => $planned['core_root_policy'] === 'common_foundation_and_entrypoints',
            'entrypoint_policy' => $planned['entrypoint_policy'] === 'single_file_principle',
            'event_log_policy' => $planned['event_log_policy'] === 'single_file_principle',
            'event_log_file' => $planned['event_log_file'] === 'Core/EventLog.php',
            'event_log_folder' => $planned['event_log_folder'] === 'prohibited',
            'event_log_common_foundation' => $planned['event_log_common_foundation'] === true,
            'event_log_single_file' => $planned['event_log_single_file'] === true,
            'event_log_not_entrypoint' => $planned['event_log_entrypoint'] === false,
            'event_log_realtime_database' => in_array('realtime_database', $planned['event_log_shared_by'], true),
            'event_log_authentication' => in_array('authentication', $planned['event_log_shared_by'], true),
            'event_log_authorization' => in_array('authorization', $planned['event_log_shared_by'], true),
            'event_log_not_message_broker' => $planned['event_log_message_broker'] === false,
            'event_log_not_remote_sync' => $planned['event_log_remote_sync'] === false,
            'event_log_not_automatic_repair' => $planned['event_log_automatic_repair'] === false,
            'event_log_not_automatic_compaction' => $planned['event_log_automatic_compaction'] === false,
            'event_log_not_automatic_delete' => $planned['event_log_automatic_delete'] === false,
            'event_envelope' => $planned['event_envelope'] === true,
            'event_domain_source' => $planned['event_domain_source'] === true,
            'event_metadata' => $planned['event_metadata'] === true,
            'event_type_registry' => $planned['event_type_registry'] === true,
            'event_chain_hash' => $planned['event_chain_hash'] === true,
            'event_validation' => $planned['event_validation'] === true,
            'event_replay_scope' => $planned['event_replay_scope'] === true,
            'event_evidence' => $planned['event_evidence'] === true,
            'event_snapshot_link' => $planned['event_snapshot_link'] === true,
            'event_replay_verification' => $planned['event_replay_verification'] === true,
            'event_cursor_contract' => $planned['event_cursor_contract'] === true,
            'event_import_validation' => $planned['event_import_validation'] === true,
            'event_export_packet' => $planned['event_export_packet'] === true,
            'event_retention_view' => $planned['event_retention_view'] === true,
            'event_risk_report' => $planned['event_risk_report'] === true,
            'event_operation_journal' => $planned['event_operation_journal'] === true,
            'sqlite_libsql_storage' => $planned['storage'] === 'sqlite_libsql',
            'sqlite_selected' => $planned['selected_database'] === 'sqlite',
            'libsql_compatibility_target' => $planned['compatibility_target'] === 'libsql',
            'sqlite_primary_policy' => $planned['storage_policy'] === 'sqlite_primary_libsql_compatible',
            'sqlite_persistence' => $planned['sqlite_persistence'] === true,
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
            'change_feed_filter' => $planned['change_feed_filter'] === true,
            'record_version_history' => $planned['record_version_history'] === true,
            'record_diff' => $planned['record_diff'] === true,
            'snapshot_retention_plan' => $planned['snapshot_retention_plan'] === true,
            'backup_manifest' => $planned['backup_manifest'] === true,
            'restore_preview' => $planned['restore_preview'] === true,
            'collection_lock' => $planned['collection_lock'] === true,
            'write_quota_guard' => $planned['write_quota_guard'] === true,
            'event_checkpoint' => $planned['event_checkpoint'] === true,
            'operational_incident_report' => $planned['operational_incident_report'] === true,
            'query_cursor_enhancement' => $planned['query_cursor_enhancement'] === true,
            'import_validation_enhancement' => $planned['import_validation_enhancement'] === true,
            'audit_integrity_enhancement' => $planned['audit_integrity_enhancement'] === true,
            'operational_report_enhancement' => $planned['operational_report_enhancement'] === true,
            'data_redaction_export_enhancement' => $planned['data_redaction_export_enhancement'] === true,
            'schema_versioning_enhancement' => $planned['schema_versioning_enhancement'] === true,
            'health_baseline' => $planned['health_baseline'] === true,
            'drift_baseline_compare' => $planned['drift_baseline_compare'] === true,
            'write_safety_preflight' => $planned['write_safety_preflight'] === true,
            'restore_safety_gate' => $planned['restore_safety_gate'] === true,
            'backup_consistency_report' => $planned['backup_consistency_report'] === true,
            'event_gap_report' => $planned['event_gap_report'] === true,
            'corruption_suspect_report' => $planned['corruption_suspect_report'] === true,
            'operational_risk_score' => $planned['operational_risk_score'] === true,
            'recovery_decision_report' => $planned['recovery_decision_report'] === true,
            'safe_mode' => $planned['safe_mode'] === true,
            'readonly_runtime_report' => $planned['readonly_runtime_report'] === true,
            'incident_timeline' => $planned['incident_timeline'] === true,
            'write_intent_log' => $planned['write_intent_log'] === true,
            'write_commit_verification' => $planned['write_commit_verification'] === true,
            'recovery_simulation' => $planned['recovery_simulation'] === true,
            'restore_impact_report' => $planned['restore_impact_report'] === true,
            'event_chain_integrity' => $planned['event_chain_integrity'] === true,
            'snapshot_integrity_seal' => $planned['snapshot_integrity_seal'] === true,
            'operational_runbook_report' => $planned['operational_runbook_report'] === true,
            'degraded_mode' => $planned['degraded_mode'] === true,
            'critical_operation_guard' => $planned['critical_operation_guard'] === true,
            'operational_evidence_bundle' => $planned['operational_evidence_bundle'] === true,
            'pre_write_risk_evaluation' => $planned['pre_write_risk_evaluation'] === true,
            'critical_write_two_step_guard' => $planned['critical_write_two_step_guard'] === true,
            'backup_restore_compatibility_check' => $planned['backup_restore_compatibility_check'] === true,
            'snapshot_seal_verification' => $planned['snapshot_seal_verification'] === true,
            'operational_degradation_reason' => $planned['operational_degradation_reason'] === true,
            'incident_severity_classification' => $planned['incident_severity_classification'] === true,
            'recovery_readiness_report' => $planned['recovery_readiness_report'] === true,
            'operation_freeze_policy' => $planned['operation_freeze_policy'] === true,
            'data_durability_report' => $planned['data_durability_report'] === true,
            'release_safety_evidence' => $planned['release_safety_evidence'] === true,
            'operational_slo_report' => $planned['operational_slo_report'] === true,
            'write_failure_classification' => $planned['write_failure_classification'] === true,
            'backup_freshness_report' => $planned['backup_freshness_report'] === true,
            'restore_candidate_ranking' => $planned['restore_candidate_ranking'] === true,
            'read_model_confidence_report' => $planned['read_model_confidence_report'] === true,
            'operational_window_policy' => $planned['operational_window_policy'] === true,
            'recovery_drill_report' => $planned['recovery_drill_report'] === true,
            'incident_evidence_digest' => $planned['incident_evidence_digest'] === true,
            'data_lifecycle_guard' => $planned['data_lifecycle_guard'] === true,
            'operational_handoff_report' => $planned['operational_handoff_report'] === true,
            'operational_baseline_snapshot' => $planned['operational_baseline_snapshot'] === true,
            'write_anomaly_detector' => $planned['write_anomaly_detector'] === true,
            'recovery_priority_report' => $planned['recovery_priority_report'] === true,
            'operational_risk_timeline' => $planned['operational_risk_timeline'] === true,
            'data_consistency_score' => $planned['data_consistency_score'] === true,
            'backup_candidate_validation_matrix' => $planned['backup_candidate_validation_matrix'] === true,
            'write_safety_threshold_policy' => $planned['write_safety_threshold_policy'] === true,
            'incident_replay_summary' => $planned['incident_replay_summary'] === true,
            'production_readiness_gate' => $planned['production_readiness_gate'] === true,
            'operator_action_checklist' => $planned['operator_action_checklist'] === true,
            'operational_drift_budget' => $planned['operational_drift_budget'] === true,
            'write_blast_radius_report' => $planned['write_blast_radius_report'] === true,
            'recovery_path_comparison' => $planned['recovery_path_comparison'] === true,
            'data_integrity_attestation' => $planned['data_integrity_attestation'] === true,
            'incident_containment_policy' => $planned['incident_containment_policy'] === true,
            'operational_regression_guard' => $planned['operational_regression_guard'] === true,
            'backup_rotation_policy_report' => $planned['backup_rotation_policy_report'] === true,
            'state_transition_audit' => $planned['state_transition_audit'] === true,
            'critical_collection_profile' => $planned['critical_collection_profile'] === true,
            'production_incident_packet' => $planned['production_incident_packet'] === true,
            'operational_health_trend' => $planned['operational_health_trend'] === true,
            'write_quarantine_recommendation' => $planned['write_quarantine_recommendation'] === true,
            'read_model_rebuild_safety_report' => $planned['read_model_rebuild_safety_report'] === true,
            'backup_trust_score' => $planned['backup_trust_score'] === true,
            'event_gap_detection' => $planned['event_gap_detection'] === true,
            'operational_saturation_report' => $planned['operational_saturation_report'] === true,
            'safe_maintenance_window_report' => $planned['safe_maintenance_window_report'] === true,
            'data_recovery_confidence' => $planned['data_recovery_confidence'] === true,
            'incident_root_cause_hints' => $planned['incident_root_cause_hints'] === true,
            'production_operation_summary' => $planned['production_operation_summary'] === true,
            'operation_readiness_ledger' => $planned['operation_readiness_ledger'] === true,
            'write_admission_control_report' => $planned['write_admission_control_report'] === true,
            'critical_record_watchlist' => $planned['critical_record_watchlist'] === true,
            'schema_stability_report' => $planned['schema_stability_report'] === true,
            'event_replay_feasibility_report' => $planned['event_replay_feasibility_report'] === true,
            'restore_dry_run_evidence' => $planned['restore_dry_run_evidence'] === true,
            'sqlite_operational_limits_report' => $planned['sqlite_operational_limits_report'] === true,
            'incident_communication_summary' => $planned['incident_communication_summary'] === true,
            'release_regression_evidence' => $planned['release_regression_evidence'] === true,
            'production_safety_board' => $planned['production_safety_board'] === true,
            'operational_control_tower' => $planned['operational_control_tower'] === true,
            'write_pressure_report' => $planned['write_pressure_report'] === true,
            'failure_recurrence_detector' => $planned['failure_recurrence_detector'] === true,
            'restore_decision_checklist' => $planned['restore_decision_checklist'] === true,
            'event_chain_trust_report' => $planned['event_chain_trust_report'] === true,
            'read_consistency_verification' => $planned['read_consistency_verification'] === true,
            'operational_evidence_timeline' => $planned['operational_evidence_timeline'] === true,
            'degraded_mode_exit_criteria' => $planned['degraded_mode_exit_criteria'] === true,
            'backup_exposure_report' => $planned['backup_exposure_report'] === true,
            'production_operations_packet' => $planned['production_operations_packet'] === true,
            'database_state_digest' => $planned['database_state_digest'] === true,
            'write_readiness_check' => $planned['write_readiness_check'] === true,
            'restore_candidate_inspector' => $planned['restore_candidate_inspector'] === true,
            'event_stream_integrity_summary' => $planned['event_stream_integrity_summary'] === true,
            'operational_status_board' => $planned['operational_status_board'] === true,
            'maintenance_decision_report' => $planned['maintenance_decision_report'] === true,
            'backup_rotation_view' => $planned['backup_rotation_view'] === true,
            'data_mutation_risk_report' => $planned['data_mutation_risk_report'] === true,
            'read_model_rebuild_safety_check' => $planned['read_model_rebuild_safety_check'] === true,
            'incident_recovery_packet' => $planned['incident_recovery_packet'] === true,
            'operation_journal' => $planned['operation_journal'] === true,
            'recovery_confidence_score' => $planned['recovery_confidence_score'] === true,
            'schema_drift_guard' => $planned['schema_drift_guard'] === true,
            'event_replay_proof' => $planned['event_replay_proof'] === true,
            'backup_trust_ledger' => $planned['backup_trust_ledger'] === true,
            'operational_freeze_reason' => $planned['operational_freeze_reason'] === true,
            'critical_path_check' => $planned['critical_path_check'] === true,
            'data_loss_exposure_report' => $planned['data_loss_exposure_report'] === true,
            'operator_handoff_note' => $planned['operator_handoff_note'] === true,
            'write_contract_validator' => $planned['write_contract_validator'] === true,
            'event_causality_chain' => $planned['event_causality_chain'] === true,
            'snapshot_recovery_point' => $planned['snapshot_recovery_point'] === true,
            'restore_conflict_preview' => $planned['restore_conflict_preview'] === true,
            'read_consistency_window' => $planned['read_consistency_window'] === true,
            'backup_completeness_check' => $planned['backup_completeness_check'] === true,
            'operational_mode_matrix' => $planned['operational_mode_matrix'] === true,
            'critical_operation_approval_token' => $planned['critical_operation_approval_token'] === true,
            'data_retention_policy_view' => $planned['data_retention_policy_view'] === true,
            'event_gap_repair_plan' => $planned['event_gap_repair_plan'] === true,
            'schema_compatibility_matrix' => $planned['schema_compatibility_matrix'] === true,
            'recovery_timeline_simulator' => $planned['recovery_timeline_simulator'] === true,
            'incident_containment_view' => $planned['incident_containment_view'] === true,
            'production_readiness_ledger' => $planned['production_readiness_ledger'] === true,
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

    private static function snapshotSeals(): array
    {
        $seals = [];
        foreach (array_keys(self::collections()) as $collection) {
            $seals[$collection] = self::snapshotIntegritySeal($collection);
        }

        return $seals;
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
        if (self::$safeMode) {
            throw new RuntimeException('Realtime Database is in safe mode.');
        }
        if (self::$maintenanceMode) {
            throw new RuntimeException('Realtime Database is in maintenance mode.');
        }
    }

    private static function assertCollectionWritesAllowed(string $collection): void
    {
        if ((self::$collectionLocks[$collection] ?? false) === true) {
            throw new RuntimeException('Realtime Database collection is locked.');
        }
    }

    private static function assertCriticalOperationAllowed(string $operation, ?string $collection = null): void
    {
        $guard = self::criticalOperationGuard($operation, $collection);
        if ($guard['allowed'] !== true) {
            throw new RuntimeException('Realtime Database critical operation is blocked.');
        }
    }

    private static function criticalOperations(): array
    {
        return ['restore_database', 'restore_snapshot', 'bulk_write', 'delete', 'record_restore'];
    }

    private static function recordWriteIntent(string $operation, ?string $collection, ?string $recordId, array $payload, bool $critical): array
    {
        $intent = [
            'id' => 'intent_' . str_pad((string)(count(self::$writeIntents) + 1), 6, '0', STR_PAD_LEFT),
            'operation' => $operation,
            'collection' => $collection,
            'record_id' => $recordId,
            'critical' => $critical,
            'cursor_before' => self::cursor()['latest'],
            'event_sequence_before' => self::$eventSequence,
            'payload_fingerprint' => hash('sha256', self::encodeJson(self::stableData($payload))),
        ];
        self::$writeIntents[] = $intent;

        return $intent;
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

    private static function payloadForVersion(array $versions, int $version): array
    {
        foreach ($versions as $entry) {
            if ((int)($entry['version'] ?? 0) === $version && is_array($entry['payload'] ?? null)) {
                return $entry['payload'];
            }
        }

        return [];
    }

    private static function recordsById(array $records): array
    {
        $byId = [];
        foreach ($records as $record) {
            if (is_array($record) && isset($record['id'])) {
                $byId[(string)$record['id']] = $record;
            }
        }
        ksort($byId);

        return $byId;
    }

    private static function payloadEventGapReport(array $events): array
    {
        return AdlaireEventLog::payloadEventGapReport($events);
    }

    private static function payloadRecordCount(array $payload): int
    {
        $count = 0;
        foreach (($payload['snapshots'] ?? []) as $snapshot) {
            if (is_array($snapshot) && is_array($snapshot['records'] ?? null)) {
                $count += count($snapshot['records']);
            }
        }

        return $count;
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

    private static function recordEvent(string $collection, string $recordId, string $type, int $version, array $payload, ?array $before): array
    {
        $event = AdlaireEventLog::recordEvent(
            self::$events,
            $collection,
            self::collections()[$collection]['channel'],
            $recordId,
            $type,
            $version,
            $payload,
            $before
        );
        self::$eventSequence = (int)$event['sequence'];
        self::$events[] = $event;
        self::persistEvent($event);

        return $event;
    }

    private static function lastEventId(array $events): ?string
    {
        return AdlaireEventLog::lastEventId($events);
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
