<?php

declare(strict_types=1);

require_once __DIR__ . '/../Core/Database.php';

final class TestFailure extends RuntimeException
{
}

function assert_true(bool $condition, string $message): void
{
    if (!$condition) {
        throw new TestFailure($message);
    }
}

function assert_same(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new TestFailure($message . ' expected=' . var_export($expected, true) . ' actual=' . var_export($actual, true));
    }
}

function files_in(string $directory): array
{
    $items = scandir(__DIR__ . '/../' . $directory);
    $items = array_values(array_filter($items === false ? [] : $items, static fn(string $item): bool => $item !== '.' && $item !== '..'));
    sort($items);
    return $items;
}

function recursive_php_files(string $directory): array
{
    $root = realpath(__DIR__ . '/../' . $directory);
    if ($root === false) {
        return [];
    }

    $files = [];
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
    foreach ($iterator as $file) {
        if ($file->isFile() && $file->getExtension() === 'php') {
            $files[] = str_replace($root . DIRECTORY_SEPARATOR, '', $file->getPathname());
        }
    }
    sort($files);
    return $files;
}

function root_markdown_files(): array
{
    $items = glob(__DIR__ . '/../*.md') ?: [];
    $items = array_map('basename', $items);
    sort($items);
    return $items;
}

function test_directory_policy(): void
{
    assert_same([], root_markdown_files(), 'root should not contain markdown documents');
    assert_same(['Database', 'Database.php', 'Deployment', 'Runtime', 'Runtime.php'], files_in('Core'), 'Core should contain Database and Runtime entrypoints plus the blank Deployment boundary');
    assert_same(['Database.php', 'Database/DatabaseCore.php', 'Runtime.php', 'Runtime/RuntimeCore.php'], recursive_php_files('Core'), 'Core should not contain deployment system PHP files');
    assert_same(['DatabaseCore.php'], recursive_php_files('Core/Database'), 'Database internal folder should not contain an entrypoint file');
    assert_same([], recursive_php_files('Core/Deployment'), 'Deployment boundary should not contain PHP files');
    assert_same(['.gitkeep'], files_in('Core/Deployment'), 'Deployment boundary should remain as marker only');
    assert_same(['RuntimeCore.php'], recursive_php_files('Core/Runtime'), 'Runtime internal folder should not contain an entrypoint file');
    assert_true(count(recursive_php_files('Core/Database')) <= 5, 'Database internal folder should keep at most five PHP files');
    assert_true(count(recursive_php_files('Core/Deployment')) <= 5, 'Deployment internal folder should keep at most five PHP files');
    assert_true(count(recursive_php_files('Core/Runtime')) <= 5, 'Runtime internal folder should keep at most five PHP files');
    assert_same(['.gitkeep'], files_in('Applications'), 'Applications should contain only the boundary marker');
    assert_same(['.gitkeep'], files_in('Docker'), 'Docker should contain only the boundary marker until Docker assets are added');
    assert_same(['ADLAIRE-ECOSYSTEM.md', 'AGENTS.md', 'README.md', 'project.md', 'testing.md', 'version-plan.md'], files_in('docs'), 'docs should contain all documents');
    assert_same(['debug.php'], files_in('tests'), 'tests should contain only debug.php');

    assert_true(is_dir(__DIR__ . '/../Core'), 'Core directory should exist');
    assert_true(is_dir(__DIR__ . '/../Applications'), 'Applications directory should exist');
    assert_true(is_dir(__DIR__ . '/../Docker'), 'Docker directory should exist');
    assert_true(is_dir(__DIR__ . '/../docs'), 'docs directory should exist');
    assert_true(is_dir(__DIR__ . '/../tests'), 'tests directory should exist');
}

function test_deployment_blank_reset(): void
{
    assert_same(false, class_exists('AdlaireDeployment', false), 'AdlaireDeployment class should be discarded');
    assert_same(false, is_file(__DIR__ . '/../Core/Deployment.php'), 'Deployment entrypoint should be discarded');
    assert_same(false, is_file(__DIR__ . '/../Core/Deployment/DeploymentCore.php'), 'Deployment internal implementation should be discarded');
    assert_same(true, is_dir(__DIR__ . '/../Core/Deployment'), 'Deployment boundary folder should remain');
}

