<?php

declare(strict_types=1);

require_once __DIR__ . '/../Core/Core.php';
require_once __DIR__ . '/../Frameworks/Backend/Database.php';
require_once __DIR__ . '/../Core/Deployment.php';
require_once __DIR__ . '/../Frameworks/Runtime/DashboardSecurity.php';
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
    assert_same('v0.285', $spec['deployment_execution_foundation']['target'] ?? null, 'deployment execution foundation should target next version');
    assert_same('Deployer::executionGate()', $spec['deployment_execution_foundation']['execution_gate_method'] ?? null, 'deployment execution foundation should expose gate method');
    assert_same(false, $spec['deployment_execution_foundation']['dashboard_execution_enabled'] ?? null, 'deployment execution foundation should keep dashboard execution disabled');
    assert_same(true, $spec['deployment_execution_foundation']['final_plan_fingerprint_required'] ?? null, 'deployment execution foundation should require final plan fingerprint');
    assert_same('v0.286', $spec['deployment_dashboard_control']['target'] ?? null, 'deployment dashboard control should target next dashboard version');
    assert_same(true, $spec['deployment_dashboard_control']['execution_gate_view'] ?? null, 'deployment dashboard control should expose execution gate view');
    assert_same(true, $spec['deployment_dashboard_control']['dry_run_panel'] ?? null, 'deployment dashboard control should expose dry-run panel');
    assert_same(true, $spec['deployment_dashboard_control']['audit_ledger_viewer'] ?? null, 'deployment dashboard control should expose audit ledger viewer');
    assert_same(false, $spec['deployment_dashboard_control']['dashboard_execution_enabled'] ?? null, 'deployment dashboard control should keep execution disabled');
    assert_same('v0.290', $spec['auto_deployment_roadmap']['target'] ?? null, 'auto deployment roadmap should target v0.290');
    assert_same('Deployer::autoDeploy()', $spec['auto_deployment_roadmap']['core_engine_method'] ?? null, 'auto deployment roadmap should expose auto deploy engine');
    assert_same(false, $spec['auto_deployment_roadmap']['public_api_required'] ?? null, 'auto deployment roadmap should not require public API');
    assert_true(in_array('rollback_on_failure', $spec['auto_deployment_roadmap']['required_flow'] ?? [], true), 'auto deployment roadmap should require rollback on failure');
    assert_same('v0.295', $spec['provider_api_deployment']['target'] ?? null, 'provider API deployment should target v0.295');
    assert_same(false, $spec['provider_api_deployment']['public_api_required'] ?? null, 'provider API deployment should not require framework public API');
    assert_same(true, $spec['provider_api_deployment']['provider_api_internal_only'] ?? null, 'provider API deployment should be internal only');
    assert_true(in_array('xserver_rental', $spec['provider_api_deployment']['supported_initial_profiles'] ?? [], true), 'provider API deployment should support xserver rental profile');
    assert_true(in_array('xserver_vps', $spec['provider_api_deployment']['supported_initial_profiles'] ?? [], true), 'provider API deployment should support xserver vps profile');
    assert_same('v0.305', $spec['provider_orchestrated_deployment']['target'] ?? null, 'provider orchestrated deployment should target v0.305');
    assert_same('Deployer::providerOrchestrator()', $spec['provider_orchestrated_deployment']['methods']['orchestrator'] ?? null, 'provider orchestrated deployment should expose orchestrator method');
    assert_same('Deployer::providerOrchestratedReleaseGate()', $spec['provider_orchestrated_deployment']['methods']['release_gate'] ?? null, 'provider orchestrated deployment should expose release gate method');
    assert_same(false, $spec['provider_orchestrated_deployment']['credentials_persisted'] ?? null, 'provider orchestrated deployment should not persist credentials');
    assert_same('v0.311', $spec['provider_runtime_foundation']['target'] ?? null, 'provider runtime foundation should target v0.311');
    assert_same('Deployer::providerRuntimeInterface()', $spec['provider_runtime_foundation']['methods']['runtime_interface'] ?? null, 'provider runtime foundation should expose runtime interface');
    assert_same('Deployer::providerSecretRedactionEngine()', $spec['provider_runtime_foundation']['methods']['secret_redaction_engine'] ?? null, 'provider runtime foundation should expose secret redaction');
    assert_same(false, $spec['provider_runtime_foundation']['credentials_persisted'] ?? null, 'provider runtime foundation should not persist credentials');
    assert_same('v0.320', $spec['provider_runtime_execution']['target'] ?? null, 'provider runtime execution should target v0.320');
    assert_same('Deployer::xserverRentalRuntimeAdapter()', $spec['provider_runtime_execution']['methods']['xserver_rental_adapter'] ?? null, 'provider runtime execution should expose rental adapter');
    assert_same('Deployer::xserverVpsRuntimeAdapter()', $spec['provider_runtime_execution']['methods']['xserver_vps_adapter'] ?? null, 'provider runtime execution should expose vps adapter');
    assert_same('Deployer::providerRuntimeExecutionGate()', $spec['provider_runtime_execution']['methods']['execution_gate'] ?? null, 'provider runtime execution should expose execution gate');
    $configurationPolicy = Adlaire::configurationFilePolicy();
    assert_same(true, $configurationPolicy['ini_files_allowed'] ?? null, 'ini files should be fully allowed');
    assert_true(!in_array('*.ini', $configurationPolicy['prohibited_patterns'] ?? [], true), 'ini files should not be prohibited');
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
    assert_true(is_string($manifest['fingerprint'] ?? null) && preg_match('/^[a-f0-9]{64}$/', $manifest['fingerprint']) === 1, 'distribution manifest should expose fingerprint');
    assert_same($manifest['file_count'] ?? null, count($manifest['file_fingerprints'] ?? []), 'distribution manifest should fingerprint every file');
    assert_true(is_string($manifest['file_fingerprints'][0]['sha256'] ?? null), 'distribution manifest should expose file sha256');
    assert_same(true, $manifest['docker_profile_collected'] ?? null, 'distribution manifest should include Docker profile files');
    assert_same(true, $manifest['root_docker_files_absent'] ?? null, 'distribution manifest should reject root Docker files');
    assert_same(true, $manifest['safe_release']['enabled'] ?? null, 'distribution manifest should expose safe release');
    assert_same('v0.284 Safe Release', $manifest['safe_release']['label'] ?? null, 'distribution manifest should expose safe release label');
    assert_same(0, $manifest['safe_release']['known_bug_count'] ?? null, 'distribution manifest should expose zero known bugs');
    assert_same(true, $manifest['safe_release']['dashboard_control_matrix_required'] ?? null, 'distribution manifest should require dashboard control matrix');
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
    $snapshot = $backup . '/20260101000000/public_html';
    if (!is_dir($snapshot)) {
        mkdir($snapshot, 0777, true);
    }
    file_put_contents($backup . '/20260101000000/manifest.json', json_encode(['files' => ['public_html/index.php']], JSON_THROW_ON_ERROR));
    file_put_contents($snapshot . '/index.php', '<?php echo "Previous";');
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
    $executionGate = $deployer->executionGate($source, $finalPlan['fingerprint']);
    $dryRun = $deployer->deploymentDryRun($source, $finalPlan['fingerprint']);
    $ledger = $deployer->recordDeploymentAuditLedger('dry_run_ready', $source, ['operator' => 'debug']);
    assert_same(true, $executionGate['ready'] ?? null, 'deployment execution gate should pass with matching final plan fingerprint');
    assert_same(false, $executionGate['dashboard_execution_enabled'] ?? null, 'deployment execution gate should keep dashboard execution disabled');
    assert_same(true, $executionGate['checks']['expected_fingerprint_matched'] ?? null, 'deployment execution gate should verify expected fingerprint');
    assert_same($finalPlan['fingerprint'], $executionGate['final_plan_fingerprint'] ?? null, 'deployment execution gate should expose final plan fingerprint');
    assert_same(true, $dryRun['dry_run'] ?? null, 'deployment dry-run should identify dry-run mode');
    assert_same(false, $dryRun['apply_allowed'] ?? null, 'deployment dry-run should never apply changes');
    assert_same($finalPlan['fingerprint'], $dryRun['final_plan_fingerprint'] ?? null, 'deployment dry-run should carry final plan fingerprint');
    assert_same(true, $ledger['recorded'] ?? null, 'deployment audit ledger should record evidence');
    assert_same(false, $ledger['configuration_file'] ?? null, 'deployment audit ledger should not be configuration file');
    assert_same(true, is_file($ledger['path'] ?? ''), 'deployment audit ledger should write JSONL evidence');
    assert_true(str_contains((string)file_get_contents($ledger['path']), 'dry_run_ready'), 'deployment audit ledger should include event name');
    $autoDeploy = $deployer->autoDeploy($source, $finalPlan['fingerprint']);
    assert_same('completed', $autoDeploy['status'] ?? null, 'auto deployment should complete when gate and fingerprint match');
    assert_same(true, $autoDeploy['applied'] ?? null, 'auto deployment should apply changes');
    assert_same(false, $autoDeploy['rolled_back'] ?? null, 'auto deployment should not rollback on success');
    assert_same(true, is_file($target . '/public_html/index.php'), 'auto deployment should write target file');
    assert_true(str_contains((string)file_get_contents($backup . '/deployment_audit_ledger.jsonl'), 'auto_deploy_completed'), 'auto deployment should record completion ledger');
    $providerMatrix = $deployer->providerCapabilityMatrix('xserver_rental');
    $vpsProviderPlan = $deployer->providerExecutionPlan('xserver_vps');
    $genericProviderEvidence = $deployer->providerAuditEvidence('future_provider');
    assert_same('xserver_rental', $providerMatrix['selected_provider'] ?? null, 'provider matrix should select xserver rental');
    assert_same('Xserver Rental Server', $providerMatrix['selected_profile']['label'] ?? null, 'provider matrix should expose xserver rental label');
    assert_same(false, $providerMatrix['public_api_required'] ?? null, 'provider matrix should not require public API');
    assert_same(false, $providerMatrix['credentials_persisted'] ?? null, 'provider matrix should not persist credentials');
    assert_same('xserver_vps', $vpsProviderPlan['provider'] ?? null, 'provider execution plan should select xserver vps');
    assert_true(in_array('service_restart', $vpsProviderPlan['steps'] ?? [], true), 'xserver vps plan should allow service restart step');
    assert_same(false, $vpsProviderPlan['manual_required'] ?? null, 'xserver vps plan should not require manual operation by default');
    assert_same('generic_provider', $genericProviderEvidence['provider'] ?? null, 'unknown provider should fall back to generic provider');
    assert_true(is_string($genericProviderEvidence['fingerprint'] ?? null) && preg_match('/^[a-f0-9]{64}$/', $genericProviderEvidence['fingerprint']) === 1, 'provider evidence should expose fingerprint');
    $orchestrator = $deployer->providerOrchestrator('xserver_vps');
    $remotePlan = $deployer->remoteOperationPlan('xserver_vps');
    $credentialPolicy = $deployer->providerCredentialPolicy();
    $transportEvidence = $deployer->providerApiTransportEvidence('xserver_vps', 'restart_service');
    $multiProviderPlan = $deployer->multiProviderDeploymentPlan(['xserver_rental', 'xserver_vps']);
    $healthProbe = $deployer->providerHealthProbe('xserver_vps');
    $rollbackOrchestrator = $deployer->providerRollbackOrchestrator('xserver_vps');
    $providerGate = $deployer->providerOrchestratedReleaseGate('xserver_vps');
    assert_same(true, $orchestrator['valid'] ?? null, 'provider orchestrator should validate');
    assert_true(in_array('provider_orchestration', $orchestrator['orchestration_layers'] ?? [], true), 'provider orchestrator should expose orchestration layer');
    assert_true(in_array('restart_service', $remotePlan['operations'] ?? [], true), 'remote operation plan should include restart service for vps');
    assert_same(true, $credentialPolicy['runtime_injection_only'] ?? null, 'provider credential policy should require runtime injection');
    assert_same(false, $credentialPolicy['credentials_persisted'] ?? null, 'provider credential policy should not persist credentials');
    assert_same(true, $transportEvidence['redaction_applied'] ?? null, 'provider transport evidence should redact values');
    assert_same(false, $transportEvidence['secret_values_exposed'] ?? null, 'provider transport evidence should not expose secrets');
    assert_same(2, $multiProviderPlan['provider_count'] ?? null, 'multi provider plan should include two providers');
    assert_same(true, $healthProbe['valid'] ?? null, 'provider health probe should validate');
    assert_same(true, $rollbackOrchestrator['valid'] ?? null, 'provider rollback orchestrator should validate');
    assert_same(true, $providerGate['ready'] ?? null, 'provider orchestrated release gate should pass');
    assert_same('v0.305', $providerGate['target'] ?? null, 'provider orchestrated release gate should target v0.305');
    $runtime = $deployer->providerRuntimeInterface('xserver_vps');
    $remoteState = $deployer->remoteStateSnapshot('xserver_vps');
    $transaction = $deployer->providerTransactionPlan('xserver_vps');
    $retry = $deployer->providerRetryBackoffPolicy();
    $rateLimit = $deployer->providerRateLimitGuard();
    $redaction = $deployer->providerSecretRedactionEngine(['api_token' => 'secret-token', 'region' => 'jp']);
    assert_same(true, $runtime['valid'] ?? null, 'provider runtime interface should validate');
    assert_true(in_array('rollback', $runtime['operations'] ?? [], true), 'provider runtime should expose rollback operation');
    assert_true(is_string($remoteState['fingerprint'] ?? null) && preg_match('/^[a-f0-9]{64}$/', $remoteState['fingerprint']) === 1, 'remote state snapshot should expose fingerprint');
    assert_same(true, $transaction['rollback_on_failure'] ?? null, 'provider transaction should require rollback on failure');
    assert_same(3, $retry['retry_max'] ?? null, 'provider retry policy should define retry max');
    assert_same(true, $rateLimit['emergency_stop_enabled'] ?? null, 'provider rate limit should expose emergency stop');
    assert_same('[redacted]', $redaction['payload']['api_token'] ?? null, 'provider redaction should redact token');
    assert_same('jp', $redaction['payload']['region'] ?? null, 'provider redaction should preserve non-secret values');
    assert_same(false, $redaction['secret_values_exposed'] ?? null, 'provider redaction should not expose secrets');
    $rentalAdapter = $deployer->xserverRentalRuntimeAdapter();
    $vpsAdapter = $deployer->xserverVpsRuntimeAdapter();
    $runtimeExecution = $deployer->providerRuntimeExecutionPlan('xserver_vps');
    $artifactLifecycle = $deployer->remoteArtifactLifecycle('xserver_vps');
    $switchStrategy = $deployer->remoteReleaseSwitchStrategy('xserver_rental');
    $failure = $deployer->providerRuntimeFailureClassifier('fingerprint_mismatch');
    $recovery = $deployer->providerRuntimeRecoveryPlan('fingerprint_mismatch');
    $runtimeDashboard = $deployer->providerRuntimeDashboardControl('xserver_vps');
    $runtimeGate = $deployer->providerRuntimeExecutionGate('xserver_vps');
    assert_same('xserver_rental', $rentalAdapter['provider'] ?? null, 'xserver rental runtime adapter should target rental');
    assert_same(false, $rentalAdapter['service_restart_supported'] ?? null, 'xserver rental adapter should not support service restart');
    assert_same('xserver_vps', $vpsAdapter['provider'] ?? null, 'xserver vps runtime adapter should target vps');
    assert_same(true, $vpsAdapter['service_restart_supported'] ?? null, 'xserver vps adapter should support service restart');
    assert_same(true, $runtimeExecution['valid'] ?? null, 'provider runtime execution plan should validate');
    assert_true(in_array('transfer', $runtimeExecution['phases'] ?? [], true), 'provider runtime execution plan should include transfer');
    assert_true(in_array('promoted', $artifactLifecycle['states'] ?? [], true), 'remote artifact lifecycle should include promoted state');
    assert_same('public_html_overwrite', $switchStrategy['strategy'] ?? null, 'xserver rental switch strategy should use public_html overwrite');
    assert_same('critical', $failure['severity'] ?? null, 'fingerprint mismatch should be critical');
    assert_true(in_array('block_apply', $recovery['actions'] ?? [], true), 'fingerprint mismatch recovery should block apply');
    assert_same(true, $runtimeDashboard['ready'] ?? null, 'provider runtime dashboard control should be ready');
    assert_same(true, $runtimeGate['ready'] ?? null, 'provider runtime execution gate should pass');
    assert_same('v0.320', $runtimeGate['target'] ?? null, 'provider runtime execution gate should target v0.320');
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
    assert_same(11, $matrix['summary']['total'] ?? null, 'dashboard control matrix should count total rows');
    assert_same(11, $matrix['summary']['ready'] ?? null, 'dashboard control matrix should count ready rows');
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
        'execution_gate_view',
        'dry_run_panel',
        'audit_ledger_viewer',
        'release_check_evidence',
    ] as $row) {
        assert_true(isset($matrix['rows'][$row]), 'dashboard control matrix should expose row: ' . $row);
        assert_true(is_string($matrix['rows'][$row]['severity'] ?? null), 'dashboard control matrix row should expose severity: ' . $row);
        assert_true(is_string($matrix['rows'][$row]['next_action'] ?? null), 'dashboard control matrix row should expose next action: ' . $row);
    }
    $executionGate = $data['sections']['deployment_execution_gate'] ?? null;
    $dryRun = $data['sections']['deployment_dry_run'] ?? null;
    $ledger = $data['sections']['deployment_audit_ledger'] ?? null;
    $timeline = $data['sections']['deployment_decision_timeline'] ?? null;
    $controls = $data['sections']['dashboard_deploy_controls'] ?? null;
    $queue = $data['sections']['deployment_queue_status'] ?? null;
    $fullGate = $data['sections']['full_auto_deployment_gate'] ?? null;
    $provider = $data['sections']['provider_api_deployment'] ?? null;
    $providerOrchestration = $data['sections']['provider_orchestrated_deployment'] ?? null;
    $providerRuntime = $data['sections']['provider_runtime_foundation'] ?? null;
    $providerRuntimeExecution = $data['sections']['provider_runtime_execution'] ?? null;
    assert_true(is_array($executionGate), 'dashboard should expose execution gate view');
    assert_same(true, $executionGate['ready'] ?? null, 'dashboard execution gate view should be ready');
    assert_same(false, $executionGate['dashboard_execution_enabled'] ?? null, 'dashboard execution gate view should keep execution disabled');
    assert_same(false, $executionGate['apply_enabled'] ?? null, 'dashboard execution gate view should not enable apply');
    assert_true(is_array($dryRun), 'dashboard should expose dry-run panel');
    assert_same(true, $dryRun['dry_run_required'] ?? null, 'dashboard dry-run panel should require dry-run');
    assert_same(false, $dryRun['apply_allowed'] ?? null, 'dashboard dry-run panel should not allow apply');
    assert_true(is_array($ledger), 'dashboard should expose audit ledger viewer');
    assert_same(true, $ledger['read_only'] ?? null, 'dashboard audit ledger should be read-only');
    assert_same(false, $ledger['configuration_file'] ?? null, 'dashboard audit ledger should not be configuration file');
    assert_true(is_array($timeline), 'dashboard should expose decision timeline');
    assert_same(true, $timeline['ready'] ?? null, 'dashboard decision timeline should be ready');
    assert_same(6, count($timeline['events'] ?? []), 'dashboard decision timeline should expose control events');
    assert_true(is_array($controls), 'dashboard should expose deploy controls');
    assert_same(true, $controls['dashboard_execution_enabled'] ?? null, 'dashboard deploy controls should enable safety-gated execution');
    assert_same(true, $controls['csrf_required'] ?? null, 'dashboard deploy controls should require csrf');
    assert_same(true, $controls['short_lived_execution_token_required'] ?? null, 'dashboard deploy controls should require short-lived execution token');
    assert_same(false, $controls['public_api_required'] ?? null, 'dashboard deploy controls should not require public API');
    assert_true(is_array($queue), 'dashboard should expose deployment queue status');
    assert_same('idle', $queue['status'] ?? null, 'dashboard queue should be idle by default');
    assert_true(is_array($fullGate), 'dashboard should expose full auto deployment gate');
    assert_same('v0.290', $fullGate['target'] ?? null, 'full auto deployment gate should target v0.290');
    assert_same(true, $fullGate['ready'] ?? null, 'full auto deployment gate should be ready');
    assert_same(true, $fullGate['full_auto_deployment_enabled'] ?? null, 'full auto deployment gate should enable full automation');
    assert_true(is_array($provider), 'dashboard should expose provider API deployment');
    assert_same('v0.295', $provider['target'] ?? null, 'dashboard provider API deployment should target v0.295');
    assert_same(true, $provider['ready'] ?? null, 'dashboard provider API deployment should be ready');
    assert_true(in_array('xserver_rental', $provider['profiles'] ?? [], true), 'dashboard provider API deployment should expose xserver rental');
    assert_true(in_array('xserver_vps', $provider['profiles'] ?? [], true), 'dashboard provider API deployment should expose xserver vps');
    assert_true(is_array($providerOrchestration), 'dashboard should expose provider orchestrated deployment');
    assert_same('v0.305', $providerOrchestration['target'] ?? null, 'dashboard provider orchestration should target v0.305');
    assert_same(true, $providerOrchestration['ready'] ?? null, 'dashboard provider orchestration should be ready');
    assert_same(false, $providerOrchestration['command_execution_allowed'] ?? null, 'dashboard provider orchestration should be read-only');
    assert_true(is_array($providerRuntime), 'dashboard should expose provider runtime foundation');
    assert_same('v0.311', $providerRuntime['target'] ?? null, 'dashboard provider runtime should target v0.311');
    assert_same(true, $providerRuntime['ready'] ?? null, 'dashboard provider runtime should be ready');
    assert_same(false, $providerRuntime['command_execution_allowed'] ?? null, 'dashboard provider runtime should be read-only');
    assert_true(is_array($providerRuntimeExecution), 'dashboard should expose provider runtime execution');
    assert_same('v0.320', $providerRuntimeExecution['target'] ?? null, 'dashboard provider runtime execution should target v0.320');
    assert_same(true, $providerRuntimeExecution['ready'] ?? null, 'dashboard provider runtime execution should be ready');
    assert_same(false, $providerRuntimeExecution['command_execution_allowed'] ?? null, 'dashboard provider runtime execution should be read-only');

    $html = AdlaireDashboardView::render($data);
    assert_true(str_contains($html, 'Deployment Control Matrix'), 'dashboard HTML should render control matrix section');
    assert_true(str_contains($html, 'Release Decision'), 'dashboard HTML should render release decision');
    assert_true(str_contains($html, $matrix['fingerprint']), 'dashboard HTML should render decision fingerprint');
    assert_true(str_contains($html, 'run_release_check'), 'dashboard HTML should render remediation guidance');
    assert_true(str_contains($html, 'Release Gate Inputs'), 'dashboard HTML should render release gate inputs');
    assert_true(str_contains($html, 'control-matrix-row'), 'dashboard HTML should render matrix rows');
    assert_true(str_contains($html, 'ready controls'), 'dashboard HTML should render matrix summary');
    assert_true(str_contains($html, 'Execution Gate'), 'dashboard HTML should render execution gate');
    assert_true(str_contains($html, 'Dry-run'), 'dashboard HTML should render dry-run panel');
    assert_true(str_contains($html, 'Audit Ledger'), 'dashboard HTML should render audit ledger');
    assert_true(str_contains($html, 'Decision Timeline'), 'dashboard HTML should render decision timeline');
    assert_true(str_contains($html, 'Deploy Controls'), 'dashboard HTML should render deploy controls');
    assert_true(str_contains($html, 'Deployment Queue'), 'dashboard HTML should render deployment queue');
    assert_true(str_contains($html, 'Full Auto Deployment Gate'), 'dashboard HTML should render full auto deployment gate');
    assert_true(str_contains($html, 'Provider API Deployment'), 'dashboard HTML should render provider API deployment');
    assert_true(str_contains($html, 'Provider Orchestrated Deployment'), 'dashboard HTML should render provider orchestrated deployment');
    assert_true(str_contains($html, 'Provider Runtime Foundation'), 'dashboard HTML should render provider runtime foundation');
    assert_true(str_contains($html, 'Provider Runtime Execution'), 'dashboard HTML should render provider runtime execution');
}

function test_dashboard_execution_tokens(): void
{
    $csrf = AdlaireDashboardSecurity::csrfToken();
    assert_true(is_string($csrf) && strlen($csrf) === 64, 'dashboard csrf token should be generated');
    assert_same(true, AdlaireDashboardSecurity::verifyCsrf($csrf), 'dashboard csrf token should verify');
    assert_same(false, AdlaireDashboardSecurity::verifyCsrf('invalid'), 'dashboard csrf token should reject invalid token');

    $execution = AdlaireDashboardSecurity::executionToken();
    assert_true(is_string($execution) && strlen($execution) === 64, 'dashboard execution token should be generated');
    assert_same(true, AdlaireDashboardSecurity::verifyExecutionToken($execution), 'dashboard execution token should verify once');
    assert_same(false, AdlaireDashboardSecurity::verifyExecutionToken($execution), 'dashboard execution token should be one-time');
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
    'dashboard_execution_tokens' => test_dashboard_execution_tokens(...),
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
