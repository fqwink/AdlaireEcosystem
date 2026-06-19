<?php

declare(strict_types=1);

require_once __DIR__ . '/../Core/Deployment/Deployment.php';

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
    assert_same(['Database', 'Deployment', 'Runtime'], files_in('Core'), 'Core should contain three boundary folders');
    assert_same(['Database/Database.php', 'Deployment/Deployment.php', 'Runtime/Runtime.php'], recursive_php_files('Core'), 'Core should contain v0.002 planned files under three folders');
    assert_true(count(recursive_php_files('Core')) >= 3 && count(recursive_php_files('Core')) <= 5, 'Core should keep three to five PHP files');
    assert_same(['.gitkeep'], files_in('Applications'), 'Applications should contain only the boundary marker');
    assert_same(['.gitkeep'], files_in('Docker'), 'Docker should contain only the boundary marker until Docker assets are added');
    assert_same(['ADLAIRE-ECOSYSTEM.md', 'AGENTS.md', 'README.md', 'project.md', 'testing.md'], files_in('docs'), 'docs should contain all documents');
    assert_same(['debug.php'], files_in('tests'), 'tests should contain only debug.php');

    assert_true(is_dir(__DIR__ . '/../Core'), 'Core directory should exist');
    assert_true(is_dir(__DIR__ . '/../Applications'), 'Applications directory should exist');
    assert_true(is_dir(__DIR__ . '/../Docker'), 'Docker directory should exist');
    assert_true(is_dir(__DIR__ . '/../docs'), 'docs directory should exist');
    assert_true(is_dir(__DIR__ . '/../tests'), 'tests directory should exist');
}

function test_deployment_readiness(): void
{
    $manifest = AdlaireDeployment::manifest();
    assert_same('Adlaire Ecosystem', $manifest['name'], 'project name should be inherited');
    assert_same('v0.002', $manifest['version'], 'version should be v0.002');
    assert_same('BaaS Project', $manifest['type'], 'project should be a BaaS Project');
    assert_same(true, $manifest['current_scope_only'], 'project should use the current v0.002 scope');
    assert_same(['deployment_system', 'realtime_database'], $manifest['core_scope'], 'v0.002 scope should be deployment system and realtime database');
    assert_same('integrated_into_deployment_system', $manifest['project_boundary'], 'project boundary should be integrated into deployment system');
    assert_true(in_array('Docker', $manifest['allowed_directories'], true), 'Docker directory should be allowed');
    assert_true(in_array('authentication', $manifest['undefined_scope'], true), 'authentication should be undefined');
    assert_true(in_array('authorization', $manifest['undefined_scope'], true), 'authorization should be undefined');

    $readiness = AdlaireDeployment::readiness();
    assert_same(true, $readiness['ready'], 'deployment readiness should pass');
    foreach ($readiness['checks'] as $name => $passed) {
        assert_same(true, $passed, "deployment readiness check should pass: {$name}");
    }
}