function test_core_capabilities(): void
{
    $database = AdlaireDatabase::readiness();
    assert_same(true, $database['ready'], 'realtime database readiness should pass');
    assert_same(true, $database['planned_state']['adlaire_method'], 'database should use the Adlaire method');
    assert_same('baas_core_feature', $database['planned_state']['kind'], 'database should be a BaaS Core Feature');
    assert_same('realtime_database', $database['planned_state']['deployable_unit'], 'database should be a deployable unit');
    assert_same('undefined', $database['planned_state']['deployment_axis'], 'database should not depend on the blank deployment system');
    assert_same('event_log', $database['planned_state']['mode'], 'database should use event log mode');
    assert_same('sqlite', $database['planned_state']['selected_database'], 'database should select SQLite');
    assert_same('libsql', $database['planned_state']['compatibility_target'], 'database should keep libSQL as compatibility target');
    assert_same('sqlite_primary_libsql_compatible', $database['planned_state']['storage_policy'], 'database should use SQLite primary libSQL compatible policy');
    assert_same('sqlite_persistent', $database['planned_state']['runtime_execution'], 'database runtime should be SQLite persistent in v0.012');
    assert_same('in_memory', $database['planned_state']['fallback_runtime'], 'database should keep in-memory fallback');
    assert_same(true, $database['planned_state']['sqlite_persistence'], 'database should support SQLite persistence');
    assert_same(true, $database['planned_state']['wal_mode'], 'database should support WAL mode');
    assert_same(true, $database['planned_state']['integrity_check'], 'database should support integrity check');
    assert_same(true, $database['planned_state']['backup_restore'], 'database should support backup and restore');
    assert_same(true, $database['planned_state']['restore_validation'], 'database should validate restore payloads');
    assert_same(true, $database['planned_state']['operational_health'], 'database should expose operational health');
    assert_same(true, $database['planned_state']['integrity_audit'], 'database should expose integrity audit');
    assert_same(true, $database['planned_state']['diagnostics'], 'database should expose diagnostics');
    assert_same(true, $database['planned_state']['write_policy'], 'database should expose write policy');
    assert_same(true, $database['planned_state']['write_policy_enforcement'], 'database should enforce write policy');
    assert_same(true, $database['planned_state']['query_explain'], 'database should expose query explain');
    assert_same(true, $database['planned_state']['import_validation'], 'database should expose import validation');
    assert_same(true, $database['planned_state']['operational_guard'], 'database should expose operational guard');
    assert_same(true, $database['planned_state']['maintenance_mode'], 'database should expose maintenance mode');
    assert_same(true, $database['planned_state']['startup_self_check'], 'database should expose startup self check');
    assert_same(true, $database['planned_state']['backup_verification'], 'database should expose backup verification');
    assert_same(true, $database['planned_state']['restore_dry_run'], 'database should expose restore dry run');
    assert_same(true, $database['planned_state']['recovery_check'], 'database should expose recovery check');
    assert_same(true, $database['planned_state']['event_log_consistency_check'], 'database should expose event log consistency check');
    assert_same(true, $database['planned_state']['cursor_safety'], 'database should expose cursor safety');
    assert_same(true, $database['planned_state']['read_model_drift_detection'], 'database should expose read model drift detection');
    assert_same(true, $database['planned_state']['operational_metrics'], 'database should expose operational metrics');
    assert_same(true, $database['planned_state']['operational_report'], 'database should expose operational report');
    assert_same(true, $database['planned_state']['collection_stream'], 'database should support collection stream tracking');
    assert_same(true, $database['planned_state']['record_lookup'], 'database should support record lookup');
    assert_same(true, $database['planned_state']['record_listing'], 'database should support record listing');
    assert_same(true, $database['planned_state']['schema'], 'database should support collection schema');
    assert_same(true, $database['planned_state']['record_metadata'], 'database should support record metadata');
    assert_same(true, $database['planned_state']['query'], 'database should support query');
    assert_same(true, $database['planned_state']['query_explain'], 'database should support query explain');
    assert_same(true, $database['planned_state']['index_plan'], 'database should expose index planned state');
    assert_same(true, $database['planned_state']['migration_plan'], 'database should expose migration planned state');
    assert_same(true, $database['planned_state']['event_payload_summary'], 'database should expose event payload summary');
    assert_same(true, $database['planned_state']['subscription_model'], 'database should expose subscription model');
    assert_same(true, $database['planned_state']['transaction_boundary'], 'database should expose transaction boundary');
    assert_same(true, $database['planned_state']['snapshot_export'], 'database should support snapshot export');
    assert_same(true, $database['planned_state']['database_export'], 'database should support database export');
    assert_same(true, $database['planned_state']['snapshot_restore'], 'database should support snapshot restore');
    assert_same(true, $database['planned_state']['conflict_detection'], 'database should support conflict detection');
    assert_same(true, $database['planned_state']['event_replay'], 'database should support event replay');
    assert_same(true, $database['planned_state']['read_model_rebuild'], 'database should support read model rebuild');
    assert_same(true, $database['planned_state']['integrity_audit'], 'database should support integrity audit');
    assert_same(true, $database['planned_state']['diagnostics'], 'database should support diagnostics');
    assert_same(true, $database['planned_state']['write_policy'], 'database should support write policy');
    assert_same(true, $database['planned_state']['import_validation'], 'database should support import validation');
    assert_same(true, $database['planned_state']['collection_lifecycle'], 'database should support collection lifecycle');
    assert_same(true, $database['planned_state']['schema_versioning'], 'database should support schema versioning');
    assert_same(true, $database['planned_state']['bulk_import_dry_run'], 'database should support bulk import dry run');
    assert_same(true, $database['planned_state']['bulk_write'], 'database should support bulk write');
    assert_same(true, $database['planned_state']['record_restore'], 'database should support record restore');
    assert_same(true, $database['planned_state']['snapshot_compare'], 'database should support snapshot compare');
    assert_same(true, $database['planned_state']['event_replay_range'], 'database should support event replay range');
    assert_same(true, $database['planned_state']['query_cursor_pagination'], 'database should support query cursor pagination');
    assert_same(true, $database['planned_state']['collection_export_filter'], 'database should support collection export filter');
    assert_same(true, $database['planned_state']['data_redaction_export'], 'database should support data redaction export');
    assert_same(true, $database['planned_state']['record_ttl_plan'], 'database should expose record TTL plan');
    assert_same(true, $database['planned_state']['subscriber_checkpoint_plan'], 'database should expose subscriber checkpoint plan');
    assert_same(false, $database['planned_state']['libsql_runtime'], 'database should not implement libSQL runtime in v0.012');
    assert_same(true, $database['planned_state']['change_feed_filter'], 'database should support change feed filter');
    assert_same(true, $database['planned_state']['record_version_history'], 'database should support record version history');
    assert_same(true, $database['planned_state']['record_diff'], 'database should support record diff');
    assert_same(true, $database['planned_state']['snapshot_retention_plan'], 'database should expose snapshot retention plan');
    assert_same(true, $database['planned_state']['backup_manifest'], 'database should expose backup manifest');
    assert_same(true, $database['planned_state']['restore_preview'], 'database should support restore preview');
    assert_same(true, $database['planned_state']['collection_lock'], 'database should support collection lock');
    assert_same(true, $database['planned_state']['write_quota_guard'], 'database should support write quota guard');
    assert_same(true, $database['planned_state']['event_checkpoint'], 'database should support event checkpoint');
    assert_same(true, $database['planned_state']['operational_incident_report'], 'database should expose operational incident report');
    assert_same(true, $database['planned_state']['health_baseline'], 'database should expose health baseline');
    assert_same(true, $database['planned_state']['drift_baseline_compare'], 'database should expose drift baseline compare');
    assert_same(true, $database['planned_state']['write_safety_preflight'], 'database should expose write safety preflight');
    assert_same(true, $database['planned_state']['restore_safety_gate'], 'database should expose restore safety gate');
    assert_same(true, $database['planned_state']['backup_consistency_report'], 'database should expose backup consistency report');
    assert_same(true, $database['planned_state']['event_gap_report'], 'database should expose event gap report');
    assert_same(true, $database['planned_state']['corruption_suspect_report'], 'database should expose corruption suspect report');
    assert_same(true, $database['planned_state']['operational_risk_score'], 'database should expose operational risk score');
    assert_same(true, $database['planned_state']['recovery_decision_report'], 'database should expose recovery decision report');
    assert_same(true, $database['planned_state']['safe_mode'], 'database should expose safe mode');
    assert_same(true, $database['planned_state']['readonly_runtime_report'], 'database should expose readonly runtime report');
    assert_same(true, $database['planned_state']['incident_timeline'], 'database should expose incident timeline');
    assert_same(true, $database['planned_state']['write_intent_log'], 'database should expose write intent log');
    assert_same(true, $database['planned_state']['write_commit_verification'], 'database should expose write commit verification');
    assert_same(true, $database['planned_state']['recovery_simulation'], 'database should expose recovery simulation');
    assert_same(true, $database['planned_state']['restore_impact_report'], 'database should expose restore impact report');
    assert_same(true, $database['planned_state']['event_chain_integrity'], 'database should expose event chain integrity');
    assert_same(true, $database['planned_state']['snapshot_integrity_seal'], 'database should expose snapshot integrity seal');
    assert_same(true, $database['planned_state']['operational_runbook_report'], 'database should expose operational runbook report');
    assert_same(true, $database['planned_state']['degraded_mode'], 'database should expose degraded mode');
    assert_same(true, $database['planned_state']['critical_operation_guard'], 'database should expose critical operation guard');
    assert_same(true, $database['planned_state']['operational_evidence_bundle'], 'database should expose operational evidence bundle');
    assert_same(true, $database['planned_state']['pre_write_risk_evaluation'], 'database should expose pre-write risk evaluation');
    assert_same(true, $database['planned_state']['critical_write_two_step_guard'], 'database should expose critical write two-step guard');
    assert_same(true, $database['planned_state']['backup_restore_compatibility_check'], 'database should expose backup restore compatibility check');
    assert_same(true, $database['planned_state']['snapshot_seal_verification'], 'database should expose snapshot seal verification');
    assert_same(true, $database['planned_state']['operational_degradation_reason'], 'database should expose operational degradation reason');
    assert_same(true, $database['planned_state']['incident_severity_classification'], 'database should expose incident severity classification');
    assert_same(true, $database['planned_state']['recovery_readiness_report'], 'database should expose recovery readiness report');
    assert_same(true, $database['planned_state']['operation_freeze_policy'], 'database should expose operation freeze policy');
    assert_same(true, $database['planned_state']['data_durability_report'], 'database should expose data durability report');
    assert_same(true, $database['planned_state']['release_safety_evidence'], 'database should expose release safety evidence');
    assert_same(true, $database['planned_state']['operational_slo_report'], 'database should expose operational SLO report');
    assert_same(true, $database['planned_state']['write_failure_classification'], 'database should expose write failure classification');
    assert_same(true, $database['planned_state']['backup_freshness_report'], 'database should expose backup freshness report');
    assert_same(true, $database['planned_state']['restore_candidate_ranking'], 'database should expose restore candidate ranking');
    assert_same(true, $database['planned_state']['read_model_confidence_report'], 'database should expose read model confidence report');
    assert_same(true, $database['planned_state']['operational_window_policy'], 'database should expose operational window policy');
    assert_same(true, $database['planned_state']['recovery_drill_report'], 'database should expose recovery drill report');
    assert_same(true, $database['planned_state']['incident_evidence_digest'], 'database should expose incident evidence digest');
    assert_same(true, $database['planned_state']['data_lifecycle_guard'], 'database should expose data lifecycle guard');
    assert_same(true, $database['planned_state']['operational_handoff_report'], 'database should expose operational handoff report');
    assert_same('undefined', $database['planned_state']['access_rules'], 'database access rules should remain undefined');
    assert_same('none', $database['planned_state']['realtime_adapter'], 'database realtime adapter should be none');
    assert_same('pull_cursor', $database['planned_state']['stream_mode'], 'database stream mode should be pull cursor');
}

