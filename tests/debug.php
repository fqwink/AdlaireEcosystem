<?php

declare(strict_types=1);

require_once __DIR__ . '/../Core/Project.php';

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

function test_directory_policy(): void
{
    assert_same(['Database.php', 'Deployment.php', 'Project.php'], files_in('Core'), 'Core should contain v0.001 planned files');
    assert_same(['.gitkeep'], files_in('Applications'), 'Applications should contain only the boundary marker');
    assert_same(['project.md'], files_in('docs'), 'docs should contain only project.md');
    assert_same(['debug.php'], files_in('tests'), 'tests should contain only debug.php');

    assert_true(is_dir(__DIR__ . '/../Core'), 'Core directory should exist');
    assert_true(is_dir(__DIR__ . '/../Applications'), 'Applications directory should exist');
    assert_true(is_dir(__DIR__ . '/../docs'), 'docs directory should exist');
    assert_true(is_dir(__DIR__ . '/../tests'), 'tests directory should exist');
}

function test_project_readiness(): void
{
    $manifest = AdlaireProject::manifest();
    assert_same('Adlaire Ecosystem', $manifest['name'], 'project name should be inherited');
    assert_same('v0.001', $manifest['version'], 'version should restart at v0.001');
    assert_same('BaaS Project', $manifest['type'], 'project should be a BaaS Project');
    assert_same(true, $manifest['current_scope_only'], 'project should use the current v0.001 scope');
    assert_same(['deployment_system', 'realtime_database'], $manifest['core_scope'], 'v0.001 scope should be deployment system and realtime database');
    assert_true(in_array('authentication', $manifest['undefined_scope'], true), 'authentication should be undefined');
    assert_true(in_array('authorization', $manifest['undefined_scope'], true), 'authorization should be undefined');

    $readiness = AdlaireProject::readiness();
    assert_same(true, $readiness['ready'], 'project readiness should pass');
    foreach ($readiness['checks'] as $name => $passed) {
        assert_same(true, $passed, "project readiness check should pass: {$name}");
    }
}

function test_core_capabilities(): void
{
    $deployment = AdlaireDeployment::readiness();
    assert_same(true, $deployment['ready'], 'deployment system blank state should be explicit');
    assert_same('blank', $deployment['state']['state'], 'deployment system should be blank');
    assert_same('none', $deployment['state']['execution'], 'deployment system should not execute in v0.001');
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
    assert_same('in_memory', $database['planned_state']['runtime_execution'], 'database runtime should be in-memory in v0.001');
    assert_same(true, $database['planned_state']['collection_stream'], 'database should support collection stream tracking');
    assert_same(true, $database['planned_state']['record_lookup'], 'database should support record lookup');
    assert_same(true, $database['planned_state']['record_listing'], 'database should support record listing');
    assert_same(true, $database['planned_state']['schema'], 'database should support collection schema');
    assert_same(true, $database['planned_state']['record_metadata'], 'database should support record metadata');
    assert_same(true, $database['planned_state']['query'], 'database should support query');
    assert_same(true, $database['planned_state']['index_plan'], 'database should expose index planned state');
    assert_same(true, $database['planned_state']['subscription_model'], 'database should expose subscription model');
    assert_same(true, $database['planned_state']['transaction_boundary'], 'database should expose transaction boundary');
    assert_same(true, $database['planned_state']['snapshot_export'], 'database should support snapshot export');

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
    assert_same(false, $subscription['push'], 'subscription should not use push in v0.001');

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
}

function test_release_conditions(): void
{
    $release = AdlaireProject::release();
    assert_same('v0.001', $release['version'], 'release version should be v0.001');
    assert_same(false, $release['release_ready'], 'release should not be ready while deployment system is blank');
    assert_same(false, $release['deployment_gate']['ready'], 'deployment release gate should not pass while blank');
    assert_same('blank', $release['deployment_gate']['state']['state'], 'deployment gate should expose blank state');
    assert_true(is_string($release['fingerprint']) && $release['fingerprint'] !== '', 'release fingerprint should be present');
}

function test_documents(): void
{
    $spec = file_get_contents(__DIR__ . '/../ADLAIRE-ECOSYSTEM.md');
    $readme = file_get_contents(__DIR__ . '/../README.md');
    $agents = file_get_contents(__DIR__ . '/../AGENTS.md');
    $projectDoc = file_get_contents(__DIR__ . '/../docs/project.md');

    assert_true(is_string($spec) && str_contains($spec, 'v0.001'), 'spec should describe v0.001');
    assert_true(is_string($spec) && str_contains($spec, 'Selected database | SQLite'), 'spec should select SQLite');
    assert_true(is_string($spec) && str_contains($spec, 'libSQLは正選定しない'), 'spec should not select libSQL');
    assert_true(is_string($spec) && str_contains($spec, 'Realtime Database BaaS Contract'), 'spec should define the realtime database BaaS contract');
    assert_true(is_string($spec) && str_contains($spec, 'Collection Schema'), 'spec should define collection schema');
    assert_true(is_string($spec) && str_contains($spec, 'Snapshot Export'), 'spec should define snapshot export');
    assert_true(is_string($readme) && str_contains($readme, 'BaaS Project'), 'README should describe the BaaS Project');
    assert_true(is_string($agents) && str_contains($agents, '仕様取りまとめ'), 'AGENTS should define the development order');
    assert_true(is_string($projectDoc) && str_contains($projectDoc, 'ADLAIRE-ECOSYSTEM.md'), 'project doc should delegate details to the spec');
}

$tests = [
    'directory_policy' => test_directory_policy(...),
    'project_readiness' => test_project_readiness(...),
    'core_capabilities' => test_core_capabilities(...),
    'realtime_database_data' => test_realtime_database_data(...),
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
