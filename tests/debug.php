<?php

declare(strict_types=1);

require_once __DIR__ . '/../Core/Core.php';
require_once __DIR__ . '/../Frameworks/Backend/Database.php';
require_once __DIR__ . '/../Core/Deployment.php';

final class DebugTestFailure extends RuntimeException
{
}

function assert_true(bool $condition, string $message): void
{
    if (!$condition) {
        throw new DebugTestFailure($message);
    }
}

function assert_same(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new DebugTestFailure($message . ' expected=' . var_export($expected, true) . ' actual=' . var_export($actual, true));
    }
}

function assert_absent(string $path, string $message): void
{
    assert_true(!file_exists(__DIR__ . '/../' . $path), $message . ': ' . $path);
}

function assert_file_count(string $directory, int $expected): void
{
    $files = glob(__DIR__ . '/../' . $directory . '/*');
    $files = array_values(array_filter($files === false ? [] : $files, 'is_file'));
    assert_same($expected, count($files), "{$directory} should contain {$expected} files");
}

function test_release_identity(): void
{
    assert_same('v0.277', Adlaire::version(), 'version should be v0.277');

    $spec = Adlaire::currentSpecification();
    assert_same('v0.277', $spec['version'] ?? null, 'current specification should expose current version');
    assert_same(false, $spec['compatibility']['guaranteed'] ?? null, 'current specification should reject compatibility guarantees');
    assert_same(false, $spec['compatibility']['legacy_shims_allowed'] ?? null, 'current specification should reject legacy shims');
    assert_same('Core/Deployment.php', $spec['entrypoints']['deployment'] ?? null, 'current specification should expose deployment entrypoint');
    assert_same('Applications', $spec['application_modules']['base_directory'] ?? null, 'application modules should use Applications boundary');
    assert_same(false, $spec['application_modules']['legacy_modules_directory_allowed'] ?? null, 'legacy modules directory should not be allowed');
    assert_same(false, $spec['application_modules']['deployment_dependency_allowed'] ?? null, 'application modules should not depend on deployment framework');
    assert_true(in_array('CMS', $spec['application_modules']['examples'] ?? [], true), 'application modules should include CMS example');
    assert_true(in_array('Wiki', $spec['application_modules']['examples'] ?? [], true), 'application modules should include Wiki example');
    assert_same('Docker', $spec['docker_profile']['base_directory'] ?? null, 'Docker profile should use Docker directory');
    assert_same('Docker/Dockerfile.xserver', $spec['docker_profile']['dockerfile'] ?? null, 'Dockerfile should use Docker directory');
    assert_same('Docker/docker-compose.xserver.yml', $spec['docker_profile']['compose_file'] ?? null, 'compose profile should use Docker directory');
    assert_same(false, $spec['docker_profile']['root_docker_files_allowed'] ?? null, 'root Docker files should not be allowed');
    assert_same(45, $spec['release_phases']['source_improvement_cycles'] ?? null, 'current specification should expose source improvement cycles');
    assert_same(5, $spec['release_phases']['physical_cleanup_cycles'] ?? null, 'current specification should expose cleanup cycles');
    assert_same(0, $spec['release_phases']['known_bug_count'] ?? null, 'current specification should expose zero known bugs');
    assert_true(is_file(__DIR__ . '/../Applications/.gitkeep'), 'Applications boundary should be retained');
    assert_true(is_file(__DIR__ . '/../Docker/Dockerfile.xserver'), 'Dockerfile should be collected under Docker');
    assert_true(is_file(__DIR__ . '/../Docker/docker-compose.xserver.yml'), 'docker compose profile should be collected under Docker');
    assert_true(!is_dir(__DIR__ . '/../modules'), 'legacy modules directory should be absent');
    assert_true(!is_file(__DIR__ . '/../modules/Auris/.gitkeep'), 'legacy Auris module placeholder should be absent');
    assert_true(!is_file(__DIR__ . '/../Dockerfile.xserver'), 'root Dockerfile.xserver should be absent');
    assert_true(!is_file(__DIR__ . '/../docker-compose.xserver.yml'), 'root docker-compose.xserver.yml should be absent');

    $contract = Adlaire::stableReleaseContract();
    assert_same(true, $contract['stable_release'] ?? null, 'stable release should be enabled');
    assert_same('v0.277 consolidated breaking development release', $contract['release_name'] ?? null, 'stable release name should be fixed');
    assert_same(false, $contract['deployment_system_compatibility_guaranteed'] ?? null, 'deployment compatibility should be removed');
    assert_same(false, $contract['deployment_system_no_breaking_changes'] ?? null, 'deployment breaking changes should be allowed for v0.277');
    assert_same(true, $contract['v0_277_stable_release_finalized'] ?? null, 'v0.277 stable release should be finalized');
    assert_same(false, $contract['mysql_support_planned'] ?? null, 'MySQL support should remain unplanned');
}