function test_core_capabilities(): void
{
    $deployment = AdlaireDeployment::readiness();
    assert_same(true, $deployment['ready'], 'deployment system blank state should be explicit');
    assert_same('blank', $deployment['state']['state'], 'deployment system should be blank');
    assert_same('none', $deployment['state']['execution'], 'deployment system should not execute in v0.002');
    assert_same(false, $deployment['state']['release_ready'], 'deployment system should not be release ready while blank');

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
    assert_same('sqlite_persistent', $database['planned_state']['runtime_execution'], 'database runtime should be SQLite persistent in v0.002');
    assert_same('in_memory', $database['planned_state']['fallback_runtime'], 'database should keep in-memory fallback');
    assert_same(true, $database['planned_state']['sqlite_persistence'], 'database should support SQLite persistence');
    assert_same(true, $database['planned_state']['wal_mode'], 'database should support WAL mode');
    assert_same(true, $database['planned_state']['integrity_check'], 'database should support integrity check');
    assert_same(true, $database['planned_state']['backup_restore'], 'database should support backup and restore');
    assert_same(true, $database['planned_state']['restore_validation'], 'database should validate restore payloads');
    assert_same(true, $database['planned_state']['operational_health'], 'database should expose operational health');
    assert_same(true, $database['planned_state']['collection_stream'], 'database should support collection stream tracking');
    assert_same(true, $database['planned_state']['record_lookup'], 'database should support record lookup');
    assert_same(true, $database['planned_state']['record_listing'], 'database should support record listing');
    assert_same(true, $database['planned_state']['schema'], 'database should support collection schema');
    assert_same(true, $database['planned_state']['record_metadata'], 'database should support record metadata');
    assert_same(true, $database['planned_state']['query'], 'database should support query');
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
    assert_same('undefined', $database['planned_state']['access_rules'], 'database access rules should remain undefined');
    assert_same('none', $database['planned_state']['realtime_adapter'], 'database realtime adapter should be none');
    assert_same('pull_cursor', $database['planned_state']['stream_mode'], 'database stream mode should be pull cursor');

    $gate = AdlaireDeployment::releaseGate();
    assert_same(false, $gate['ready'], 'deployment system release gate should not pass while blank');
    assert_same('deployment_system_policy_reset', $gate['blocking_reason'], 'deployment system should report policy reset as blocking reason');
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
    assert_same(false, $subscription['push'], 'subscription should not use push in v0.002');

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
    assert_same(['collections', 'records', 'events', 'schema_versions', 'database_meta'], $migration['tables'], 'migration plan should include SQLite tables');

    $databaseExport = AdlaireDatabase::exportDatabase();
    assert_same('sqlite', $databaseExport['selected_database'], 'database export should keep selected database');
    assert_true(isset($databaseExport['collections']['tasks']), 'database export should include collection definitions');

    $taskExport = AdlaireDatabase::exportSnapshot('tasks');
    AdlaireDatabase::defineCollection('restored_tasks', 'application', $taskDefinition['schema'], $taskDefinition['indexes'], 'hard');
    $restored = AdlaireDatabase::restoreSnapshot('restored_tasks', [
        'definition' => ['channel' => 'application', 'schema' => $taskDefinition['schema'], 'indexes' => $taskDefinition['indexes'], 'delete_mode' => 'hard'],
        'snapshot' => $taskExport['snapshot'],
    ]);
    assert_same(1, count($restored['records']), 'restore snapshot should restore records');

    $rebuilt = AdlaireDatabase::rebuildSnapshot('tasks');
    assert_same(1, count($rebuilt['records']), 'read model rebuild should replay visible records');
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
    $release = AdlaireDeployment::release();
    assert_same('v0.002', $release['version'], 'release version should be v0.002');
    assert_same(false, $release['release_ready'], 'release should not be ready while deployment system is blank');
    assert_same(false, $release['deployment_gate']['ready'], 'deployment release gate should not pass while blank');
    assert_same('blank', $release['deployment_gate']['state']['state'], 'deployment gate should expose blank state');
    assert_true(is_string($release['fingerprint']) && $release['fingerprint'] !== '', 'release fingerprint should be present');
}

function test_documents(): void
{
    $spec = file_get_contents(__DIR__ . '/../docs/ADLAIRE-ECOSYSTEM.md');
    $readme = file_get_contents(__DIR__ . '/../docs/README.md');
    $agents = file_get_contents(__DIR__ . '/../docs/AGENTS.md');
    $projectDoc = file_get_contents(__DIR__ . '/../docs/project.md');
    $testingDoc = file_get_contents(__DIR__ . '/../docs/testing.md');

    assert_true(is_string($spec) && str_contains($spec, 'v0.002'), 'spec should describe v0.002');
    assert_true(is_string($spec) && str_contains($spec, 'Selected database | SQLite'), 'spec should select SQLite');
    assert_true(is_string($spec) && str_contains($spec, 'libSQLは正選定しない'), 'spec should not select libSQL');
    assert_true(is_string($spec) && str_contains($spec, 'Realtime Database BaaS Contract'), 'spec should define the realtime database BaaS contract');
    assert_true(is_string($spec) && str_contains($spec, 'Collection Schema'), 'spec should define collection schema');
    assert_true(is_string($spec) && str_contains($spec, 'Snapshot Export'), 'spec should define snapshot export');
    assert_true(is_string($spec) && str_contains($spec, 'Migration planned state'), 'spec should define migration planned state');
    assert_true(is_string($spec) && str_contains($spec, 'Realtime Adapter Boundary'), 'spec should define realtime adapter boundary');
    assert_true(is_string($spec) && str_contains($spec, 'SQLite Persistence'), 'spec should define SQLite persistence');
    assert_true(is_string($spec) && str_contains($spec, 'Operational Health'), 'spec should define operational health');
    assert_true(is_string($readme) && str_contains($readme, 'BaaS Project'), 'README should describe the BaaS Project');
    assert_true(is_string($agents) && str_contains($agents, '仕様取りまとめ'), 'AGENTS should define the development order');
    assert_true(is_string($projectDoc) && str_contains($projectDoc, 'docs/ADLAIRE-ECOSYSTEM.md'), 'project doc should delegate details to the spec');
    assert_true(is_string($testingDoc) && str_contains($testingDoc, 'php tests/debug.php'), 'testing doc should describe the official test entrypoint');
    assert_true(is_string($testingDoc) && str_contains($testingDoc, 'v0.002 Test Scope'), 'testing doc should describe v0.002 test scope');
    assert_true(is_string($spec) && str_contains($spec, 'docs/testing.md'), 'spec should assign testing documents to docs/testing.md');
    assert_true(is_string($spec) && str_contains($spec, 'すべてのドキュメントは`docs/`へ集約する'), 'spec should centralize all documents under docs');
}

$tests = [
    'directory_policy' => test_directory_policy(...),
    'deployment_readiness' => test_deployment_readiness(...),
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
