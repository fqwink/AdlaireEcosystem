<?php

declare(strict_types=1);

require_once __DIR__ . '/../Core/Core.php';
require_once __DIR__ . '/../Frameworks/Backend/Database.php';
require_once __DIR__ . '/../Core/Deployment.php';
require_once __DIR__ . '/../Frameworks/Runtime/DashboardData.php';
require_once __DIR__ . '/../Frameworks/Runtime/DashboardView.php';

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
    assert_same('v0.284', Adlaire::version(), 'version should be v0.284');

    $spec = Adlaire::currentSpecification();
    assert_same('v0.284', $spec['version'] ?? null, 'current specification should expose current version');
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
    assert_same(false, $spec['repository_hygiene']['os_metadata_files_allowed'] ?? null, 'OS metadata files should not be allowed');
    assert_same(false, $spec['repository_hygiene']['duplicate_agent_docs_allowed'] ?? null, 'duplicate agent docs should not be allowed');
    assert_same(false, $spec['repository_hygiene']['documentation_detail_duplication_allowed'] ?? null, 'documentation detail duplication should not be allowed');
    assert_same('AGENTS.md', $spec['repository_hygiene']['agent_docs_source'] ?? null, 'AGENTS should be the agent docs source');
    assert_same('adlaire-ecosystem.md', $spec['repository_hygiene']['detail_specification_source'] ?? null, 'adlaire ecosystem spec should be the detail source');
    assert_same('dashboard display assistance', $spec['javascript_framework']['purpose'] ?? null, 'JavaScript framework should be dashboard display assistance');
    assert_same(false, $spec['javascript_framework']['public_api_dependency_allowed'] ?? null, 'JavaScript framework should not depend on Public API');
    assert_same(false, $spec['javascript_framework']['configuration_file_dependency_allowed'] ?? null, 'JavaScript framework should not depend on config files');
    assert_same(false, $spec['javascript_framework']['json_request_response_helpers_allowed'] ?? null, 'JavaScript framework should not provide JSON helpers');
    assert_same('Frameworks/Runtime', $spec['runtime_framework']['base_directory'] ?? null, 'Runtime framework should be the HTTP execution boundary');
    assert_same(false, $spec['runtime_framework']['legacy_frontend_directory_allowed'] ?? null, 'legacy Frontend directory should not be allowed');
    assert_same(true, $spec['github_release_distribution']['enabled'] ?? null, 'GitHub stable release distribution should be enabled');
    assert_same('main', $spec['github_release_distribution']['stable_branch'] ?? null, 'stable branch should be main');
    assert_same('next', $spec['github_release_distribution']['development_branch'] ?? null, 'development branch should be next');
    assert_same(true, $spec['safe_release_version']['enabled'] ?? null, 'safe release version should be enabled');
    assert_same('v0.284 Safe Release', $spec['safe_release_version']['label'] ?? null, 'safe release label should be fixed');
    assert_same(0, $spec['safe_release_version']['known_bug_count_required'] ?? null, 'safe release should require zero known bugs');
    assert_same(true, $spec['safe_release_version']['dashboard_control_matrix_required'] ?? null, 'safe release should require dashboard control matrix');
    assert_same(true, $spec['deployment_release_artifact_manifest']['enabled'] ?? null, 'deployment release artifact manifest should be enabled');
    assert_same(false, $spec['deployment_release_artifact_manifest']['configuration_file'] ?? null, 'release artifact manifest should not be a config file');
    assert_same(true, $spec['deployment_release_artifact_manifest']['single_pass_evidence_builder'] ?? null, 'release artifact evidence should use single-pass builder');
    assert_true(in_array('stable_release_candidate_gate', $spec['deployment_release_artifact_manifest']['integrated_sections'] ?? [], true), 'release artifact manifest should feed stable release gate');
    assert_same('push_artifact', $spec['deployment_artifact_acquisition']['default_method'] ?? null, 'artifact acquisition should default to push');
    assert_true(in_array('pull_artifact', $spec['deployment_artifact_acquisition']['allowed_methods'] ?? [], true), 'artifact acquisition should allow pull mode');
    assert_same(true, $spec['deployment_artifact_acquisition']['xserver_safe_default'] ?? null, 'artifact acquisition should keep Xserver-safe default');
    assert_same(true, $spec['deployment_artifact_pre_extract_preview']['enabled'] ?? null, 'artifact pre-extract preview should be enabled');
    assert_same(true, $spec['deployment_artifact_pre_extract_preview']['read_only'] ?? null, 'artifact pre-extract preview should be read-only');
    assert_same(false, $spec['deployment_artifact_pre_extract_preview']['writes_allowed'] ?? null, 'artifact pre-extract preview should not write');
    assert_same(true, $spec['deployment_artifact_integrity']['enabled'] ?? null, 'artifact integrity should be enabled');
    assert_same(true, $spec['deployment_artifact_integrity']['sha256_required'] ?? null, 'artifact integrity should require sha256');
    assert_same(true, $spec['deployment_artifact_integrity']['artifact_path_optional'] ?? null, 'artifact path should remain optional');
    assert_same(true, $spec['deployment_final_plan']['enabled'] ?? null, 'deployment final plan should be enabled');
    assert_same(true, $spec['deployment_final_plan']['frozen'] ?? null, 'deployment final plan should be frozen');
    assert_same(true, $spec['deployment_final_plan']['fingerprint_required'] ?? null, 'deployment final plan should require fingerprint');
    assert_same(true, $spec['deployment_final_plan']['content_hash_required'] ?? null, 'deployment final plan should require file content hashes');
    assert_same(true, $spec['release_check_evidence']['summary_required'] ?? null, 'release check should require summary output');
    assert_same(true, $spec['release_check_evidence']['named_passes_required'] ?? null, 'release check should require named pass output');
    assert_same(false, $spec['release_check_evidence']['configuration_file'] ?? null, 'release check evidence should not be a configuration file');
    assert_same(45, $spec['release_phases']['source_improvement_cycles'] ?? null, 'current specification should expose source improvement cycles');
    assert_same(5, $spec['release_phases']['physical_cleanup_cycles'] ?? null, 'current specification should expose cleanup cycles');
    assert_same(0, $spec['release_phases']['known_bug_count'] ?? null, 'current specification should expose zero known bugs');
    assert_true(is_file(__DIR__ . '/../Applications/.gitkeep'), 'Applications boundary should be retained');
    assert_true(is_file(__DIR__ . '/../Docker/Dockerfile.xserver'), 'Dockerfile should be collected under Docker');
    assert_true(is_file(__DIR__ . '/../Docker/docker-compose.xserver.yml'), 'docker compose profile should be collected under Docker');
    assert_true(!is_dir(__DIR__ . '/../modules'), 'legacy modules directory should be absent');
    assert_true(!is_file(__DIR__ . '/../modules/Auris/.gitkeep'), 'legacy named module marker should be absent');
    assert_true(!is_file(__DIR__ . '/../Dockerfile.xserver'), 'root Dockerfile.xserver should be absent');
    assert_true(!is_file(__DIR__ . '/../docker-compose.xserver.yml'), 'root docker-compose.xserver.yml should be absent');
    assert_true(is_dir(__DIR__ . '/../Frameworks/Runtime'), 'Runtime framework directory should exist');
    assert_true(!is_dir(__DIR__ . '/../Frameworks/Frontend'), 'legacy Frontend framework directory should be absent');
    assert_true(!is_file(__DIR__ . '/../.DS_Store'), 'OS metadata files should be absent');
    assert_true(!is_file(__DIR__ . '/../CLAUDE.md'), 'empty duplicate agent documentation should be absent');

    $contract = Adlaire::stableReleaseContract();
    assert_same(true, $contract['stable_release'] ?? null, 'stable release should be enabled');
    assert_same('v0.284 stable improvement release', $contract['release_name'] ?? null, 'stable release name should be fixed');
    assert_same(false, $contract['deployment_system_compatibility_guaranteed'] ?? null, 'deployment compatibility should be removed');
    assert_same(false, $contract['deployment_system_no_breaking_changes'] ?? null, 'deployment breaking changes should be allowed for v0.284');
    assert_same(true, $contract['v0_284_stable_release_finalized'] ?? null, 'v0.284 stable release should be finalized');
    assert_same(true, $contract['javascript_framework_implemented'] ?? null, 'stable contract should include JavaScript implementation');
    assert_same(true, $contract['repository_hygiene_enforced'] ?? null, 'stable contract should include repository hygiene');
    assert_same(false, $contract['mysql_support_planned'] ?? null, 'MySQL support should remain unplanned');
}

