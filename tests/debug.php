<?php

declare(strict_types=1);

require_once __DIR__ . '/../Core/Database.php';
require_once __DIR__ . '/../Core/Auth.php';

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
    assert_same(['Auth', 'Auth.php', 'Database', 'Database.php', 'Deployment', 'EventLog.php'], files_in('Core'), 'Core should contain common foundations, entrypoints, Auth, Database, and the blank Deployment boundary');
    assert_same(['Auth.php', 'Auth/AuthCore.php', 'Auth/AuthOperations.php', 'Auth/AuthStorage.php', 'Database.php', 'Database/DatabaseCore.php', 'Database/DatabaseOperations.php', 'Database/DatabaseStorage.php', 'EventLog.php'], recursive_php_files('Core'), 'Core should not contain Runtime or deployment system PHP files');
    assert_same(['AuthCore.php', 'AuthOperations.php', 'AuthStorage.php'], recursive_php_files('Core/Auth'), 'Auth internal folder should contain exactly three PHP files');
    assert_same(['DatabaseCore.php', 'DatabaseOperations.php', 'DatabaseStorage.php'], recursive_php_files('Core/Database'), 'Database internal folder should contain exactly three PHP files');
    assert_same([], recursive_php_files('Core/Deployment'), 'Deployment boundary should not contain PHP files');
    assert_same(['.gitkeep'], files_in('Core/Deployment'), 'Deployment boundary should remain as marker only');
    assert_same(true, is_file(__DIR__ . '/../Core/EventLog.php'), 'Event Log common foundation should be a single root file');
    assert_same(true, is_file(__DIR__ . '/../Core/Auth.php'), 'Auth entrypoint should be a single root file');
    assert_same(false, is_dir(__DIR__ . '/../Core/EventLog'), 'Event Log folder should be prohibited');
    assert_same(false, is_file(__DIR__ . '/../Core/Runtime.php'), 'Runtime entrypoint should be removed');
    assert_same(false, is_dir(__DIR__ . '/../Core/Runtime'), 'Runtime internal folder should be removed');
    assert_same(3, count(recursive_php_files('Core/Database')), 'Database internal folder should keep exactly three PHP files');
    assert_same(3, count(recursive_php_files('Core/Auth')), 'Auth internal folder should keep exactly three PHP files');
    assert_true(count(recursive_php_files('Core/Deployment')) <= 5, 'Deployment internal folder should keep at most five PHP files');
    assert_same(['.gitkeep'], files_in('Applications'), 'Applications should contain only the boundary marker');
    assert_same(['.gitkeep'], files_in('Docker'), 'Docker should contain only the boundary marker until Docker assets are added');
    assert_same(['ADLAIRE-ECOSYSTEM.md', 'AGENTS.md', 'README.md', 'testing.md', 'version-plan.md'], files_in('docs'), 'docs should contain all documents');
    assert_same(['debug.php'], files_in('tests'), 'tests should contain only debug.php');

    assert_true(is_dir(__DIR__ . '/../Core'), 'Core directory should exist');
    assert_true(is_dir(__DIR__ . '/../Applications'), 'Applications directory should exist');
    assert_true(is_dir(__DIR__ . '/../Docker'), 'Docker directory should exist');
    assert_true(is_dir(__DIR__ . '/../docs'), 'docs directory should exist');
    assert_true(is_dir(__DIR__ . '/../tests'), 'tests directory should exist');
}

