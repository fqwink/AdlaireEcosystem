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
    assert_same('in_memory', $database['planned_state']['runtime_execution'], 'database runtime should be in-memory in v0.001');
    assert_same(true, $database['planned_state']['collection_stream'], 'database should support collection stream tracking');
    assert_same(true, $database['planned_state']['record_lookup'], 'database should support record lookup');
    assert_same(true, $database['planned_state']['record_listing'], 'database should support record listing');

    $gate = AdlaireDeployment::releaseGate();
    assert_same(false, $gate['ready'], 'deployment system release gate should not pass while blank');
    assert_same('deployment_system_policy_reset', $gate['blocking_reason'], 'deployment system should report policy reset as blocking reason');
}

function test_realtime_database_data(): void
{
    AdlaireDatabase::reset();
    assert_true(isset(AdlaireDatabase::collections()['system']), 'system collection should be defined');
    assert_true(isset(AdlaireDatabase::collections()['application']), 'application collection should be defined');

    $defined = AdlaireDatabase::defineCollection('audit_log', 'system');
    assert_same('audit_log', $defined['name'], 'custom collection should be defined');
    assert_same('system', $defined['channel'], 'custom collection should keep channel');

    $created = AdlaireDatabase::create('system', ['name' => 'alpha']);
    assert_same('system', $created['collection'], 'created record should keep collection');
    assert_same('system', $created['channel'], 'created record should include collection channel');
    assert_same(1, $created['version'], 'created record should start at version 1');
    assert_same($created, AdlaireDatabase::get('system', $created['id']), 'record lookup should return the created record');
    assert_same(1, count(AdlaireDatabase::records('system')), 'record listing should include the created record');

    $updated = AdlaireDatabase::update('system', $created['id'], ['name' => 'beta']);
    assert_same(2, $updated['version'], 'updated record should increment version');
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
    assert_true(is_string($spec) && str_contains($spec, 'Realtime Database BaaS Contract'), 'spec should define the realtime database BaaS contract');
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