function test_release_readiness(): void
{
    $readiness = Adlaire::releaseReadiness();
    assert_same('v0.284', $readiness['version'] ?? null, 'release readiness should include current version');
    assert_same(true, $readiness['ready'] ?? null, 'release readiness should pass');

    foreach ($readiness['checks'] ?? [] as $name => $passed) {
        assert_same(true, $passed, "release readiness check should pass: {$name}");
    }
}

function test_stable_release_policy(): void
{
    $policy = Adlaire::v0284StableReleasePolicy();
    assert_same('v0.284 Stable Improvement Release', $policy['theme'] ?? null, 'stable policy should define theme');
    assert_same('stable_release_finalized', $policy['status'] ?? null, 'stable policy should be finalized');
    assert_same(true, $policy['stable_release'] ?? null, 'stable policy should mark stable release');
    assert_same(true, $policy['safe_release_version'] ?? null, 'stable policy should mark safe release version');
    assert_same('v0.284 Safe Release', $policy['safe_release_label'] ?? null, 'stable policy should expose safe release label');
    assert_same(0, $policy['known_bug_count'] ?? null, 'known bug count should be zero');
    assert_same(true, $policy['deployment_core_contract_changed'] ?? null, 'DeploymentCore contract should change');
    assert_same(false, $policy['deployment_system_compatibility_guaranteed'] ?? null, 'DeploymentCore compatibility should not be guaranteed');
    assert_same(false, $policy['public_api_available'] ?? null, 'public API should remain removed');
    assert_same(false, $policy['configuration_files_allowed'] ?? null, 'configuration files should remain prohibited');
    assert_same(false, $policy['mysql_support_planned'] ?? null, 'MySQL support should remain unplanned');
    assert_same(true, $policy['javascript_framework_implemented'] ?? null, 'JavaScript framework should be implemented');
    assert_same(true, $policy['javascript_placeholder_free'] ?? null, 'JavaScript framework should be placeholder-free');
    assert_same(true, $policy['repository_hygiene_enforced'] ?? null, 'repository hygiene should be enforced');
    assert_same(true, $policy['documentation_deduplication_enforced'] ?? null, 'documentation deduplication should be enforced');
    assert_same(true, $policy['runtime_framework_aggregated'] ?? null, 'Runtime framework should be aggregated');
    assert_same(true, $policy['legacy_frontend_framework_absent'] ?? null, 'legacy Frontend framework should be absent');
    assert_same(true, $policy['github_release_distribution_enabled'] ?? null, 'GitHub release distribution should be enabled');
    assert_same(true, $policy['deployment_release_artifact_manifest_required'] ?? null, 'deployment release artifact manifest should be required');
    assert_same(true, $policy['deployment_artifact_acquisition_plan_required'] ?? null, 'deployment artifact acquisition plan should be required');
    assert_same(true, $policy['deployment_artifact_pre_extract_preview_required'] ?? null, 'deployment artifact pre-extract preview should be required');
    assert_same(true, $policy['deployment_artifact_integrity_required'] ?? null, 'deployment artifact integrity should be required');
    assert_same(true, $policy['deployment_final_plan_required'] ?? null, 'deployment final plan should be required');
    assert_same(true, $policy['release_check_summary_required'] ?? null, 'release check summary should be required');
    assert_same(true, $policy['dashboard_control_matrix_required'] ?? null, 'dashboard control matrix should be required');
    assert_true(!is_dir(__DIR__ . '/../FrameworkCore'), 'legacy FrameworkCore shim should be absent');

    $manifest = Adlaire::distributionManifest();
    assert_same(true, $manifest['files_unique'] ?? null, 'distribution manifest files should be unique');
    assert_same(true, $manifest['files_exist'] ?? null, 'distribution manifest files should exist');
    assert_same(true, $manifest['docker_profile_collected'] ?? null, 'distribution manifest should include Docker profile files');
    assert_same(true, $manifest['root_docker_files_absent'] ?? null, 'distribution manifest should reject root Docker files');
    assert_true(in_array('Docker/Dockerfile.xserver', $manifest['files'] ?? [], true), 'distribution manifest should include Dockerfile');
    assert_true(in_array('Docker/docker-compose.xserver.yml', $manifest['files'] ?? [], true), 'distribution manifest should include compose profile');

    $controlMatrixPolicy = Adlaire::dashboardDeploymentControlMatrixPolicy();
    assert_same('Dashboard Deployment Control Matrix', $controlMatrixPolicy['theme'] ?? null, 'dashboard control matrix policy should exist');
    assert_same(false, $controlMatrixPolicy['execution_enabled'] ?? null, 'dashboard control matrix should keep execution disabled');
    assert_same(true, $controlMatrixPolicy['decision_required'] ?? null, 'dashboard control matrix should require release decision');
    assert_same(true, $controlMatrixPolicy['decision_fingerprint_required'] ?? null, 'dashboard control matrix should require decision fingerprint');
    assert_same(true, $controlMatrixPolicy['remediation_guidance_required'] ?? null, 'dashboard control matrix should require remediation guidance');
    assert_true(in_array('final_deployment_plan', $controlMatrixPolicy['rows'] ?? [], true), 'dashboard control matrix should include final deployment plan');
}