function test_mandatory_requirements(): void
{
    assert_same(true, extension_loaded('json'), 'json extension should be available');
    assert_same(true, extension_loaded('PDO'), 'PDO extension should be available');
    assert_same(true, extension_loaded('pdo_sqlite'), 'pdo_sqlite extension should be available');
    assert_same(true, class_exists(PDO::class), 'PDO class should be available');
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
    assert_same(true, class_exists(AdlaireEventLog::class), 'Event Log common foundation class should exist');
    assert_same('Core/EventLog.php', AdlaireEventLog::role()['file'], 'Event Log role should expose its Core file');
    assert_same(false, AdlaireEventLog::role()['entrypoint'], 'Event Log role should not be entrypoint');
    assert_same('baas_core_feature', $database['planned_state']['kind'], 'database should be a BaaS Core Feature');
    assert_same('realtime_database', $database['planned_state']['deployable_unit'], 'database should be a deployable unit');
    assert_same('undefined', $database['planned_state']['deployment_axis'], 'database should not depend on the blank deployment system');
    assert_same('event_log', $database['planned_state']['mode'], 'database should use event log mode');
    assert_same('sqlite', $database['planned_state']['selected_database'], 'database should select SQLite');
    assert_same('libsql', $database['planned_state']['compatibility_target'], 'database should keep libSQL as compatibility target');
    assert_same('sqlite_primary_libsql_compatible', $database['planned_state']['storage_policy'], 'database should use SQLite primary libSQL compatible policy');
    assert_same('v0.019', $database['planned_state']['version'], 'database version should be v0.019');
    assert_same(true, $database['planned_state']['runtime_removed'], 'Runtime should be removed in v0.019');
    assert_same('prohibited', $database['planned_state']['runtime_replacement_category'], 'Runtime replacement category should be prohibited');
    assert_same(true, $database['planned_state']['auth_core_feature'], 'Auth should be a Core feature in v0.019');
    assert_same('Core/Auth.php', $database['planned_state']['auth_entrypoint'], 'Auth entrypoint should be Core/Auth.php');
    assert_same('Core/Auth', $database['planned_state']['auth_folder'], 'Auth internal folder should be Core/Auth');
    assert_same(['AuthCore.php', 'AuthStorage.php', 'AuthOperations.php'], $database['planned_state']['auth_files'], 'Auth planned state should expose three files');
    assert_same(3, $database['planned_state']['auth_file_count'], 'Auth planned state should expose file count');
    assert_same(true, $database['planned_state']['authentication'], 'Authentication should be enabled in v0.019');
    assert_same(true, $database['planned_state']['authorization'], 'Authorization should be enabled in v0.019');
    assert_same('common_foundation_and_entrypoints', $database['planned_state']['core_root_policy'], 'Core root policy should allow common foundations and entrypoints');
    assert_same('single_file_principle', $database['planned_state']['event_log_policy'], 'Event Log should use the single file principle');
    assert_same('Core/EventLog.php', $database['planned_state']['event_log_file'], 'Event Log should live in Core/EventLog.php');
    assert_same('prohibited', $database['planned_state']['event_log_folder'], 'Event Log folder should be prohibited');
    assert_same(true, $database['planned_state']['event_log_common_foundation'], 'Event Log should be a common foundation');
    assert_same(true, $database['planned_state']['event_log_single_file'], 'Event Log should be single file');
    assert_same(false, $database['planned_state']['event_log_entrypoint'], 'Event Log should not be an entrypoint');
    assert_same(['realtime_database', 'authentication', 'authorization'], $database['planned_state']['event_log_shared_by'], 'Event Log should be shared by database, authentication, and authorization');
    assert_same(false, $database['planned_state']['event_log_message_broker'], 'Event Log should not be a message broker');
    assert_same(false, $database['planned_state']['event_log_remote_sync'], 'Event Log should not be remote sync');
    assert_same(false, $database['planned_state']['event_log_automatic_repair'], 'Event Log should not auto repair');
    assert_same(false, $database['planned_state']['event_log_automatic_compaction'], 'Event Log should not auto compact');
    assert_same(false, $database['planned_state']['event_log_automatic_delete'], 'Event Log should not auto delete');
    assert_same(true, $database['planned_state']['database_three_file_split'], 'Database should be split into three files');
    assert_same(['DatabaseCore.php', 'DatabaseStorage.php', 'DatabaseOperations.php'], $database['planned_state']['database_files'], 'Database planned state should expose three files');
    assert_same(3, $database['planned_state']['database_file_count'], 'Database planned state should expose file count');
    assert_same('completely_blank', $database['planned_state']['deployment_system'], 'Deployment System should be completely blank');
    assert_same(true, $database['planned_state']['event_envelope'], 'Event Log should support event envelope');
    assert_same(true, $database['planned_state']['event_domain_source'], 'Event Log should support domain source');
    assert_same(true, $database['planned_state']['event_metadata'], 'Event Log should support metadata');
    assert_same(true, $database['planned_state']['event_type_registry'], 'Event Log should support type registry');
    assert_same(true, $database['planned_state']['event_chain_hash'], 'Event Log should support chain hash');
    assert_same(true, $database['planned_state']['event_validation'], 'Event Log should support validation');
    assert_same(true, $database['planned_state']['event_replay_scope'], 'Event Log should support replay scope');
    assert_same(true, $database['planned_state']['event_evidence'], 'Event Log should support evidence');
    assert_same(true, $database['planned_state']['event_snapshot_link'], 'Event Log should support snapshot link');
    assert_same(true, $database['planned_state']['event_replay_verification'], 'Event Log should support replay verification');
    assert_same(true, $database['planned_state']['event_cursor_contract'], 'Event Log should support cursor contract');
    assert_same(true, $database['planned_state']['event_import_validation'], 'Event Log should support import validation');
    assert_same(true, $database['planned_state']['event_export_packet'], 'Event Log should support export packet');
    assert_same(true, $database['planned_state']['event_retention_view'], 'Event Log should support retention view');
    assert_same(true, $database['planned_state']['event_risk_report'], 'Event Log should support risk report');
    assert_same(true, $database['planned_state']['event_operation_journal'], 'Event Log should support operation journal');
    assert_same(true, $database['planned_state']['event_health_summary'], 'Event Log should support health summary');
    assert_same(true, $database['planned_state']['event_recovery_evidence'], 'Event Log should support recovery evidence');
    assert_same(true, $database['planned_state']['event_operational_guard'], 'Event Log should support operational guard');
    assert_same(true, $database['planned_state']['event_trust_score'], 'Event Log should support trust score');
    assert_same(true, $database['planned_state']['event_restore_readiness'], 'Event Log should support restore readiness');
    assert_same(true, $database['planned_state']['event_audit_packet'], 'Event Log should support audit packet');
    assert_same(true, $database['planned_state']['event_incident_packet'], 'Event Log should support incident packet');
    assert_same(true, $database['planned_state']['event_degradation_report'], 'Event Log should support degradation report');
    assert_same(true, $database['planned_state']['event_write_safety_gate'], 'Event Log should support write safety gate');
    assert_same(true, $database['planned_state']['event_replay_window'], 'Event Log should support replay window');
    assert_same(true, $database['planned_state']['event_cursor_drift_report'], 'Event Log should support cursor drift report');
    assert_same(true, $database['planned_state']['event_export_integrity'], 'Event Log should support export integrity');
    assert_same(true, $database['planned_state']['event_restore_impact'], 'Event Log should support restore impact');
    assert_same(true, $database['planned_state']['event_retention_decision_view'], 'Event Log should support retention decision view');
    assert_same(true, $database['planned_state']['event_operational_slo'], 'Event Log should support operational SLO');
    assert_same(true, $database['planned_state']['event_handoff_summary'], 'Event Log should support handoff summary');
    assert_same(true, $database['planned_state']['event_preflight_report'], 'Event Log should support preflight report');
    assert_same(true, $database['planned_state']['event_chain_snapshot'], 'Event Log should support chain snapshot');
    assert_same(true, $database['planned_state']['event_continuity_proof'], 'Event Log should support continuity proof');
    assert_same(true, $database['planned_state']['event_payload_integrity_report'], 'Event Log should support payload integrity report');
    assert_same(true, $database['planned_state']['event_domain_isolation_report'], 'Event Log should support domain isolation report');
    assert_same(true, $database['planned_state']['event_recovery_route'], 'Event Log should support recovery route');
    assert_same(true, $database['planned_state']['event_manual_review_queue'], 'Event Log should support manual review queue');
    assert_same(true, $database['planned_state']['event_operational_timeline'], 'Event Log should support operational timeline');
    assert_same(true, $database['planned_state']['event_evidence_seal'], 'Event Log should support evidence seal');
    assert_same(true, $database['planned_state']['event_trust_ledger'], 'Event Log should support trust ledger');
    assert_same('sqlite_persistent', $database['planned_state']['runtime_execution'], 'database runtime should be SQLite persistent in v0.019');
    assert_same('in_memory', $database['planned_state']['fallback_runtime'], 'database should keep in-memory fallback');
    assert_same(true, $database['planned_state']['sqlite_persistence'], 'database should support SQLite persistence');
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
    assert_same(false, $database['planned_state']['libsql_runtime'], 'database should not implement libSQL runtime in v0.019');
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
    assert_same('realtime_database', $events[0]['domain'], 'event envelope should include domain');
    assert_same('realtime_database', $events[0]['source'], 'event envelope should include source');
    assert_same(1, $events[0]['envelope_version'], 'event envelope should include version');
    assert_same('system', $events[0]['metadata']['actor'], 'event metadata should include actor');
    assert_same('root', $events[0]['previous_hash'], 'first event should link to root hash');
    assert_true(is_string($events[0]['event_hash']) && $events[0]['event_hash'] !== '', 'event should include event hash');
    assert_same($events[0]['event_hash'], $events[1]['previous_hash'], 'second event should link to previous event hash');
    assert_same(['realtime_database', 'authentication', 'authorization'], AdlaireDatabase::eventTypeRegistry()['domains'], 'event type registry should expose domains');
    assert_same(true, in_array('update', AdlaireDatabase::eventTypeRegistry()['types'], true), 'event type registry should expose update type');
    assert_same(true, AdlaireDatabase::eventCursorContract()['valid'], 'event cursor contract should be valid');
    assert_same($events[1]['id'], AdlaireDatabase::eventCursorContract()['event_id'], 'event cursor contract should expose latest event id');
    assert_same(2, AdlaireDatabase::eventReplayScope('realtime_database', 'system')['count'], 'event replay scope should filter by domain and collection');
    assert_same(true, AdlaireDatabase::eventEvidence()['valid'], 'event evidence should be valid');
    assert_same(2, AdlaireDatabase::eventEvidence()['latest_sequence'], 'event evidence should expose latest sequence');
    assert_same(true, AdlaireDatabase::eventImportValidation($events)['valid'], 'event import validation should pass for current events');
    assert_same('event_log_export_packet', AdlaireDatabase::eventExportPacket()['kind'], 'event export packet should expose kind');
    assert_same(false, AdlaireDatabase::eventRetentionView()['automatic_delete'], 'event retention view should not auto delete');
    assert_same('clear', AdlaireDatabase::eventRiskReport()['status'], 'event risk report should be clear');
    assert_same(false, AdlaireDatabase::eventOperationJournal('validation')['will_mutate_event_log'], 'event operation journal should not mutate event log');
    assert_same(true, AdlaireDatabase::eventSnapshotLink($events[1]['id'], $snapshot['fingerprint'])['linked'], 'event snapshot link should link event and snapshot');
    assert_same(true, AdlaireDatabase::eventReplayVerification('system')['verified'], 'event replay verification should verify snapshot and replay');
    assert_same(true, AdlaireDatabase::eventHealthSummary()['ready'], 'event health summary should be ready');
    assert_same('ready', AdlaireDatabase::eventRestoreReadiness()['status'], 'event restore readiness should be ready');
    assert_same('normal', AdlaireDatabase::eventOperationalGuard()['status'], 'event operational guard should be normal');
    assert_same(100, AdlaireDatabase::eventTrustScore()['score'], 'event trust score should be perfect');
    assert_true(isset(AdlaireDatabase::eventAuditPacket()['export_fingerprint']), 'event audit packet should include export fingerprint');
    assert_same('normal', AdlaireDatabase::eventIncidentPacket()['degradation']['status'], 'event incident packet should include degradation status');
    assert_same('normal', AdlaireDatabase::eventDegradationReport()['status'], 'event degradation report should be normal');
    assert_same(true, AdlaireDatabase::eventWriteSafetyGate('create')['allowed'], 'event write safety gate should allow known event type');
    assert_same(2, AdlaireDatabase::eventReplayWindow(1, 2)['event_count'], 'event replay window should count selected events');
    assert_same(false, AdlaireDatabase::eventCursorDriftReport($events[1]['id'])['drift'], 'event cursor drift report should not drift for latest cursor');
    assert_same(true, AdlaireDatabase::eventExportIntegrity()['valid'], 'event export integrity should validate current packet');
    assert_same(0, AdlaireDatabase::eventRestoreImpact($events)['events_added'], 'event restore impact should not add current events');
    assert_same(true, AdlaireDatabase::eventRetentionDecisionView()['keep_all'], 'event retention decision should keep all events');
    assert_same(true, AdlaireDatabase::eventOperationalSlo()['met'], 'event operational SLO should be met');
    assert_same('continue_observation', AdlaireDatabase::eventHandoffSummary()['recommended_action'], 'event handoff summary should continue observation');
    assert_same(true, AdlaireDatabase::eventPreflightReport('create')['allowed'], 'event preflight should allow known event type');
    assert_same(2, AdlaireDatabase::eventChainSnapshot()['event_count'], 'event chain snapshot should include event count');
    assert_same(true, AdlaireDatabase::eventContinuityProof()['proved'], 'event continuity proof should be proved');
    assert_same(true, AdlaireDatabase::eventPayloadIntegrityReport()['valid'], 'event payload integrity should be valid');
    assert_same(2, AdlaireDatabase::eventDomainIsolationReport()['realtime_database']['event_count'], 'event domain isolation should count realtime database events');
    assert_same('replay', AdlaireDatabase::eventRecoveryRoute()['route'], 'event recovery route should prefer replay');
    assert_same(0, AdlaireDatabase::eventManualReviewQueue()['count'], 'event manual review queue should be empty');
    assert_same(2, AdlaireDatabase::eventOperationalTimeline()['count'], 'event operational timeline should count events');
    assert_same(true, AdlaireDatabase::eventEvidenceSeal()['verified'], 'event evidence seal should verify evidence');
    assert_same(100, AdlaireDatabase::eventTrustLedger()['trust_score']['score'], 'event trust ledger should include trust score');
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
    assert_same(2, $migration['schema_version'], 'migration plan should expose v0.019 schema version');
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
    assert_same('v0.019', $evidence['version'], 'operational evidence bundle should expose v0.019');
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
    assert_same('v0.019', AdlaireDatabase::operationalBaselineSnapshot($currentExport)['version'], 'operational baseline snapshot should expose v0.019');
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
    assert_same('v0.019', AdlaireDatabase::databaseStateDigest()['version'], 'database state digest should expose v0.019');
    assert_same('ready', AdlaireDatabase::writeReadinessCheck('tasks', ['title' => 'Ready', 'score' => 1])['status'], 'write readiness check should be ready');
    assert_same(false, AdlaireDatabase::restoreCandidateInspector($currentExport)['will_restore'], 'restore candidate inspector should not restore');
    assert_same(true, AdlaireDatabase::eventStreamIntegritySummary()['valid'], 'event stream integrity summary should be valid');
    assert_true(isset(AdlaireDatabase::operationalStatusBoard($currentExport)['state_digest']), 'operational status board should include state digest');
    assert_same('maintenance_not_required', AdlaireDatabase::maintenanceDecisionReport($currentExport)['decision'], 'maintenance decision should not require maintenance');
    assert_same(false, AdlaireDatabase::backupRotationView([$currentExport])['automatic_delete'], 'backup rotation view should not delete automatically');
    assert_same(false, AdlaireDatabase::dataMutationRiskReport('restore_database', null, $currentExport)['will_mutate'], 'data mutation risk should be dry-run');
    assert_same('safe', AdlaireDatabase::readModelRebuildSafetyCheck('tasks')['status'], 'read model rebuild safety check should be safe');
    assert_same(false, AdlaireDatabase::incidentRecoveryPacket($currentExport)['automatic_restore'], 'incident recovery packet should not restore automatically');
    assert_true(AdlaireDatabase::operationJournal()['count'] > 0, 'operation journal should expose write intents');
    assert_same(100, AdlaireDatabase::recoveryConfidenceScore($currentExport)['score'], 'recovery confidence score should be high for current export');
    assert_same(false, AdlaireDatabase::schemaDriftGuard()['drift'], 'schema drift guard should be clear');
    assert_same(true, AdlaireDatabase::eventReplayProof('tasks')['proved'], 'event replay proof should prove current snapshot');
    assert_same(true, AdlaireDatabase::backupTrustLedger([$currentExport])['entries'][0]['trusted'], 'backup trust ledger should trust current export');
    assert_same(false, AdlaireDatabase::operationalFreezeReason()['frozen'], 'operational freeze reason should be clear');
    assert_same('ready', AdlaireDatabase::criticalPathCheck($currentExport)['status'], 'critical path check should be ready');
    assert_same('covered', AdlaireDatabase::dataLossExposureReport($currentExport)['status'], 'data loss exposure should be covered');
    assert_same('met:continue_observation', AdlaireDatabase::operatorHandoffNote($currentExport)['brief'], 'operator handoff note should be concise');
    assert_same(true, AdlaireDatabase::writeContractValidator('tasks', 'create', ['title' => 'Contract', 'score' => 1])['valid'], 'write contract validator should allow valid writes');
    assert_true(AdlaireDatabase::eventCausalityChain()['count'] > 0, 'event causality chain should expose events');
    assert_true(is_string(AdlaireDatabase::snapshotRecoveryPoint('tasks')['seal']), 'snapshot recovery point should expose seal');
    assert_same(false, AdlaireDatabase::restoreConflictPreview($currentExport)['will_restore'], 'restore conflict preview should not restore');
    assert_same(true, AdlaireDatabase::readConsistencyWindow()['consistent'], 'read consistency window should be consistent');
    assert_same(true, AdlaireDatabase::backupCompletenessCheck($currentExport)['complete'], 'backup completeness check should pass current export');
    assert_same(true, AdlaireDatabase::operationalModeMatrix()['current']['read'], 'operational mode matrix should allow reads');
    assert_same(false, AdlaireDatabase::criticalOperationApprovalToken('delete', 'tasks')['external_auth'], 'critical operation approval token should not use external auth');
    assert_same(false, AdlaireDatabase::dataRetentionPolicyView()['runtime_enforced'], 'data retention policy view should not enforce runtime deletion');
    assert_same(false, AdlaireDatabase::eventGapRepairPlan()['automatic_repair'], 'event gap repair plan should not repair automatically');
    assert_same(true, AdlaireDatabase::schemaCompatibilityMatrix($currentExport)['compatible'], 'schema compatibility matrix should accept current export');
    assert_same(false, AdlaireDatabase::recoveryTimelineSimulator($currentExport)['will_restore'], 'recovery timeline simulator should not restore');
    assert_same(false, AdlaireDatabase::incidentContainmentView($currentExport)['automatic_freeze'], 'incident containment view should not freeze automatically');
    assert_same(true, AdlaireDatabase::productionReadinessLedger($currentExport)['ready'], 'production readiness ledger should be ready');
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
    assert_same('v0.019', AdlaireDatabase::operationalReport()['version'], 'operational report should expose v0.019');
    assert_same('v0.019', AdlaireDatabase::operationalIncidentReport()['version'], 'operational incident report should expose v0.019');
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

function test_auth_capabilities(): void
{
    AdlaireAuth::reset();
    $readiness = AdlaireAuth::readiness();
    assert_same(true, $readiness['ready'], 'Auth readiness should pass');
    assert_same('v0.021', $readiness['planned_state']['version'], 'Auth version should be v0.021');
    assert_same('Core/Auth.php', $readiness['planned_state']['core_entrypoint'], 'Auth entrypoint should be Core/Auth.php');
    assert_same(3, $readiness['planned_state']['auth_file_count'], 'Auth should keep exactly three internal PHP files');
    assert_same(true, $readiness['planned_state']['authentication'], 'Authentication should be planned');
    assert_same(true, $readiness['planned_state']['authorization'], 'Authorization should be planned');
    assert_same(false, $readiness['planned_state']['external_dependency'], 'Auth should not add external dependencies');
    assert_same(false, $readiness['planned_state']['runtime'], 'Auth should not depend on Runtime');
    assert_same('prohibited', $readiness['planned_state']['runtime_replacement_category'], 'Auth should prohibit Runtime replacement categories');
    assert_same(false, $readiness['planned_state']['plain_password'], 'Auth should not store plain passwords');
    assert_same('deny', $readiness['planned_state']['undefined_policy'], 'Undefined authorization policy should deny');
    assert_same(true, $readiness['planned_state']['auth_change_impact_report'], 'Auth should expose change impact report');
    assert_same(true, $readiness['planned_state']['policy_simulation'], 'Auth should expose policy simulation');
    assert_same(true, $readiness['planned_state']['authorization_regression_guard'], 'Auth should expose authorization regression guard');
    assert_same(true, $readiness['planned_state']['auth_control_summary'], 'Auth should expose control summary');

    $user = AdlaireAuth::createUser();
    $credential = AdlaireAuth::registerCredential($user['id'], 'correct-horse-secret');
    assert_same(false, array_key_exists('secret_hash', $credential), 'Credential response should not expose secret hash');
    assert_same(true, $credential['secret_hash_stored'], 'Credential response should report stored hash without exposing it');

    $login = AdlaireAuth::login($credential['id'], 'correct-horse-secret');
    assert_same(true, $login['authenticated'], 'Login should authenticate valid credentials');
    $session = $login['session'];
    assert_same(true, AdlaireAuth::validateSession($session['id'])['valid'], 'Session validation should pass');

    $role = AdlaireAuth::createRole('operator');
    $permission = AdlaireAuth::createPermission('collection:tasks', 'read');
    $unusedPermission = AdlaireAuth::createPermission('collection:tasks', 'archive');
    $policy = AdlaireAuth::assignPolicy($user['id'], $role['id'], $permission['id']);
    $allow = AdlaireAuth::accessDecision($session['id'], 'collection:tasks', 'read');
    assert_same('allow', $allow['decision'], 'Matching policy should allow access');
    assert_same('matched_policy', $allow['reason'], 'Allowed decision should expose matched policy reason');

    $deny = AdlaireAuth::accessDecision($session['id'], 'collection:tasks', 'delete');
    assert_same('deny', $deny['decision'], 'Missing policy should deny access');
    assert_same('no_policy', $deny['reason'], 'Denied decision should expose no policy reason');
    assert_true(in_array($deny['reason'], AdlaireAuth::denyReasonRegistry(), true), 'Deny reason should be registered');
    assert_same(false, AdlaireAuth::login($credential['id'], 'wrong-secret-value')['authenticated'], 'Invalid credential login should fail');
    $unvalidatedSession = AdlaireAuth::issueSession($user['id']);
    $dormantUser = AdlaireAuth::createUser();

    assert_same(1, AdlaireAuth::leastPrivilegeReport($user['id'])['permission_count'], 'Least privilege report should count active policies');
    assert_same(false, AdlaireAuth::policyConflictReport()['conflict'], 'Policy conflict report should be clear');
    assert_same(false, AdlaireAuth::policyDriftReport()['drift'], 'Policy drift report should be clear');
    assert_same([$user['id']], AdlaireAuth::policyBlastRadius($policy['id'])['subjects_affected'], 'Policy blast radius should expose affected subject');
    assert_same(0, AdlaireAuth::sessionAnomalyReport()['count'], 'Session anomaly report should be clear');
    assert_same(true, AdlaireAuth::policyIntegrityReport()['valid'], 'Policy integrity should pass');
    assert_same(true, AdlaireAuth::sessionIntegrityReport()['valid'], 'Session integrity should pass');
    assert_same(true, AdlaireAuth::authAuditPacket()['policy_integrity']['valid'], 'Auth audit packet should include policy integrity');
    assert_same(true, AdlaireAuth::authEvidenceSeal()['verified'], 'Auth evidence seal should verify evidence');
    assert_true(AdlaireAuth::authTrustLedger()['credential_trust']['score'] > 0, 'Auth trust ledger should expose credential trust score');
    assert_same(false, AdlaireAuth::authIncidentContainment()['automatic_recovery'], 'Auth incident containment should not auto recover');
    assert_same(false, AdlaireAuth::authWriteSafetyGate('policy_assign')['automatic_block'], 'Auth write safety gate should not auto block');
    assert_same(false, AdlaireAuth::authEmergencyFreezeView()['automatic_freeze'], 'Auth emergency freeze view should not auto freeze');
    assert_true(in_array(AdlaireAuth::authDegradedModeView()['mode'], ['normal', 'degraded'], true), 'Auth degraded mode view should expose a mode');
    assert_same([$user['id']], AdlaireAuth::authChangeImpactReport('policy_change', ['policy_id' => $policy['id']])['affected_subjects'], 'Auth change impact should expose affected subject');
    assert_same('allow', AdlaireAuth::policySimulation($user['id'], 'collection:tasks', 'read')['decision'], 'Policy simulation should allow existing policy');
    assert_same(false, AdlaireAuth::policySimulation($user['id'], 'collection:tasks', 'read')['will_mutate'], 'Policy simulation should not mutate');
    assert_same($user['id'], AdlaireAuth::sessionRevocationImpact($session['id'])['user_id'], 'Session revocation impact should expose user');
    assert_same($user['id'], AdlaireAuth::credentialRevocationImpact($credential['id'])['user_id'], 'Credential revocation impact should expose user');
    assert_true(AdlaireAuth::permissionCoverageReport()['count'] >= 2, 'Permission coverage should include permissions');
    assert_same(1, AdlaireAuth::unusedPermissionReport()['count'], 'Unused permission report should find unused permission');
    assert_same(1, AdlaireAuth::dormantUserReport()['count'], 'Dormant user report should find a user without auth activity');
    assert_same($unvalidatedSession['id'], AdlaireAuth::staleSessionReport()['items'][0]['id'], 'Stale session report should find unvalidated session');
    assert_same(1, AdlaireAuth::failedLoginTrend()['count'], 'Failed login trend should count failed login');
    $baseline = AdlaireAuth::accessPatternBaseline();
    assert_same(false, AdlaireAuth::accessPatternDriftReport($baseline)['drift'], 'Access pattern drift should be clear against current baseline');
    assert_same(1, AdlaireAuth::roleSaturationReport()['count'], 'Role saturation report should include role');
    assert_same(false, AdlaireAuth::policyExpiryPlan()['automatic_expiry'], 'Policy expiry plan should not expire automatically');
    assert_same(false, AdlaireAuth::emergencyAccessReview()['automatic_privilege_escalation'], 'Emergency access review should not escalate privileges automatically');
    $export = AdlaireAuth::authEvidenceExport();
    assert_same(true, AdlaireAuth::authEvidenceImportValidation($export)['valid'], 'Auth evidence export should validate before import');
    assert_same(false, AdlaireAuth::authStateCompare($export, $export)['changed'], 'Auth state compare should be clear for identical payloads');
    assert_same(true, AdlaireAuth::authorizationRegressionGuard($baseline)['passed'], 'Authorization regression guard should pass current baseline');
    assert_true(AdlaireAuth::authOperationsLedger()['event_count'] > 0, 'Auth operations ledger should expose events');
    assert_same(false, AdlaireAuth::authControlSummary()['automatic_recovery'], 'Auth control summary should not auto recover');

    $events = AdlaireAuth::authEvents();
    assert_true(count($events) >= 9, 'Auth should record authentication and authorization events');
    assert_same(true, in_array('authentication', array_column($events, 'domain'), true), 'Auth events should include authentication domain');
    assert_same(true, in_array('authorization', array_column($events, 'domain'), true), 'Auth events should include authorization domain');
    assert_same(true, in_array('login_success', array_column($events, 'type'), true), 'Auth events should include login_success');
    assert_same(true, in_array('access_allow', array_column($events, 'type'), true), 'Auth events should include access_allow');
    assert_same(true, in_array('access_deny', array_column($events, 'type'), true), 'Auth events should include access_deny');
    assert_true(is_string(AdlaireAuth::authEvidence()['fingerprint']), 'Auth evidence should expose fingerprint');
    $registryTypes = AdlaireEventLog::typeRegistry()['types'];
    assert_true(in_array('auth_change_impact_report', $registryTypes, true), 'Event Log should register auth change impact type');
    assert_true(in_array('authorization_regression_guard', $registryTypes, true), 'Event Log should register authorization regression guard type');
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
    assert_same('v0.019', AdlaireDatabase::plannedState()['version'], 'database version should be v0.019 without deployment dependency');
    assert_same(false, method_exists('AdlaireDatabase', 'release'), 'Realtime Database should not provide a deployment release gate');
}

function test_documents(): void
{
    $spec = file_get_contents(__DIR__ . '/../docs/ADLAIRE-ECOSYSTEM.md');
    $readme = file_get_contents(__DIR__ . '/../docs/README.md');
    $agents = file_get_contents(__DIR__ . '/../docs/AGENTS.md');
    $testingDoc = file_get_contents(__DIR__ . '/../docs/testing.md');
    $versionPlan = file_get_contents(__DIR__ . '/../docs/version-plan.md');

    assert_true(is_string($spec) && str_contains($spec, 'v0.019'), 'spec should describe v0.019');
    assert_true(is_string($spec) && str_contains($spec, 'v0.021'), 'spec should describe v0.021');
    assert_true(is_string($spec) && str_contains($spec, 'Selected database | SQLite'), 'spec should select SQLite');
    assert_true(is_string($spec) && str_contains($spec, 'libSQLはSQLite互換の将来拡張として決定済み'), 'spec should define libSQL as decided future SQLite-compatible extension');
    assert_true(is_string($spec) && str_contains($spec, 'Realtime Database BaaS Contract'), 'spec should define the realtime database BaaS contract');
    assert_true(is_string($spec) && str_contains($spec, 'Event Log Overview'), 'spec should define the event log overview');
    assert_true(is_string($spec) && str_contains($spec, 'Realtime Databaseの変更履歴を追記型で保持する内部履歴基盤'), 'spec should describe event log as append-only internal history');
    assert_true(is_string($spec) && str_contains($spec, 'Snapshot、Cursor、Replay、Export/Restore'), 'spec should connect event log with snapshot cursor replay export restore');
    assert_true(is_string($spec) && str_contains($spec, '外部同期や外部message brokerの代替ではなく'), 'spec should reject external sync and message broker framing');
    assert_true(is_string($spec) && str_contains($spec, 'Event Logを`Core/EventLog.php`の単一ファイルとして独立'), 'spec should define Event Log as single file');
    assert_true(is_string($spec) && str_contains($spec, 'Realtime Database、Authentication、Authorizationに共通するCore横断履歴基盤'), 'spec should define Event Log as Core common foundation');
    assert_true(is_string($spec) && str_contains($spec, 'Runtimeを廃止'), 'spec should define Runtime removal');
    assert_true(is_string($spec) && str_contains($spec, 'Authentication / Authorization'), 'spec should define Auth features');
    assert_true(is_string($spec) && str_contains($spec, 'Auth Change Impact Report'), 'spec should define v0.021 Auth change impact');
    assert_true(is_string($spec) && str_contains($spec, 'Authorization Regression Guard'), 'spec should define v0.021 Auth regression guard');
    assert_true(is_string($spec) && str_contains($spec, 'Realtime Databaseを3ファイルへ分割'), 'spec should define v0.019 database split');
    assert_true(is_string($spec) && str_contains($spec, 'Event Envelope'), 'spec should describe Event Envelope');
    assert_true(is_string($spec) && str_contains($spec, 'Event Chain Hash'), 'spec should describe Event Chain Hash');
    assert_true(is_string($spec) && str_contains($spec, 'Event Import Validation'), 'spec should describe Event Import Validation');
    assert_true(is_string($spec) && str_contains($spec, 'Event Trust Ledger'), 'spec should describe Event Trust Ledger');
    assert_true(is_string($spec) && str_contains($spec, 'DatabaseStorage.php'), 'spec should describe DatabaseStorage.php');
    assert_true(is_string($spec) && str_contains($spec, 'Collection Schema'), 'spec should define collection schema');
    assert_true(is_string($spec) && str_contains($spec, 'Snapshot Export'), 'spec should define snapshot export');
    assert_true(is_string($spec) && str_contains($spec, 'Migration planned state'), 'spec should define migration planned state');
    assert_true(is_string($spec) && str_contains($spec, 'Realtime Adapter Boundary'), 'spec should define realtime adapter boundary');
    assert_true(is_string($spec) && str_contains($spec, 'SQLite Persistence'), 'spec should define SQLite persistence');
    assert_true(is_string($spec) && str_contains($spec, 'Operational Health'), 'spec should define operational health');
    assert_true(is_string($spec) && str_contains($spec, 'Integrity audit'), 'spec should define integrity audit');
    assert_true(is_string($spec) && str_contains($spec, 'Query explain'), 'spec should define query explain');
    assert_true(is_string($spec) && str_contains($spec, 'Import validation'), 'spec should define import validation');
    assert_true(is_string($readme) && str_contains($readme, 'Adlaire Ecosystem'), 'README should name the project');
    assert_true(is_string($readme) && str_contains($readme, 'Adlaireグループの内部システム基盤'), 'README should describe the Adlaire group internal foundation');
    assert_true(is_string($readme) && str_contains($readme, 'BaaS基盤プロジェクト'), 'README should briefly describe the BaaS project');
    assert_true(is_string($readme) && str_contains($readme, 'SQLiteとEvent Log'), 'README should mention SQLite and Event Log');
    assert_true(is_string($readme) && str_contains($readme, 'Realtime Databaseの変更履歴を追記型で保持する内部履歴基盤'), 'README should describe Event Log');
    assert_true(is_string($readme) && str_contains($readme, 'Snapshot、Cursor、Replay、Export/Restore'), 'README should connect Event Log with related database concepts');
    assert_true(is_string($readme) && str_contains($readme, '外部依存を抑えた'), 'README should describe reduced external dependency');
    assert_true(is_string($readme) && !str_contains($readme, 'ドキュメント役割'), 'README should not carry document role details');
    assert_true(is_string($agents) && str_contains($agents, '## ドキュメント役割'), 'AGENTS should define document roles');
    assert_true(is_string($agents) && str_contains($agents, '| File | Role | 禁止 |'), 'AGENTS should define document role constraints');
    assert_true(is_string($agents) && str_contains($agents, '外部向けの簡潔なプロジェクト説明'), 'AGENTS should state that README is external-facing');
    assert_true(is_string($agents) && str_contains($agents, '内部入口、詳細仕様、作業ルール'), 'AGENTS should prohibit internal README roles');
    assert_true(is_string($agents) && str_contains($agents, '仕様確定案'), 'AGENTS should define the development order');
    assert_true(is_string($agents) && str_contains($agents, '作業エージェントの最高準拠ドキュメント'), 'AGENTS should be the work-agent top-level compliance document');
    assert_true(is_string($agents) && str_contains($agents, '最高絶対原則'), 'AGENTS should define the absolute top-level principle');
    assert_true(is_string($agents) && str_contains($agents, '最高準拠ドキュメントを読まない時点で、実行プロセスは強制停止する'), 'AGENTS should stop execution when top-level documents are not read');
    assert_true(is_string($agents) && str_contains($agents, '最高準拠ドキュメント確認'), 'AGENTS should make top-level document confirmation the first required step');
    assert_true(is_string($agents) && str_contains($agents, '承認前の仕様確定、バージョン計画記載、実装、修正、削除、追加、リリース判定は禁止'), 'AGENTS should prohibit pre-approval changes');
    assert_true(is_string($agents) && str_contains($agents, 'テスト関係は`docs/testing.md`に集約する'), 'AGENTS should delegate test details to testing docs');
    assert_true(is_string($testingDoc) && str_contains($testingDoc, 'php tests/debug.php'), 'testing doc should describe the official test entrypoint');
    assert_true(is_string($testingDoc) && str_contains($testingDoc, 'Adlaire Ecosystemにおけるテスト関係の集約先'), 'testing doc should define itself as the test aggregation document');
    assert_true(is_string($testingDoc) && str_contains($testingDoc, 'v0.019 Test Scope'), 'testing doc should describe v0.019 test scope');
    assert_true(is_string($testingDoc) && str_contains($testingDoc, 'Authentication / Authorization'), 'testing doc should categorize Auth tests');
    assert_true(is_string($testingDoc) && str_contains($testingDoc, 'v0.021のAuth実運用'), 'testing doc should cover v0.021 Auth operations tests');
    assert_true(is_string($testingDoc) && str_contains($testingDoc, 'Core/EventLog.php'), 'testing doc should cover Core/EventLog.php');
    assert_true(is_string($testingDoc) && str_contains($testingDoc, 'Core/Auth.php'), 'testing doc should cover Core/Auth.php');
    assert_true(is_string($testingDoc) && str_contains($testingDoc, 'Event LogのEnvelope'), 'testing doc should cover v0.019 Event Log improvements');
    assert_true(is_string($testingDoc) && str_contains($testingDoc, 'docker_environment_cli_php_tests_debug'), 'testing doc should define Docker CLI verification');
    assert_true(is_string($testingDoc) && str_contains($testingDoc, 'docker_production_like_environment'), 'testing doc should define future Docker production-like tests');
    assert_true(is_string($spec) && str_contains($spec, '必須動作要件はシステム動作要件の正本'), 'spec should define mandatory runtime requirements as source of truth');
    assert_true(is_string($spec) && str_contains($spec, '必須動作要件に基づく範囲内はすべて必須要件'), 'spec should define mandatory runtime scope');
    assert_true(is_string($spec) && str_contains($spec, '「必要だが必須ではない」という表現を禁止'), 'spec should prohibit ambiguous required wording');
    assert_true(is_string($spec) && str_contains($spec, '承認済み文言に厳格準拠'), 'spec should require strict approved wording compliance');
    assert_true(is_string($spec) && str_contains($spec, 'PHP: `8.3`推奨'), 'spec should define PHP 8.3 as recommended');
    assert_true(is_string($spec) && str_contains($spec, '必須拡張: `json`, `PDO`, `pdo_sqlite`'), 'spec should define required extensions');
    assert_true(is_string($spec) && str_contains($spec, 'CLI: Docker環境、デプロイメント限定'), 'spec should limit CLI usage');
    assert_true(is_string($spec) && str_contains($spec, '開発におけるCLIは必須'), 'spec should require development CLI');
    assert_true(is_string($spec) && str_contains($spec, 'SQLite使用'), 'spec should require SQLite');
    assert_true(is_string($spec) && str_contains($spec, '外部依存禁止'), 'spec should prohibit external dependencies');
    assert_true(is_string($spec) && str_contains($spec, '仕様の最高準拠ドキュメント'), 'spec should define itself as the top-level specification document');
    assert_true(is_string($spec) && str_contains($spec, '作業エージェントの承認プロセス、作業ルール、編集制約は`docs/AGENTS.md`を正とする'), 'spec should delegate work-agent rules to AGENTS');
    assert_true(is_string($spec) && str_contains($spec, 'テスト関係は`docs/testing.md`へ集約する'), 'spec should delegate test details to testing docs');
    assert_true(is_string($spec) && str_contains($spec, 'docker_test_mode: future_production_like_environment'), 'spec should define future Docker test mode');
    assert_true(is_string($spec) && str_contains($spec, 'docs/testing.md'), 'spec should assign testing documents to docs/testing.md');
    assert_true(is_string($spec) && str_contains($spec, 'docs/version-plan.md'), 'spec should assign version plan documents to docs/version-plan.md');
    assert_true(is_string($spec) && str_contains($spec, 'すべてのドキュメントは`docs/`へ集約する'), 'spec should centralize all documents under docs');
    assert_true(is_string($versionPlan) && str_contains($versionPlan, 'version: v0.021'), 'version plan should describe v0.021');
    assert_true(is_string($versionPlan) && str_contains($versionPlan, 'scope: auth_operations_resilience_hardening'), 'version plan should describe v0.021 scope');
    assert_true(is_string($versionPlan) && str_contains($versionPlan, 'auth_file_count_3'), 'version plan should keep Auth file count constraint');
    assert_true(is_string($versionPlan) && str_contains($versionPlan, 'version: v0.020'), 'version plan should describe v0.020');
    assert_true(is_string($versionPlan) && str_contains($versionPlan, 'scope: documentation_governance_cleanup'), 'version plan should describe v0.020 scope');
    assert_true(is_string($versionPlan) && str_contains($versionPlan, 'no_source_code_change'), 'version plan should keep source code out of v0.020');
    assert_true(is_string($versionPlan) && str_contains($versionPlan, 'version: v0.019'), 'version plan should describe v0.019');
    assert_true(is_string($versionPlan) && str_contains($versionPlan, 'scope: runtime_removal_auth_authorization_core_feature'), 'version plan should describe v0.019 scope');
    assert_true(is_string($versionPlan) && str_contains($versionPlan, 'database_file_count: 3'), 'version plan should describe database file count');
    assert_true(is_string($versionPlan) && str_contains($versionPlan, 'event_log_file: Core/EventLog.php'), 'version plan should describe Core/EventLog.php');
    assert_true(is_string($versionPlan) && str_contains($versionPlan, 'implementation: approved'), 'version plan should approve v0.019 implementation');
    assert_true(is_string($versionPlan) && str_contains($versionPlan, 'absolute_principle: 必要だが必須ではないという表現を禁止'), 'version plan should record the absolute principle');
    assert_true(is_string($versionPlan) && str_contains($versionPlan, 'mandatory_runtime_scope: 必須動作要件に基づく範囲内はすべて必須要件'), 'version plan should record mandatory runtime scope');
    assert_true(is_string($versionPlan) && str_contains($versionPlan, 'required_extensions: json, PDO, pdo_sqlite'), 'version plan should describe required extensions');
    assert_true(is_string($versionPlan) && str_contains($versionPlan, 'development_cli: required'), 'version plan should require development CLI');
    assert_true(is_string($versionPlan) && str_contains($versionPlan, 'status: version_plan_approved'), 'version plan should be approved');
    assert_true(is_string($versionPlan) && str_contains($versionPlan, 'implementation: approved'), 'version plan should approve implementation');
    assert_true(is_string($versionPlan) && str_contains($versionPlan, 'remote_sync: not_adopted'), 'version plan should reject remote sync');
    assert_true(is_string($versionPlan) && str_contains($versionPlan, 'implementation_status: implemented'), 'version plan should record implementation status');
    assert_true(is_string($versionPlan) && str_contains($versionPlan, '要点のみを簡潔に明記'), 'version plan should require concise entries');
}

$tests = [
    'directory_policy' => test_directory_policy(...),
    'mandatory_requirements' => test_mandatory_requirements(...),
    'deployment_blank_reset' => test_deployment_blank_reset(...),
    'core_capabilities' => test_core_capabilities(...),
    'auth_capabilities' => test_auth_capabilities(...),
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