function test_release_readiness(): void
{
    $readiness = Adlaire::releaseReadiness();
    assert_same('v0.277', $readiness['version'] ?? null, 'release readiness should include current version');
    assert_same(true, $readiness['ready'] ?? null, 'release readiness should pass');

    foreach ($readiness['checks'] ?? [] as $name => $passed) {
        assert_same(true, $passed, "release readiness check should pass: {$name}");
    }
}

function test_stable_release_policy(): void
{
    $policy = Adlaire::v0277StableReleasePolicy();
    assert_same('v0.277 Consolidated Breaking Development Release', $policy['theme'] ?? null, 'stable policy should define theme');
    assert_same('stable_release_finalized', $policy['status'] ?? null, 'stable policy should be finalized');
    assert_same(true, $policy['stable_release'] ?? null, 'stable policy should mark stable release');
    assert_same(0, $policy['known_bug_count'] ?? null, 'known bug count should be zero');
    assert_same(true, $policy['deployment_core_contract_changed'] ?? null, 'DeploymentCore contract should change');
    assert_same(false, $policy['deployment_system_compatibility_guaranteed'] ?? null, 'DeploymentCore compatibility should not be guaranteed');
    assert_same(false, $policy['public_api_available'] ?? null, 'public API should remain removed');
    assert_same(false, $policy['configuration_files_allowed'] ?? null, 'configuration files should remain prohibited');
    assert_same(false, $policy['mysql_support_planned'] ?? null, 'MySQL support should remain unplanned');
    assert_true(!is_dir(__DIR__ . '/../FrameworkCore'), 'legacy FrameworkCore shim should be absent');

    $manifest = Adlaire::distributionManifest();
    assert_same(true, $manifest['files_unique'] ?? null, 'distribution manifest files should be unique');
    assert_same(true, $manifest['files_exist'] ?? null, 'distribution manifest files should exist');
    assert_same(true, $manifest['docker_profile_collected'] ?? null, 'distribution manifest should include Docker profile files');
    assert_same(true, $manifest['root_docker_files_absent'] ?? null, 'distribution manifest should reject root Docker files');
    assert_true(in_array('Docker/Dockerfile.xserver', $manifest['files'] ?? [], true), 'distribution manifest should include Dockerfile');
    assert_true(in_array('Docker/docker-compose.xserver.yml', $manifest['files'] ?? [], true), 'distribution manifest should include compose profile');
}

function test_framework_five_file_principle(): void
{
    foreach ([
        'Core',
        'Core',
        'Frameworks/Backend',
        'Frameworks/Frontend',
        'Frameworks/CSS',
        'Frameworks/JavaScript',
    ] as $directory) {
        assert_file_count($directory, 5);
    }

    assert_absent('Core/.gitkeep', 'deployment placeholder should be removed');
    assert_absent('Frameworks/Frontend/.gitkeep', 'frontend placeholder should be removed');
    assert_absent('Frameworks/CSS/.gitkeep', 'CSS placeholder should be removed');
    assert_absent('Frameworks/JavaScript/.gitkeep', 'JavaScript placeholder should be removed');
}

function test_application_module_boundary(): void
{
    $boundary = new ApplicationModuleBoundary();
    $policy = $boundary->handle('applications.policy');
    $manifest = $boundary->handle('applications.manifest');
    $validation = $boundary->handle('applications.validate', $policy);

    assert_same('Applications', $boundary->id(), 'application boundary id should be Applications');
    assert_same(['documented specification'], $boundary->dependencies(), 'application boundary should not depend on deployment');
    assert_same('Applications', $policy['base_directory'] ?? null, 'application boundary policy should use Applications');
    assert_same(false, $policy['deployment_core_dependency_allowed'] ?? null, 'application boundary should forbid deployment dependency');
    assert_same(false, $policy['legacy_modules_directory_allowed'] ?? null, 'application boundary should forbid legacy modules directory');
    assert_true(in_array('applications.status', $manifest['messages'] ?? [], true), 'application boundary manifest should expose status message');
    assert_same(true, $validation['valid'] ?? null, 'application boundary policy should validate');
}

function test_deployment_breaking_boundary(): void
{
    assert_true(!is_file(__DIR__ . '/../DeploymentCore.php'), 'root DeploymentCore.php compatibility entrypoint should be absent');
    assert_true(is_file(__DIR__ . '/../Core/Deployment.php'), 'deployment framework bootstrap should exist');
    assert_true(class_exists(DeployConfig::class), 'DeployConfig should load through deployment bootstrap');
    assert_true(class_exists(Deployer::class), 'Deployer should load through deployment bootstrap');
    assert_true(class_exists(DeploymentPaths::class), 'DeploymentPaths should load through deployment bootstrap');
    assert_true(class_exists(DeploymentEvidence::class), 'DeploymentEvidence should load through deployment bootstrap');
}