function test_realtime_database_data(): void
{
    AdlaireDatabase::reset();
    assert_true(isset(AdlaireDatabase::collections()['system']), 'system collection should be defined');
    assert_true(isset(AdlaireDatabase::collections()['application']), 'application collection should be defined');

    $systemDefinition = AdlaireDatabase::defineCollection('system', 'system', ['name' => 'string'], ['name'], 'hard');
    assert_same(['name' => 'string'], $systemDefinition['schema'], 'default collection should be redefinable');
    assert_same(['name'], AdlaireDatabase::collections()['system']['indexes'], 'default collection should keep redefined indexes');

    $defined = AdlaireDatabase::defineCollection('audit_log', 'system', ['action' => 'string', 'count' => 'integer'], ['action'], 'soft');
    assert_same('audit_log', $defined['name'], 'custom collection should be defined');
    assert_same('system', $defined['channel'], 'custom collection should keep channel');
    assert_same(['action' => 'string', 'count' => 'integer'], $defined['schema'], 'custom collection should keep schema');
    assert_same(['action'], $defined['indexes'], 'custom collection should keep custom indexes');
    assert_same('soft', $defined['delete_mode'], 'custom collection should keep delete mode');
    $taskDefinition = AdlaireDatabase::defineCollection('tasks', 'application', [
        'title' => ['type' => 'string', 'required' => true],
        'status' => ['type' => 'string', 'default' => 'draft', 'enum' => ['draft', 'done']],
        'score' => ['type' => 'integer', 'min' => 0, 'max' => 10],
        'tags' => ['type' => 'array', 'default' => []],
    ], ['status', 'score'], 'hard');
    assert_same('draft', $taskDefinition['schema']['status']['default'], 'rich schema should keep defaults');

    $created = AdlaireDatabase::create('system', ['name' => 'alpha']);
    assert_same('system', $created['collection'], 'created record should keep collection');
    assert_same('system', $created['channel'], 'created record should include collection channel');
    assert_same(1, $created['version'], 'created record should start at version 1');
    assert_same(1, $created['meta']['revision'], 'created record should start at revision 1');
    assert_same(1, $created['meta']['created_sequence'], 'created record should include logical created sequence');
    assert_same($created, AdlaireDatabase::get('system', $created['id']), 'record lookup should return the created record');
    assert_same(1, count(AdlaireDatabase::records('system')), 'record listing should include the created record');

    $updated = AdlaireDatabase::update('system', $created['id'], ['name' => 'beta']);
    assert_same(2, $updated['version'], 'updated record should increment version');
    assert_same(2, $updated['meta']['revision'], 'updated record should increment revision');
    assert_same(2, $updated['meta']['updated_sequence'], 'updated record should include logical updated sequence');
    assert_same('beta', $updated['data']['name'], 'updated record should merge data');
    assert_same($updated, AdlaireDatabase::get('system', $created['id']), 'record lookup should return the updated record');

    $snapshot = AdlaireDatabase::snapshot('system');
    assert_same('system', $snapshot['collection'], 'snapshot should keep collection');
    assert_same(1, count($snapshot['records']), 'snapshot should include current record');
    assert_same('evt_000002', $snapshot['cursor'], 'snapshot should expose the latest collection cursor');
    assert_true(is_string($snapshot['fingerprint']) && $snapshot['fingerprint'] !== '', 'snapshot fingerprint should be present');

    $events = AdlaireDatabase::events();
    assert_same(2, count($events), 'event log should include create and update');
    assert_same('create', $events[0]['type'], 'first event should be create');
    assert_same('update', $events[1]['type'], 'second event should be update');
    assert_same(1, $events[0]['sequence'], 'event should include stable sequence');
    assert_same('system', $events[0]['channel'], 'event should include collection channel');
    $changeFeed = AdlaireDatabase::changeFeedFilter(['collection' => 'system', 'type' => 'update']);
    assert_same(1, $changeFeed['count'], 'change feed filter should filter by collection and type');
    $history = AdlaireDatabase::recordVersionHistory('system', $created['id']);
    assert_same(2, $history['count'], 'record version history should include create and update');
    $diff = AdlaireDatabase::recordDiff('system', $created['id'], 1, 2);
    assert_same(['name'], $diff['changed_fields'], 'record diff should list changed fields');

    $afterFirst = AdlaireDatabase::events($events[0]['id']);
    assert_same(1, count($afterFirst), 'cursor should return events after the given event id');
    assert_same($events[1]['id'], $afterFirst[0]['id'], 'cursor should return the second event');

    $stream = AdlaireDatabase::stream('system', $events[0]['id']);
    assert_same('system', $stream['collection'], 'collection stream should keep collection');
    assert_same(1, count($stream['events']), 'collection stream should return events after cursor');
    assert_same($events[1]['id'], $stream['cursor'], 'collection stream should expose latest returned cursor');

    $cursor = AdlaireDatabase::cursor();
    assert_same(null, $cursor['after'], 'cursor should describe the initial cursor');
    assert_same($events[1]['id'], $cursor['latest'], 'cursor should expose latest event id');

    $deleted = AdlaireDatabase::delete('system', $created['id']);
    assert_same(true, $deleted['deleted'], 'delete should mark deletion result');
    assert_same(3, count(AdlaireDatabase::events()), 'event log should include delete');
    assert_same(0, count(AdlaireDatabase::snapshot('system')['records']), 'deleted record should be removed from snapshot');
    assert_same(null, AdlaireDatabase::get('system', $created['id']), 'deleted record should no longer be returned by lookup');

    $audit = AdlaireDatabase::create('audit_log', ['action' => 'deploy', 'count' => 1]);
    assert_same('deploy', $audit['data']['action'], 'schema collection should accept matching data');
    $auditSecond = AdlaireDatabase::create('audit_log', ['action' => 'deploy', 'count' => 10]);
    $auditThird = AdlaireDatabase::create('audit_log', ['action' => 'deploy', 'count' => 2]);

    $query = AdlaireDatabase::query('audit_log', [
        'where' => ['field' => 'action', 'equals' => 'deploy'],
        'order_by' => 'count',
        'direction' => 'desc',
        'limit' => 1,
    ]);
    assert_same(1, $query['count'], 'query should return limited matching records');
    assert_same($auditSecond['id'], $query['records'][0]['id'], 'query should sort numeric fields numerically');

    $indexPlan = AdlaireDatabase::indexPlan();
    assert_same('sqlite', $indexPlan['selected_database'], 'index plan should target SQLite');
    assert_same(['action'], $indexPlan['custom']['audit_log'], 'index plan should include custom collection indexes');

    $subscription = AdlaireDatabase::subscribe('audit_log');
    assert_same('collection_stream', $subscription['subscription'], 'subscription should use collection stream model');
    assert_same(false, $subscription['push'], 'subscription should not use push in v0.005');

    $export = AdlaireDatabase::exportSnapshot('audit_log');
    assert_same('audit_log', $export['collection'], 'snapshot export should keep collection');
    assert_same('sqlite', $export['selected_database'], 'snapshot export should keep selected database');
    assert_true(is_string($export['fingerprint']) && $export['fingerprint'] !== '', 'snapshot export fingerprint should be present');
    assert_same($export['fingerprint'], hash('sha256', json_encode([
        'collection' => $export['collection'],
        'definition' => $export['definition'],
        'snapshot' => $export['snapshot'],
        'cursor' => $export['cursor'],
        'selected_database' => $export['selected_database'],
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)), 'snapshot export fingerprint should cover the full export payload');

    $deletedAudit = AdlaireDatabase::delete('audit_log', $audit['id']);
    assert_same(true, $deletedAudit['deleted'], 'soft delete should mark deletion result');
    assert_same(null, AdlaireDatabase::get('audit_log', $audit['id']), 'soft deleted record should not be returned by lookup');
    assert_same(2, count(AdlaireDatabase::records('audit_log')), 'soft deleted record should be hidden from records');

    $transaction = AdlaireDatabase::transaction([
        ['type' => 'create', 'collection' => 'application', 'data' => ['name' => 'one']],
        ['type' => 'create', 'collection' => 'application', 'data' => ['name' => 'two']],
    ]);
    assert_same('txn_000001', $transaction['id'], 'transaction should have a stable id');
    assert_same(2, count($transaction['results']), 'transaction should return operation results');
    assert_same(2, count($transaction['events']), 'transaction should return created events');
    assert_same($transaction['events'][1]['id'], $transaction['cursor'], 'transaction should expose latest event cursor');

    $task = AdlaireDatabase::create('tasks', ['title' => 'Ship', 'score' => 3]);
    assert_same('draft', $task['data']['status'], 'schema default should be applied on create');
    $conflict = AdlaireDatabase::update('tasks', $task['id'], ['score' => 4], 99);
    assert_same(true, $conflict['conflict'], 'update should detect version conflict');
    assert_same(1, $conflict['current_version'], 'conflict should include current version');

    $patched = AdlaireDatabase::patch('tasks', $task['id'], [
        ['type' => 'set', 'field' => 'status', 'value' => 'done'],
        ['type' => 'increment', 'field' => 'score', 'value' => 2],
        ['type' => 'append', 'field' => 'tags', 'value' => 'release'],
        ['type' => 'unset', 'field' => 'title'],
    ], 1);
    assert_same(5, $patched['data']['score'], 'patch should increment numeric fields');
    assert_same(['release'], $patched['data']['tags'], 'patch should append list fields');
    assert_true(!array_key_exists('title', $patched['data']), 'patch should unset fields');

    $queryAdvanced = AdlaireDatabase::query('tasks', [
        'where' => ['field' => 'score', 'operator' => 'gte', 'value' => 5],
        'select' => ['status', 'score'],
        'count_only' => false,
    ]);
    assert_same(1, $queryAdvanced['count'], 'advanced query should support range operators');
    assert_same('done', $queryAdvanced['records'][0]['status'], 'advanced query should select requested fields');

    $stats = AdlaireDatabase::stats('tasks');
    assert_same(1, $stats['record_count'], 'stats should include record count');
    assert_true(is_string($stats['schema_fingerprint']) && $stats['schema_fingerprint'] !== '', 'stats should include schema fingerprint');

    $migration = AdlaireDatabase::migrationPlan();
    assert_same('planned', $migration['persistence_status'], 'migration plan should be planned');
    assert_same(2, $migration['schema_version'], 'migration plan should expose v0.012 schema version');
    assert_same(['collections', 'records', 'events', 'schema_versions', 'database_meta'], $migration['tables'], 'migration plan should include SQLite tables');
    assert_same(true, $migration['dry_run'], 'migration plan should support dry-run');
    assert_same(true, $migration['rollback_plan'], 'migration plan should expose rollback plan');

    $databaseExport = AdlaireDatabase::exportDatabase();
    assert_same('sqlite', $databaseExport['selected_database'], 'database export should keep selected database');
    assert_true(isset($databaseExport['collections']['tasks']), 'database export should include collection definitions');
    $baseline = AdlaireDatabase::healthBaseline();
    assert_true(is_string($baseline['fingerprint']) && $baseline['fingerprint'] !== '', 'health baseline should expose fingerprint');
    $baselineCompare = AdlaireDatabase::driftBaselineCompare($baseline);
    assert_same(false, $baselineCompare['drift'], 'baseline compare should not drift against current baseline');
    $manifest = AdlaireDatabase::backupManifest($databaseExport);
    assert_same(true, $manifest['valid'], 'backup manifest should validate export payload');
    assert_true($manifest['collection_count'] > 0, 'backup manifest should include collection count');
    $preview = AdlaireDatabase::restorePreview($databaseExport);
    assert_same(true, $preview['valid'], 'restore preview should validate export payload');
    assert_same(true, $preview['dry_run'], 'restore preview should be dry-run');
    assert_same(true, AdlaireDatabase::restoreSafetyGate($databaseExport)['allowed'], 'restore safety gate should allow valid export');
    assert_same(true, AdlaireDatabase::backupConsistencyReport($databaseExport)['consistent'], 'backup consistency report should accept valid export');
    $simulation = AdlaireDatabase::recoverySimulation($databaseExport);
    assert_same(true, $simulation['valid'], 'recovery simulation should validate export payload');
    assert_same(false, $simulation['will_restore'], 'recovery simulation should not restore automatically');
    $impact = AdlaireDatabase::restoreImpactReport($databaseExport);
    assert_same(true, $impact['valid'], 'restore impact report should accept valid export');
    assert_same(false, $impact['will_restore'], 'restore impact report should not restore automatically');

    $lifecycle = AdlaireDatabase::collectionLifecycle('tasks');
    assert_same('active', $lifecycle['state'], 'collection lifecycle should expose active state');
    $schemaVersion = AdlaireDatabase::schemaVersioning('tasks');
    assert_true(is_string($schemaVersion['schema_fingerprint']) && $schemaVersion['schema_fingerprint'] !== '', 'schema versioning should expose fingerprint');
    $bulkDryRun = AdlaireDatabase::bulkImportDryRun('tasks', [['title' => 'Dry', 'score' => 1]]);
    assert_same(true, $bulkDryRun['valid'], 'bulk import dry run should validate valid records');
    assert_same(false, $bulkDryRun['will_write'], 'bulk import dry run should not write');
    $bulk = AdlaireDatabase::bulkWrite([
        ['type' => 'create', 'collection' => 'tasks', 'data' => ['title' => 'Bulk 1', 'score' => 1]],
        ['type' => 'create', 'collection' => 'tasks', 'data' => ['title' => 'Bulk 2', 'score' => 2]],
    ]);
    assert_same('bulk_write', $bulk['operation'], 'bulk write should expose operation name');
    assert_same(true, $bulk['applied'], 'bulk write should apply operations');
    $cursorPage = AdlaireDatabase::queryCursor('tasks', ['order_by' => 'id', 'limit' => 1]);
    assert_same(1, $cursorPage['count'], 'query cursor should page records');
    assert_same(true, $cursorPage['has_more'], 'query cursor should expose has more');
    assert_same(1, $cursorPage['limit'], 'query cursor should expose limit');
    assert_same(null, $cursorPage['previous_cursor'], 'query cursor should expose previous cursor');
    $filteredExport = AdlaireDatabase::exportCollection('tasks', ['where' => ['field' => 'status', 'equals' => 'draft']]);
    assert_same(true, $filteredExport['filter_applied'], 'collection export should support filters');
    $redactedExport = AdlaireDatabase::exportCollection('tasks', [
        'where' => ['field' => 'status', 'equals' => 'draft'],
        'redact' => ['title'],
    ]);
    assert_same(['title'], $redactedExport['redacted_fields'], 'redaction export should list redacted fields');
    assert_same('field', $redactedExport['redaction_policy_preview']['mode'], 'redaction export should include policy preview');
    assert_same('[redacted]', $redactedExport['records'][0]['data']['title'], 'redaction export should redact field values');
    assert_same(['title'], AdlaireDatabase::dataRedactionExport('tasks', ['title'])['redacted_fields'], 'data redaction export should expose redacted fields');
    $beforeRestore = AdlaireDatabase::snapshot('tasks');
    $restoredRecord = AdlaireDatabase::restoreRecord('tasks', [
        'id' => 'rec_999999',
        'data' => ['title' => 'Restored', 'score' => 9],
        'version' => 1,
    ]);
    assert_same('rec_999999', $restoredRecord['id'], 'record restore should keep requested id');
    $afterRestore = AdlaireDatabase::snapshot('tasks');
    $compare = AdlaireDatabase::snapshotCompare($beforeRestore, $afterRestore);
    assert_same(false, $compare['same'], 'snapshot compare should detect changed snapshots');
    assert_true(in_array('rec_999999', $compare['added'], true), 'snapshot compare should list added records');
    $taskEventsForRange = AdlaireDatabase::events(null, 'tasks');
    $rangeReplay = AdlaireDatabase::eventReplayRange('tasks', $taskEventsForRange[0]['id'], $taskEventsForRange[1]['id']);
    assert_same(2, $rangeReplay['event_count'], 'event replay range should limit replayed events');

    $explain = AdlaireDatabase::queryExplain('tasks', ['where' => ['field' => 'score', 'operator' => 'gte', 'value' => 5]]);
    assert_same(true, $explain['uses_index'], 'query explain should detect indexed fields');
    assert_same(false, $explain['full_scan'], 'query explain should avoid full scan when index exists');
    $fullScanExplain = AdlaireDatabase::queryExplain('tasks', ['where' => ['field' => 'title', 'equals' => 'Ship']]);
    assert_same(true, $fullScanExplain['full_scan'], 'query explain should warn about full scans');

    $policy = AdlaireDatabase::writePolicy();
    assert_same('validated', $policy['write_mode'], 'write policy should use validated writes');
    assert_true(in_array('string', $policy['allowed_schema_types'], true), 'write policy should include string schema type');
    $quota = AdlaireDatabase::writeQuotaGuard(['record' => ['title' => 'Quota']]);
    assert_same(true, $quota['allowed'], 'write quota guard should allow small writes');
    assert_same(true, AdlaireDatabase::writeSafetyPreflight('tasks', ['title' => 'Preflight', 'score' => 1])['allowed'], 'write safety preflight should allow safe writes');
    $checkpoint = AdlaireDatabase::eventCheckpoint(AdlaireDatabase::cursor()['latest']);
    assert_true($checkpoint['event_count'] > 0, 'event checkpoint should summarize events');
    assert_same(true, AdlaireDatabase::eventGapReport()['valid'], 'event gap report should pass current event log');
    assert_same(true, AdlaireDatabase::eventChainIntegrity()['valid'], 'event chain integrity should pass current event log');
    $seal = AdlaireDatabase::snapshotIntegritySeal('tasks');
    assert_true(is_string($seal['seal']) && $seal['seal'] !== '', 'snapshot integrity seal should expose seal');
    assert_same(false, AdlaireDatabase::corruptionSuspectReport()['suspected'], 'corruption suspect report should be clear');
    assert_same('low', AdlaireDatabase::operationalRiskScore()['level'], 'operational risk score should be low for valid state');
    assert_same('continue_observation', AdlaireDatabase::recoveryDecisionReport($databaseExport)['decision'], 'recovery decision should observe healthy state');
    assert_true(AdlaireDatabase::writeIntentLog()['count'] > 0, 'write intent log should capture write attempts');
    assert_same(true, AdlaireDatabase::writeCommitVerification()['verified'], 'write commit verification should pass current state');
    assert_same(true, AdlaireDatabase::criticalOperationGuard('delete', 'tasks')['allowed'], 'critical operation guard should allow healthy delete');
    $degraded = AdlaireDatabase::setDegradedMode(true);
    assert_same(false, $degraded['critical_operations_allowed'], 'degraded mode should block critical operations');
    try {
        AdlaireDatabase::delete('tasks', $task['id']);
        throw new TestFailure('degraded mode should block critical delete');
    } catch (RuntimeException $exception) {
        assert_true(str_contains($exception->getMessage(), 'critical operation'), 'degraded mode failure should explain critical operation');
    }
    AdlaireDatabase::setDegradedMode(false);
    assert_same(true, AdlaireDatabase::degradedMode()['critical_operations_allowed'], 'degraded mode should allow critical operations after disabled');
    assert_same('continue_observation', AdlaireDatabase::operationalRunbookReport()['action'], 'operational runbook should observe healthy state');
    $evidence = AdlaireDatabase::operationalEvidenceBundle($databaseExport);
    assert_same('v0.012', $evidence['version'], 'operational evidence bundle should expose v0.012');
    assert_true(is_string($evidence['fingerprint']) && $evidence['fingerprint'] !== '', 'operational evidence bundle should expose fingerprint');
    assert_same(true, AdlaireDatabase::preWriteRiskEvaluation('tasks', ['title' => 'Risk', 'score' => 1])['allowed'], 'pre-write risk evaluation should allow healthy writes');
    $twoStep = AdlaireDatabase::criticalWriteTwoStepGuard('record_restore', 'tasks');
    assert_same(true, $twoStep['critical'], 'critical write two-step guard should classify record restore as critical');
    assert_same(true, $twoStep['allowed'], 'critical write two-step guard should allow matching latest record restore intent');
    $currentExport = AdlaireDatabase::exportDatabase();
    assert_same(true, AdlaireDatabase::backupRestoreCompatibilityCheck($currentExport)['compatible'], 'backup restore compatibility check should accept current export');
    assert_same(true, AdlaireDatabase::snapshotSealVerification('tasks', $seal)['valid'], 'snapshot seal verification should accept current seal');
    assert_same(false, AdlaireDatabase::operationalDegradationReason($databaseExport)['degraded'], 'operational degradation reason should be clear for healthy state');
    assert_same('low', AdlaireDatabase::incidentSeverityClassification()['severity'], 'incident severity should be low for healthy state');
    assert_same('ready', AdlaireDatabase::recoveryReadinessReport($currentExport)['status'], 'recovery readiness should be ready for current export');
    $freeze = AdlaireDatabase::operationFreezePolicy();
    assert_same(true, $freeze['read_allowed'], 'operation freeze policy should allow reads');
    assert_same(true, $freeze['critical_write_allowed'], 'operation freeze policy should allow critical writes when healthy');
    assert_same(true, AdlaireDatabase::dataDurabilityReport($currentExport)['durable'], 'data durability report should pass current state');
    $releaseEvidence = AdlaireDatabase::releaseSafetyEvidence($currentExport);
    assert_same(true, $releaseEvidence['safe'], 'release safety evidence should be safe for current state');
    assert_true(is_string($releaseEvidence['fingerprint']) && $releaseEvidence['fingerprint'] !== '', 'release safety evidence should expose fingerprint');
    assert_same('met', AdlaireDatabase::operationalSloReport($currentExport)['status'], 'operational SLO report should be met for current state');
    assert_same('schema_error', AdlaireDatabase::writeFailureClassification('Record data does not match collection schema.')['classification'], 'write failure classification should classify schema errors');
    assert_same('fresh', AdlaireDatabase::backupFreshnessReport($currentExport)['status'], 'backup freshness report should classify current export as fresh');
    $ranking = AdlaireDatabase::restoreCandidateRanking([$currentExport]);
    assert_same(0, $ranking['best']['index'], 'restore candidate ranking should rank current export first');
    assert_same('high', AdlaireDatabase::readModelConfidenceReport('tasks')['confidence'], 'read model confidence should be high for current state');
    $window = AdlaireDatabase::operationalWindowPolicy($currentExport);
    assert_same(true, $window['normal_write_allowed'], 'operational window policy should allow normal writes');
    assert_same(true, $window['backup_verification_allowed'], 'operational window policy should allow backup verification');
    $drill = AdlaireDatabase::recoveryDrillReport($currentExport);
    assert_same(true, $drill['drill'], 'recovery drill report should mark drill mode');
    assert_same(false, $drill['will_restore'], 'recovery drill report should not restore automatically');
    $digest = AdlaireDatabase::incidentEvidenceDigest($currentExport);
    assert_same('low', $digest['severity'], 'incident evidence digest should expose low severity');
    assert_same(true, AdlaireDatabase::dataLifecycleGuard('record_restore', 'tasks')['allowed'], 'data lifecycle guard should allow healthy record restore');
    $handoff = AdlaireDatabase::operationalHandoffReport($currentExport);
    assert_same('met', $handoff['current_status'], 'operational handoff should include SLO status');
    assert_same('continue_observation', $handoff['next_action'], 'operational handoff should keep observation for healthy state');
    assert_same('v0.012', AdlaireDatabase::operationalBaselineSnapshot($currentExport)['version'], 'operational baseline snapshot should expose v0.012');
    assert_same('normal', AdlaireDatabase::writeAnomalyDetector()['status'], 'write anomaly detector should be normal for healthy state');
    assert_same(100, AdlaireDatabase::dataConsistencyScore($currentExport)['score'], 'data consistency score should be perfect for current export');
    assert_same('ready', AdlaireDatabase::productionReadinessGate($currentExport)['status'], 'production readiness gate should be ready for healthy state');
    assert_true(in_array('continue_observation', AdlaireDatabase::operatorActionChecklist($currentExport)['actions'], true), 'operator checklist should continue observation');
    assert_same('within_budget', AdlaireDatabase::operationalDriftBudget($currentExport)['status'], 'operational drift budget should be within budget');
    assert_same('strong', AdlaireDatabase::dataConsistencyScore($currentExport)['status'], 'data consistency status should be strong');
    assert_same(false, AdlaireDatabase::incidentContainmentPolicy($currentExport)['automatic_freeze'], 'incident containment policy should not freeze automatically');
    assert_same(false, AdlaireDatabase::backupRotationPolicyReport([$currentExport])['automatic_delete'], 'backup rotation policy should not delete automatically');
    assert_same('normal', AdlaireDatabase::stateTransitionAudit()['state'], 'state transition audit should be normal');
    assert_same('stable', AdlaireDatabase::operationalHealthTrend($currentExport)['trend'], 'operational health trend should be stable');
    assert_same('trusted', AdlaireDatabase::backupTrustScore($currentExport)['status'], 'backup trust score should trust current export');
    assert_same('low', AdlaireDatabase::operationalSaturationReport()['status'], 'operational saturation should be low');
    assert_same('safe', AdlaireDatabase::safeMaintenanceWindowReport($currentExport)['status'], 'safe maintenance window should be safe');
    assert_same('high', AdlaireDatabase::dataRecoveryConfidence($currentExport)['confidence'], 'data recovery confidence should be high');
    assert_same('admit', AdlaireDatabase::writeAdmissionControlReport($currentExport)['decision'], 'write admission control should admit healthy writes');
    assert_same('stable', AdlaireDatabase::schemaStabilityReport()['status'], 'schema stability should be stable');
    assert_same('safe', AdlaireDatabase::eventReplayFeasibilityReport()['status'], 'event replay feasibility should be safe');
    assert_same('within_limits', AdlaireDatabase::sqliteOperationalLimitsReport()['status'], 'SQLite limits should be within limits');
    assert_same(true, AdlaireDatabase::productionSafetyBoard($currentExport)['readiness'], 'production safety board should include readiness');
    assert_same('high', AdlaireDatabase::operationalControlTower($currentExport)['recovery_confidence'], 'operational control tower should expose recovery confidence');
    assert_same('normal', AdlaireDatabase::writePressureReport()['status'], 'write pressure should be normal');
    assert_same(true, AdlaireDatabase::eventChainTrustReport()['trusted'], 'event chain trust should be trusted');
    assert_same(true, AdlaireDatabase::readConsistencyVerification()['consistent'], 'read consistency verification should pass');
    assert_same(true, AdlaireDatabase::degradedModeExitCriteria($currentExport)['can_exit'], 'degraded mode exit criteria should pass');
    assert_same('covered', AdlaireDatabase::backupExposureReport($currentExport)['status'], 'backup exposure should be covered');
    assert_true(isset(AdlaireDatabase::productionOperationsPacket($currentExport)['control_tower']), 'production operations packet should include control tower');
    assert_same(false, AdlaireDatabase::snapshotRetentionPlan()['automatic_deletion'], 'snapshot retention plan should not delete automatically');
    $lock = AdlaireDatabase::setCollectionLock('tasks', true);
    assert_same(false, $lock['write_allowed'], 'collection lock should block writes');
    try {
        AdlaireDatabase::create('tasks', ['title' => 'Locked']);
        throw new TestFailure('collection lock should block create');
    } catch (RuntimeException $exception) {
        assert_true(str_contains($exception->getMessage(), 'locked'), 'collection lock failure should explain lock');
    }
    AdlaireDatabase::setCollectionLock('tasks', false);

    $maintenance = AdlaireDatabase::setMaintenanceMode(true);
    assert_same(false, $maintenance['write_allowed'], 'maintenance mode should block writes');
    try {
        AdlaireDatabase::create('tasks', ['title' => 'Blocked']);
        throw new TestFailure('maintenance mode should block create');
    } catch (RuntimeException $exception) {
        assert_true(str_contains($exception->getMessage(), 'maintenance'), 'maintenance failure should explain mode');
    }
    AdlaireDatabase::setMaintenanceMode(false);
    assert_same(true, AdlaireDatabase::operationalGuard()['ready'], 'operational guard should be ready after maintenance mode is disabled');
    $safe = AdlaireDatabase::setSafeMode(true);
    assert_same(false, $safe['write_allowed'], 'safe mode should block writes');
    try {
        AdlaireDatabase::create('tasks', ['title' => 'Safe Blocked']);
        throw new TestFailure('safe mode should block create');
    } catch (RuntimeException $exception) {
        assert_true(str_contains($exception->getMessage(), 'safe mode'), 'safe mode failure should explain mode');
    }
    AdlaireDatabase::setSafeMode(false);
    assert_same(true, AdlaireDatabase::readonlyRuntimeReport()['write_allowed'], 'readonly runtime report should allow writes after safe mode is disabled');

    $validImport = AdlaireDatabase::importValidation('tasks', [['title' => 'Import', 'score' => 1]]);
    assert_same(true, $validImport['valid'], 'import validation should accept valid records');
    $invalidImport = AdlaireDatabase::importValidation('tasks', [['score' => 1]]);
    assert_same(false, $invalidImport['valid'], 'import validation should reject missing required fields');
    $unknownImport = AdlaireDatabase::importValidation('tasks', [['title' => 'Import', 'unknown' => true]]);
    assert_same(1, $unknownImport['summary']['unknown_field'], 'import validation should classify unknown fields');

    $audit = AdlaireDatabase::auditIntegrity();
    assert_same(true, $audit['valid'], 'integrity audit should pass for current data');
    $diagnostics = AdlaireDatabase::diagnostics();
    assert_same(true, $diagnostics['ready'], 'diagnostics should be ready for valid data');
    assert_same('database_ready', $diagnostics['release_readiness_hint'], 'diagnostics should expose release readiness hint');
    assert_same(true, AdlaireDatabase::startupSelfCheck()['ready'], 'startup self check should pass');
    assert_same(true, AdlaireDatabase::eventLogConsistencyCheck()['valid'], 'event log consistency should pass');
    assert_same(true, AdlaireDatabase::cursorSafety(AdlaireDatabase::cursor()['latest'])['safe'], 'cursor safety should accept known cursor');
    assert_same(false, AdlaireDatabase::readModelDriftDetection('tasks')['drift'], 'read model drift detection should pass');
    assert_true(AdlaireDatabase::operationalMetrics()['event_count'] > 0, 'operational metrics should expose event count');
    assert_same('v0.012', AdlaireDatabase::operationalReport()['version'], 'operational report should expose v0.012');
    assert_same('v0.012', AdlaireDatabase::operationalIncidentReport()['version'], 'operational incident report should expose v0.012');
    assert_true(AdlaireDatabase::incidentTimeline()['count'] > 0, 'incident timeline should include runtime items');
    assert_same(false, AdlaireDatabase::recordTtlPlan()['runtime_enforced'], 'TTL plan should remain plan only');
    assert_same('event_cursor', AdlaireDatabase::subscriberCheckpointPlan()['checkpoint_source'], 'subscriber checkpoint plan should use event cursor');

    $taskExport = AdlaireDatabase::exportSnapshot('tasks');
    AdlaireDatabase::defineCollection('restored_tasks', 'application', $taskDefinition['schema'], $taskDefinition['indexes'], 'hard');
    $restored = AdlaireDatabase::restoreSnapshot('restored_tasks', [
        'definition' => ['channel' => 'application', 'schema' => $taskDefinition['schema'], 'indexes' => $taskDefinition['indexes'], 'delete_mode' => 'hard'],
        'snapshot' => $taskExport['snapshot'],
    ]);
    assert_same(count($taskExport['snapshot']['records']), count($restored['records']), 'restore snapshot should restore records');

    $rebuilt = AdlaireDatabase::rebuildSnapshot('tasks');
    assert_same(count($taskExport['snapshot']['records']), count($rebuilt['records']), 'read model rebuild should replay visible records');
    assert_same($rebuilt['fingerprint'], AdlaireDatabase::replay('tasks', AdlaireDatabase::events(null, 'tasks'))['fingerprint'], 'replay and rebuild should agree');

    $taskEvents = AdlaireDatabase::events(null, 'tasks');
    assert_true(array_key_exists('before_hash', $taskEvents[1]), 'event should include before hash');
    assert_true(array_key_exists('after_hash', $taskEvents[1]), 'event should include after hash');
    assert_true(in_array('score', $taskEvents[1]['changed_fields'], true), 'event should include changed fields');
    assert_same('undefined', AdlaireDatabase::accessRules()['access_rules'], 'access rules should remain undefined');
    assert_same('none', AdlaireDatabase::realtimeAdapter()['adapter'], 'realtime adapter should be none');
}

function test_sqlite_persistence(): void
{
    AdlaireDatabase::reset();
    $path = sys_get_temp_dir() . '/adlaire_realtime_' . str_replace('.', '_', uniqid('', true)) . '.sqlite';
    @unlink($path);
    @unlink($path . '-wal');
    @unlink($path . '-shm');

    $status = AdlaireDatabase::enableSQLite($path);
    assert_same(true, $status['enabled'], 'SQLite persistence should be enabled');
    assert_same('sqlite_persistent', $status['runtime_execution'], 'SQLite runtime should be persistent');
    assert_same(true, $status['wal_mode'], 'SQLite persistence should use WAL');
    assert_same('ok', $status['integrity_check'], 'SQLite integrity check should pass');
    assert_same('sqlite', AdlaireDatabase::collections()['system']['storage'], 'default system collection should use SQLite when persistence is enabled');
    assert_same('sqlite', AdlaireDatabase::collections()['application']['storage'], 'default application collection should use SQLite when persistence is enabled');

    $definition = AdlaireDatabase::defineCollection('durable_tasks', 'application', [
        'title' => ['type' => 'string', 'required' => true],
        'status' => ['type' => 'string', 'default' => 'open'],
    ], ['status'], 'hard');
    assert_same('sqlite', $definition['storage'], 'SQLite collection should be stored as sqlite');
    AdlaireDatabase::defineCollection('durable_audit', 'system', ['action' => 'string'], ['action'], 'soft');

    $created = AdlaireDatabase::create('durable_tasks', ['title' => 'Persist']);
    $updated = AdlaireDatabase::update('durable_tasks', $created['id'], ['status' => 'done']);
    assert_same('done', $updated['data']['status'], 'SQLite-backed record should update');
    $soft = AdlaireDatabase::create('durable_audit', ['action' => 'hide']);
    AdlaireDatabase::delete('durable_audit', $soft['id']);

    $export = AdlaireDatabase::exportDatabase();
    assert_same(true, $export['storage_status']['enabled'], 'database export should include enabled storage status');
    assert_true(isset($export['collections']['durable_tasks']), 'database export should include durable collection');
    assert_true(is_string($export['fingerprint']) && $export['fingerprint'] !== '', 'database export should include stable fingerprint');
    assert_same(true, AdlaireDatabase::validateDatabaseExport($export)['valid'], 'database export should validate');
    $tamperedExport = $export;
    $tamperedExport['selected_database'] = 'other';
    assert_same(false, AdlaireDatabase::validateDatabaseExport($tamperedExport)['valid'], 'tampered database export should fail validation');

    $eventCountBeforeFailure = count(AdlaireDatabase::events(null, 'durable_tasks'));
    $recordCountBeforeFailure = count(AdlaireDatabase::records('durable_tasks'));
    try {
        AdlaireDatabase::transaction([
            ['type' => 'create', 'collection' => 'durable_tasks', 'data' => ['title' => 'Rolled back']],
            ['type' => 'create', 'collection' => 'missing_collection', 'data' => ['title' => 'Invalid']],
        ]);
        throw new TestFailure('transaction failure should throw');
    } catch (InvalidArgumentException $exception) {
        assert_true(str_contains($exception->getMessage(), 'Collection'), 'transaction failure should expose collection error');
    }
    assert_same($recordCountBeforeFailure, count(AdlaireDatabase::records('durable_tasks')), 'failed transaction should roll back records');
    assert_same($eventCountBeforeFailure, count(AdlaireDatabase::events(null, 'durable_tasks')), 'failed transaction should roll back events');

    AdlaireDatabase::reset();
    $reloadStatus = AdlaireDatabase::enableSQLite($path);
    assert_same(true, $reloadStatus['enabled'], 'SQLite persistence should re-enable from existing file');
    assert_same('sqlite', AdlaireDatabase::collections()['system']['storage'], 'reloaded default system collection should use SQLite');
    $loaded = AdlaireDatabase::get('durable_tasks', $created['id']);
    assert_true(is_array($loaded), 'SQLite record should load after runtime reset');
    assert_same('done', $loaded['data']['status'], 'SQLite record should preserve updated data');
    assert_same(2, count(AdlaireDatabase::events(null, 'durable_tasks')), 'SQLite event log should load after runtime reset');
    assert_same(1, count(AdlaireDatabase::records('durable_tasks')), 'SQLite reload should not include rolled back records');
    assert_same(null, AdlaireDatabase::get('durable_audit', $soft['id']), 'SQLite soft deleted record should stay hidden after reload');
    assert_same(2, count(AdlaireDatabase::events(null, 'durable_audit')), 'SQLite soft delete events should reload');

    $health = AdlaireDatabase::operationalHealth();
    assert_same(true, $health['ready'], 'SQLite operational health should be ready');
    assert_same('ok', $health['storage']['integrity_check'], 'operational health should include integrity check');
    assert_same(true, $health['audit']['valid'], 'operational health should include audit result');

    try {
        AdlaireDatabase::restoreDatabase(['selected_database' => 'sqlite']);
        throw new TestFailure('invalid database restore should throw');
    } catch (InvalidArgumentException) {
        assert_same(1, count(AdlaireDatabase::records('durable_tasks')), 'invalid restore should not clear existing records');
    }

    $restored = AdlaireDatabase::restoreDatabase($export);
    assert_true(isset($restored['collections']['durable_tasks']), 'database restore should restore collection definitions');
    assert_same(1, count($restored['snapshots']['durable_tasks']['records']), 'database restore should restore records');
    assert_same($export['fingerprint'], $restored['fingerprint'], 'database restore should keep stable export fingerprint');

    AdlaireDatabase::reset();
    @unlink($path);
    @unlink($path . '-wal');
    @unlink($path . '-shm');
}

function test_release_conditions(): void
{
    assert_same('v0.012', AdlaireDatabase::plannedState()['version'], 'database version should be v0.012 without deployment dependency');
    assert_same(false, method_exists('AdlaireDatabase', 'release'), 'Realtime Database should not provide a deployment release gate');
}

function test_documents(): void
{
    $spec = file_get_contents(__DIR__ . '/../docs/ADLAIRE-ECOSYSTEM.md');
    $readme = file_get_contents(__DIR__ . '/../docs/README.md');
    $agents = file_get_contents(__DIR__ . '/../docs/AGENTS.md');
    $projectDoc = file_get_contents(__DIR__ . '/../docs/project.md');
    $testingDoc = file_get_contents(__DIR__ . '/../docs/testing.md');
    $versionPlan = file_get_contents(__DIR__ . '/../docs/version-plan.md');

    assert_true(is_string($spec) && str_contains($spec, 'v0.012'), 'spec should describe v0.012');
    assert_true(is_string($spec) && str_contains($spec, 'Selected database | SQLite'), 'spec should select SQLite');
    assert_true(is_string($spec) && str_contains($spec, 'libSQLはSQLite互換の将来拡張として決定済み'), 'spec should define libSQL as decided future SQLite-compatible extension');
    assert_true(is_string($spec) && str_contains($spec, 'Realtime Database BaaS Contract'), 'spec should define the realtime database BaaS contract');
    assert_true(is_string($spec) && str_contains($spec, 'Collection Schema'), 'spec should define collection schema');
    assert_true(is_string($spec) && str_contains($spec, 'Snapshot Export'), 'spec should define snapshot export');
    assert_true(is_string($spec) && str_contains($spec, 'Migration planned state'), 'spec should define migration planned state');
    assert_true(is_string($spec) && str_contains($spec, 'Realtime Adapter Boundary'), 'spec should define realtime adapter boundary');
    assert_true(is_string($spec) && str_contains($spec, 'SQLite Persistence'), 'spec should define SQLite persistence');
    assert_true(is_string($spec) && str_contains($spec, 'Operational Health'), 'spec should define operational health');
    assert_true(is_string($spec) && str_contains($spec, 'Integrity audit'), 'spec should define integrity audit');
    assert_true(is_string($spec) && str_contains($spec, 'Query explain'), 'spec should define query explain');
    assert_true(is_string($spec) && str_contains($spec, 'Import validation'), 'spec should define import validation');
    assert_true(is_string($readme) && str_contains($readme, 'BaaS Project'), 'README should describe the BaaS Project');
    assert_true(is_string($agents) && str_contains($agents, '仕様確定案'), 'AGENTS should define the development order');
    assert_true(is_string($projectDoc) && str_contains($projectDoc, 'docs/ADLAIRE-ECOSYSTEM.md'), 'project doc should delegate details to the spec');
    assert_true(is_string($testingDoc) && str_contains($testingDoc, 'php tests/debug.php'), 'testing doc should describe the official test entrypoint');
    assert_true(is_string($testingDoc) && str_contains($testingDoc, 'v0.012 Test Scope'), 'testing doc should describe v0.012 test scope');
    assert_true(is_string($testingDoc) && str_contains($testingDoc, 'php_source_code_based'), 'testing doc should define PHP source-code based tests');
    assert_true(is_string($testingDoc) && str_contains($testingDoc, 'docker_production_like_environment'), 'testing doc should define future Docker production-like tests');
    assert_true(is_string($spec) && str_contains($spec, 'docker_test_mode: future_production_like_environment'), 'spec should define future Docker test mode');
    assert_true(is_string($spec) && str_contains($spec, 'docs/testing.md'), 'spec should assign testing documents to docs/testing.md');
    assert_true(is_string($spec) && str_contains($spec, 'docs/version-plan.md'), 'spec should assign version plan documents to docs/version-plan.md');
    assert_true(is_string($spec) && str_contains($spec, 'すべてのドキュメントは`docs/`へ集約する'), 'spec should centralize all documents under docs');
    assert_true(is_string($versionPlan) && str_contains($versionPlan, 'version: v0.012'), 'version plan should describe v0.012');
    assert_true(is_string($versionPlan) && str_contains($versionPlan, 'status: version_plan_approved'), 'version plan should be approved');
    assert_true(is_string($versionPlan) && str_contains($versionPlan, 'implementation: approved'), 'version plan should approve implementation');
    assert_true(is_string($versionPlan) && str_contains($versionPlan, 'remote_sync: not_adopted'), 'version plan should reject remote sync');
    assert_true(is_string($versionPlan) && str_contains($versionPlan, 'implementation_status: implemented'), 'version plan should record implementation status');
    assert_true(is_string($versionPlan) && str_contains($versionPlan, '要点のみを簡潔に明記'), 'version plan should require concise entries');
}

$tests = [
    'directory_policy' => test_directory_policy(...),
    'deployment_blank_reset' => test_deployment_blank_reset(...),
    'core_capabilities' => test_core_capabilities(...),
    'realtime_database_data' => test_realtime_database_data(...),
    'sqlite_persistence' => test_sqlite_persistence(...),
    'release_conditions' => test_release_conditions(...),
    'documents' => test_documents(...),
];

foreach ($tests as $name => $test) {
    try {
        $test();
        echo "PASS {$name}\n";
    } catch (Throwable $exception) {
        fwrite(STDERR, "FAIL {$name}: {$exception->getMessage()}\n");
        exit(1);
    }
}

echo "OK\n";