function test_framework_five_file_principle(): void
{
    foreach ([
        'Core',
        'Frameworks/Backend',
        'Frameworks/Runtime',
        'Frameworks/CSS',
        'Frameworks/JavaScript',
    ] as $directory) {
        assert_file_count($directory, 5);
    }

    assert_absent('Core/.gitkeep', 'deployment placeholder should be removed');
    assert_absent('Frameworks/Runtime/.gitkeep', 'runtime placeholder should be removed');
    assert_absent('Frameworks/CSS/.gitkeep', 'CSS placeholder should be removed');
    assert_absent('Frameworks/JavaScript/.gitkeep', 'JavaScript placeholder should be removed');

    foreach (glob(__DIR__ . '/../Frameworks/JavaScript/*.js') ?: [] as $file) {
        $contents = file_get_contents($file);
        assert_true(is_string($contents) && !str_contains(strtolower($contents), 'placeholder'), basename($file) . ' should be implemented');
    }
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
    $source = $base . '/source';
    $work = $base . '/work';
    $backup = $base . '/backup';
    $logs = $base . '/logs';
    foreach ([$target, $source . '/public_html', $work, $backup, $logs] as $directory) {
        if (!is_dir($directory)) {
            mkdir($directory, 0777, true);
        }
    }
    file_put_contents($source . '/public_html/index.php', '<?php echo "Adlaire";');
    file_put_contents($source . '/public_html/dashboard.php', '<?php echo "Dashboard";');
    $artifact = $work . '/adlaire-ecosystem-v0.284.tar.gz';
    file_put_contents($artifact, 'adlaire-release-artifact-v0.284');
    $artifactSha256 = hash_file('sha256', $artifact);

    $config = DeployConfig::fromArray([
        'repository' => 'local',
        'branch' => 'main',
        'target_dir' => $target,
        'work_dir' => $work,
        'backup_dir' => $backup,
        'log_file' => $logs . '/deploy.log',
        'deploy_allowlist' => ['public_html'],
        'release_manifest' => [
            'enabled' => true,
            'distribution_channel' => 'GitHub Releases',
            'tag' => 'v0.284',
            'artifact' => 'adlaire-ecosystem-v0.284.tar.gz',
            'artifact_path' => $artifact,
            'artifact_sha256' => $artifactSha256,
            'artifact_files' => [
                'public_html/index.php',
                'public_html/dashboard.php',
            ],
            'artifact_acquisition' => [
                'method' => 'push_artifact',
                'server_network_required' => false,
                'transport' => 'release_archive',
                'source_verified_before_extract' => true,
            ],
            'release_check_passed' => true,
            'allowed_files' => ['public_html'],
            'excluded_files' => ['.DS_Store', 'framework configuration files', 'public API helpers'],
            'breaking_changes_documented' => true,
            'rollback_target' => 'latest_snapshot',
        ],
    ]);
    $deployer = new Deployer($config);
    $preflight = $deployer->preflight();
    $snapshot = $deployer->controlSnapshot();
    $releaseArtifact = $deployer->validateReleaseArtifactManifest($deployer->releaseArtifactManifest());
    $artifactAcquisition = $deployer->artifactAcquisitionPlan($deployer->releaseArtifactManifest());
    $artifactPreview = $deployer->artifactPreExtractPreview($deployer->releaseArtifactManifest());
    $artifactIntegrity = $deployer->artifactIntegrityCheck($deployer->releaseArtifactManifest());
    $finalPlan = $deployer->finalDeploymentPlan($source);
    $evidence = $deployer->releaseEvidenceBundle($source);
    $report = $deployer->deploymentControlReport($source);

    assert_same(true, $preflight['ready'] ?? null, 'deployment preflight should pass with writable directories');
    assert_same(true, $preflight['checks']['release_artifact_manifest_valid'] ?? null, 'deployment preflight should validate release artifact manifest');
    assert_same(true, $preflight['checks']['artifact_acquisition_plan_valid'] ?? null, 'deployment preflight should validate artifact acquisition plan');
    assert_same(true, $preflight['checks']['artifact_pre_extract_preview_valid'] ?? null, 'deployment preflight should validate artifact pre-extract preview');
    assert_same(true, $preflight['checks']['artifact_integrity_valid'] ?? null, 'deployment preflight should validate artifact integrity');
    assert_same(false, $preflight['compatibility_guaranteed'] ?? null, 'deployment preflight should not guarantee compatibility');
    assert_same(true, $snapshot['ready'] ?? null, 'deployment snapshot should pass after breaking reorganization');
    assert_same(true, $snapshot['breaking_changes_allowed'] ?? null, 'deployment snapshot should allow breaking changes');
    assert_same('github_releases', $snapshot['manifest']['release_source'] ?? null, 'deployment snapshot should expose GitHub Releases source');
    assert_same(true, $releaseArtifact['valid'] ?? null, 'release artifact manifest should validate');
    assert_same(false, $releaseArtifact['configuration_file'] ?? null, 'release artifact manifest should not be a configuration file');
    assert_same(true, $releaseArtifact['audit_artifact'] ?? null, 'release artifact manifest should be audit evidence');
    assert_same(true, $artifactAcquisition['valid'] ?? null, 'artifact acquisition plan should validate');
    assert_same('push_artifact', $artifactAcquisition['plan']['method'] ?? null, 'artifact acquisition should use push mode by default');
    assert_same(false, $artifactAcquisition['plan']['server_network_required'] ?? null, 'push artifact should not require server network');
    assert_same(true, $artifactPreview['valid'] ?? null, 'artifact pre-extract preview should validate');
    assert_same(2, $artifactPreview['summary']['accepted'] ?? null, 'artifact pre-extract preview should accept allowed files');
    assert_same(0, $artifactPreview['summary']['rejected'] ?? null, 'artifact pre-extract preview should reject no files');
    assert_same(true, $artifactIntegrity['valid'] ?? null, 'artifact integrity should validate');
    assert_same(true, $artifactIntegrity['summary']['sha256_matches'] ?? null, 'artifact integrity should match sha256');
    assert_same($releaseArtifact, $report['release_artifact_manifest'] ?? null, 'control report should reuse release artifact validation evidence');
    assert_same($artifactAcquisition, $report['artifact_acquisition_plan'] ?? null, 'control report should reuse artifact acquisition evidence');
    assert_same($artifactPreview, $report['artifact_pre_extract_preview'] ?? null, 'control report should reuse artifact preview evidence');
    assert_same($artifactIntegrity, $report['artifact_integrity'] ?? null, 'control report should reuse artifact integrity evidence');
    assert_same(true, $finalPlan['valid'] ?? null, 'final deployment plan should validate');
    assert_same(true, $finalPlan['frozen'] ?? null, 'final deployment plan should be frozen');
    assert_same(false, $finalPlan['writes_allowed'] ?? null, 'final deployment plan should be read-only');
    assert_true(is_string($finalPlan['fingerprint'] ?? null) && preg_match('/^[a-f0-9]{64}$/', $finalPlan['fingerprint']) === 1, 'final deployment plan fingerprint should be sha256');
    assert_same(2, count($finalPlan['file_fingerprints'] ?? []), 'final deployment plan should hash changed files');
    assert_true(is_string($finalPlan['file_fingerprints'][0]['sha256'] ?? null), 'final deployment plan should expose file sha256');
    assert_same(true, $evidence['evidence']['release_gate_inputs']['release_artifact_manifest_valid'] ?? null, 'release evidence should include artifact manifest result');
    assert_same(true, $evidence['evidence']['release_gate_inputs']['artifact_acquisition_plan_valid'] ?? null, 'release evidence should include artifact acquisition result');
    assert_same(true, $evidence['evidence']['release_gate_inputs']['artifact_pre_extract_preview_valid'] ?? null, 'release evidence should include artifact pre-extract preview result');
    assert_same(true, $evidence['evidence']['release_gate_inputs']['artifact_integrity_valid'] ?? null, 'release evidence should include artifact integrity result');
    assert_same(true, $evidence['evidence']['release_gate_inputs']['final_deployment_plan_valid'] ?? null, 'release evidence should include final deployment plan result');
    assert_same($finalPlan['fingerprint'], $evidence['evidence']['release_gate_inputs']['final_deployment_plan_fingerprint'] ?? null, 'release evidence should include final deployment plan fingerprint');

    $previousReport = $deployer->deploymentControlReport($source);
    file_put_contents($source . '/public_html/dashboard.php', '<?php echo "Dashboard changed";');
    $diff = $deployer->deploymentControlDiff($previousReport, $source);
    assert_true(in_array('final_deployment_plan', $diff['changed_sections'] ?? [], true), 'deployment control diff should detect final plan content changes');
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

function test_documentation_deduplication(): void
{
    $readme = file_get_contents(__DIR__ . '/../README.md');
    $xserver = file_get_contents(__DIR__ . '/../docs/xserver-production-equivalent.md');
    assert_true(is_string($readme), 'README should be readable');
    assert_true(is_string($xserver), 'Xserver verification doc should be readable');

    foreach (['Public API', 'MySQL', 'Xserver', 'GitHub Releases', '設定ファイル', '互換性', '破壊的変更', 'SQLite', 'libSQL', '5ファイル'] as $term) {
        assert_true(!str_contains($readme, $term), 'README should not duplicate detailed specification term: ' . $term);
    }

    foreach (['Deployment Core', 'Runtime', 'GitHub Releases', 'DB方針', 'Public API', 'MySQL', 'SQLite', 'libSQL', '設定ファイル'] as $term) {
        assert_true(!str_contains($xserver, $term), 'Xserver verification doc should not duplicate broad specification term: ' . $term);
    }
}

function test_dashboard_control_matrix(): void
{
    $data = AdlaireDashboardData::collect(dirname(__DIR__));
    $matrix = $data['sections']['deployment_control_matrix'] ?? null;
    assert_true(is_array($matrix), 'dashboard should expose deployment control matrix');
    assert_same('ready', $matrix['status'] ?? null, 'dashboard control matrix should expose ready status');
    assert_same(false, $matrix['execution_enabled'] ?? null, 'dashboard control matrix should not enable execution');
    assert_same(true, $matrix['read_only'] ?? null, 'dashboard control matrix should be read-only');
    assert_same(8, $matrix['summary']['total'] ?? null, 'dashboard control matrix should count total rows');
    assert_same(8, $matrix['summary']['ready'] ?? null, 'dashboard control matrix should count ready rows');
    assert_same(0, $matrix['summary']['blocked'] ?? null, 'dashboard control matrix should count blocked rows');
    assert_true(is_string($matrix['fingerprint'] ?? null) && preg_match('/^[a-f0-9]{64}$/', $matrix['fingerprint']) === 1, 'dashboard control matrix should expose fingerprint');
    assert_same(true, $matrix['decision']['release_allowed'] ?? null, 'dashboard control matrix should expose release decision');
    assert_same('all_controls_ready', $matrix['decision']['reason'] ?? null, 'dashboard control matrix should expose release decision reason');
    assert_same([], $matrix['decision']['blockers'] ?? null, 'dashboard control matrix should expose blockers');
    assert_same(true, $data['sections']['overview']['deployment_control_ready'] ?? null, 'dashboard overview should include control readiness');
    foreach ([
        'release_readiness',
        'stable_release_gate',
        'release_artifact_manifest',
        'artifact_acquisition',
        'artifact_pre_extract_preview',
        'artifact_integrity',
        'final_deployment_plan',
        'release_check_evidence',
    ] as $row) {
        assert_true(isset($matrix['rows'][$row]), 'dashboard control matrix should expose row: ' . $row);
        assert_true(is_string($matrix['rows'][$row]['severity'] ?? null), 'dashboard control matrix row should expose severity: ' . $row);
        assert_true(is_string($matrix['rows'][$row]['next_action'] ?? null), 'dashboard control matrix row should expose next action: ' . $row);
    }

    $html = AdlaireDashboardView::render($data);
    assert_true(str_contains($html, 'Deployment Control Matrix'), 'dashboard HTML should render control matrix section');
    assert_true(str_contains($html, 'Release Decision'), 'dashboard HTML should render release decision');
    assert_true(str_contains($html, $matrix['fingerprint']), 'dashboard HTML should render decision fingerprint');
    assert_true(str_contains($html, 'run_release_check'), 'dashboard HTML should render remediation guidance');
    assert_true(str_contains($html, 'Release Gate Inputs'), 'dashboard HTML should render release gate inputs');
    assert_true(str_contains($html, 'control-matrix-row'), 'dashboard HTML should render matrix rows');
    assert_true(str_contains($html, 'ready controls'), 'dashboard HTML should render matrix summary');
}

function test_release_check_script_evidence(): void
{
    $script = file_get_contents(__DIR__ . '/../scripts/release-check.sh');
    assert_true(is_string($script), 'release check script should be readable');
    assert_true(str_contains($script, 'checks_passed=0'), 'release check should count passed check groups');
    assert_true(str_contains($script, 'PASS release-check:'), 'release check should emit named pass output');
    assert_true(str_contains($script, 'Release check OK: ${checks_passed} checks'), 'release check should emit summary output');
    foreach ([
        'php_lint',
        'official_debug_test',
        'xserver_profile_audit',
        'framework_five_file_principle',
        'empty_directories_absent',
        'documentation_deduplication',
    ] as $check) {
        assert_true(str_contains($script, 'pass "' . $check . '"'), 'release check should name check group: ' . $check);
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
    'documentation_deduplication' => test_documentation_deduplication(...),
    'dashboard_control_matrix' => test_dashboard_control_matrix(...),
    'release_check_script_evidence' => test_release_check_script_evidence(...),
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