function test_consolidated_development_phases(): void
{
    $source = Adlaire::consolidatedSourceImprovementPolicy();
    assert_same(45, $source['cycle_count'] ?? null, 'source improvement phase should declare 45 cycles');
    assert_same(45, count($source['cycles'] ?? []), 'source improvement phase should contain 45 cycles');

    $cleanup = Adlaire::physicalCleanupCyclePolicy();
    assert_same(5, $cleanup['cycle_count'] ?? null, 'physical cleanup phase should declare 5 cycles');
    assert_same(5, count($cleanup['cycles'] ?? []), 'physical cleanup phase should contain 5 cycles');

    $bugs = Adlaire::bugZeroRemediationPolicy();
    assert_same(0, $bugs['known_bug_count'] ?? null, 'bug remediation phase should end with zero known bugs');
    assert_same('unlimited_until_zero', $bugs['iteration_limit'] ?? null, 'bug remediation phase should be unlimited until zero');
    assert_same(true, $source['deployment_core_contract_changed'] ?? null, 'source phase should allow deployment breaking changes');
    assert_same(true, $cleanup['deployment_core_contract_changed'] ?? null, 'cleanup phase should allow deployment breaking changes');
    assert_same(true, $bugs['deployment_core_contract_changed'] ?? null, 'bug phase should allow deployment breaking changes');
}

function test_deployment_control_smoke(): void
{
    $base = sys_get_temp_dir() . '/adlaire-debug-' . getmypid();
    $target = $base . '/target';
    $work = $base . '/work';
    $backup = $base . '/backup';
    $logs = $base . '/logs';
    foreach ([$target, $work, $backup, $logs] as $directory) {
        if (!is_dir($directory)) {
            mkdir($directory, 0777, true);
        }
    }

    $config = DeployConfig::fromArray([
        'repository' => 'local',
        'branch' => 'main',
        'target_dir' => $target,
        'work_dir' => $work,
        'backup_dir' => $backup,
        'log_file' => $logs . '/deploy.log',
        'deploy_allowlist' => ['public_html'],
    ]);
    $deployer = new Deployer($config);
    $preflight = $deployer->preflight();
    $snapshot = $deployer->controlSnapshot();

    assert_same(true, $preflight['ready'] ?? null, 'deployment preflight should pass with writable directories');
    assert_same(false, $preflight['compatibility_guaranteed'] ?? null, 'deployment preflight should not guarantee compatibility');
    assert_same(true, $snapshot['ready'] ?? null, 'deployment snapshot should pass after breaking reorganization');
    assert_same(true, $snapshot['breaking_changes_allowed'] ?? null, 'deployment snapshot should allow breaking changes');
}

function test_core_backend_smoke(): void
{
    $config = new ConfigRepository(['app' => ['debug' => 'yes']]);
    assert_same(true, $config->boolean('app.debug'), 'ConfigRepository should use backend boolean helper');
    $config->forget('app.debug');
    assert_same(false, $config->has('app.debug'), 'ConfigRepository should forget dot keys');

    $database = new Database(':memory:');
    $database->execute('CREATE TABLE smoke (id INTEGER PRIMARY KEY, name TEXT)');
    $database->execute('INSERT INTO smoke (name) VALUES (?)', ['adlaire']);
    $row = $database->execute('SELECT name FROM smoke WHERE id = ?', [1])->fetch();
    assert_same('adlaire', is_array($row) ? ($row['name'] ?? null) : null, 'SQLite smoke query should pass');
}

function test_no_framework_configuration_files(): void
{
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(dirname(__DIR__), FilesystemIterator::SKIP_DOTS));
    $allowed = [
        realpath(__DIR__ . '/../Docker/docker-compose.xserver.yml') => true,
    ];

    foreach ($iterator as $file) {
        if (!$file instanceof SplFileInfo || !$file->isFile()) {
            continue;
        }
        $path = $file->getRealPath();
        if ($path === false || str_contains($path, DIRECTORY_SEPARATOR . '.git' . DIRECTORY_SEPARATOR)) {
            continue;
        }
        if (isset($allowed[$path])) {
            continue;
        }
        $name = $file->getFilename();
        $prohibited = str_starts_with($name, '.env')
            || str_ends_with($name, '.ini')
            || str_ends_with($name, '.conf')
            || str_ends_with($name, '.yaml')
            || str_ends_with($name, '.yml')
            || $name === 'config.php'
            || str_ends_with($name, '.config.php');
        assert_true(!$prohibited, 'framework configuration file should be absent: ' . $file->getPathname());
    }
}

$tests = [
    'release_identity' => test_release_identity(...),
    'release_readiness' => test_release_readiness(...),
    'stable_release_policy' => test_stable_release_policy(...),
    'framework_five_file_principle' => test_framework_five_file_principle(...),
    'application_module_boundary' => test_application_module_boundary(...),
    'deployment_breaking_boundary' => test_deployment_breaking_boundary(...),
    'consolidated_development_phases' => test_consolidated_development_phases(...),
    'deployment_control_smoke' => test_deployment_control_smoke(...),
    'core_backend_smoke' => test_core_backend_smoke(...),
    'no_framework_configuration_files' => test_no_framework_configuration_files(...),
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
