<?php

declare(strict_types=1);

require_once __DIR__ . '/../FrameworkCore/Core.php';
require_once __DIR__ . '/../FrameworkCore/Logger.php';
require_once __DIR__ . '/../FrameworkCore/Database.php';
require_once __DIR__ . '/../DeploymentCore.php';

final class DebugTestFailure extends RuntimeException
{
}

final class DebugRouteHit extends RuntimeException
{
    public function __construct(public array $params)
    {
        parent::__construct('route hit');
    }
}

final class DebugExtension implements AdlaireExtension
{
    public function name(): string
    {
        return 'debug';
    }

    public function register(MicroKernel $kernel): void
    {
        $kernel->set('debug.registered', true);
    }

    public function boot(MicroKernel $kernel): void
    {
        $kernel->set('debug.booted', true);
    }
}

final class DebugModule implements AutonomousModule
{
    public function id(): string
    {
        return 'debug.module';
    }

    public function responsibility(): string
    {
        return 'debug testing';
    }

    public function dependencies(): array
    {
        return ['router'];
    }

    public function handle(string $message, array $payload = []): mixed
    {
        if ($message !== 'debug.ping') {
            throw new RuntimeException('unknown message');
        }
        return ['pong' => $payload['value'] ?? null];
    }

    public function health(): array
    {
        return ['status' => 'ready', 'module' => $this->id()];
    }
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

function make_request(string $method, string $uri, array $server = []): Request
{
    $_GET = [];
    $_POST = [];
    $_FILES = [];
    $_SERVER = array_merge([
        'REQUEST_METHOD' => $method,
        'REQUEST_URI' => $uri,
        'REMOTE_ADDR' => '127.0.0.1',
    ], $server);

    return new Request();
}

function test_request_helpers(): void
{
    Request::setTrustedProxies([]);
    $request = make_request('GET', '/users/42?active=1', [
        'HTTP_AUTHORIZATION' => 'Bearer token-123',
        'HTTP_X_FORWARDED_FOR' => '203.0.113.10, 10.0.0.1',
    ]);

    assert_same('/users/42', $request->uri(), 'request URI should normalize path and drop query');
    assert_same('token-123', $request->bearerToken(), 'bearer token should parse authorization header');
    assert_same('127.0.0.1', $request->ip(), 'untrusted proxy should not override remote address');

    Request::setTrustedProxies(['127.0.0.1']);
    $trusted = make_request('GET', '/users/42?active=1', [
        'HTTP_X_FORWARDED_FOR' => '203.0.113.10, 10.0.0.1',
    ]);
    assert_same('203.0.113.10', $trusted->ip(), 'trusted proxy should use forwarded first IP');
    Request::setTrustedProxies([]);

    $_GET = ['name' => 'adlaire', 'token' => 'hidden', 'active' => 'yes', 'page' => '2'];
    $_SERVER = [
        'REQUEST_METHOD' => 'GET',
        'REQUEST_URI' => '/query',
        'REMOTE_ADDR' => '127.0.0.1',
    ];
    $queryRequest = new Request();
    assert_same(['name' => 'adlaire', 'token' => 'hidden', 'active' => 'yes', 'page' => '2'], $queryRequest->all(), 'request all should include query data');
    assert_same(['name' => 'adlaire'], $queryRequest->only(['name']), 'request only should select keys');
    assert_same(['name' => 'adlaire', 'active' => 'yes', 'page' => '2'], $queryRequest->except(['token']), 'request except should remove keys');
    assert_same('adlaire', $queryRequest->string('name'), 'request string helper should read scalar input');
    assert_same(2, $queryRequest->integer('page'), 'request integer helper should cast numeric input');
    assert_same(true, $queryRequest->boolean('active'), 'request boolean helper should cast truthy input');
    $_GET = [];
}

function test_validator(): void
{
    $validator = new Validator();
    assert_true(
        !$validator->validate(['id' => '123'], ['id' => 'strict_int']),
        'strict_int should reject numeric strings'
    );

    $validator = new Validator();
    $valid = $validator->validate(
        [
            'status' => 'published',
            'email' => 'bad',
            'items' => [
                ['name' => 'ok'],
                ['name' => ''],
            ],
        ],
        [
            'title' => 'required_if:status,published',
            'email' => 'nullable|email',
            'items.*.name' => 'required|min:2',
            'score' => [
                'nullable',
                static fn(mixed $value): bool|string => $value === null || $value >= 10 ? true : 'score too low',
            ],
        ],
        [
            'items.*.name.required' => 'item name required',
        ]
    );

    assert_true($valid === false, 'validator should fail invalid payload');
    assert_true(isset($validator->errors()['title']), 'required_if should fail when condition matches');
    assert_true(isset($validator->errors()['email']), 'email should fail invalid address');
    assert_same('item name required', $validator->errors()['items.1.name'][0], 'wildcard custom message should apply');

    $validator = new Validator();
    $valid = $validator->validate(
        [
            'status' => 'draft',
            'email' => null,
            'items' => [['name' => 'book']],
            'site' => 'https://example.com',
            'id' => '550e8400-e29b-41d4-a716-446655440000',
            'published_at' => '2026-06-08',
            'role' => 'admin',
        ],
        [
            'title' => 'required_if:status,published',
            'email' => 'nullable|email',
            'items.*.name' => 'required|min:2',
            'site' => 'url',
            'id' => 'uuid',
            'published_at' => 'date',
            'role' => 'in:admin,user',
        ]
    );

    assert_true($valid, 'validator should pass valid payload');

    $validator = new Validator();
    $valid = $validator->validate(
        [
            'password' => 'secret',
            'password_confirmation' => 'secret',
            'email' => 'a@example.com',
            'backup_email' => 'b@example.com',
        ],
        [
            'password' => 'confirmed',
            'backup_email' => 'different:email',
            'email' => 'same:email',
        ]
    );
    assert_true($valid, 'validator should pass same/different/confirmed rules');

    $validator = new Validator();
    assert_true(
        !$validator->validate(['password' => 'secret', 'password_confirmation' => 'mismatch'], ['password' => 'confirmed']),
        'confirmed should fail mismatched confirmation'
    );
}

function test_core_config(): void
{
    Adlaire::init([
        'app' => [
            'name' => 'adlaire',
        ],
        'trustedProxies' => [],
    ]);
    assert_same('adlaire', Adlaire::config('app.name'), 'config should read nested value with dot notation');
    assert_same('fallback', Adlaire::config('missing.value', 'fallback'), 'config should return default for missing value');

    putenv('ADLAIRE_DEBUG=true');
    putenv('ADLAIRE_PORT=8080');
    assert_same(true, Adlaire::env('ADLAIRE_DEBUG'), 'env should cast true string');
    assert_same(8080, Adlaire::env('ADLAIRE_PORT'), 'env should cast integer string');
    putenv('ADLAIRE_DEBUG');
    putenv('ADLAIRE_PORT');
}

function test_adlaire_audit(): void
{
    assert_same('v0.266', Adlaire::version(), 'Adlaire version should follow cumulative v0.x release format');
    $audit = Adlaire::audit();
    assert_same('v0.266', $audit['version'] ?? null, 'audit should include version');
    assert_same('>=8.3', $audit['php'] ?? null, 'audit should include PHP requirement');
    assert_same('v0.x', $audit['version_format'] ?? null, 'audit should include cumulative version format');
    assert_same(true, $audit['cumulative_version'] ?? null, 'audit should mark cumulative versions');
    assert_same('v0.266', $audit['formalization_version'] ?? null, 'audit should include formalization version');
    assert_same('10 files', $audit['file_principle'] ?? null, 'audit should include 10-file principle');
    assert_same('php -d phar.readonly=0 tests/debug.php', $audit['official_debug_test'] ?? null, 'audit should include official debug test command');

    $specificationIds = Adlaire::specificationIds();
    assert_true(isset($specificationIds['FrameworkCore/Core.php']['CORE-REQ-001']), 'specification IDs should include core requirement');
    assert_true(isset($specificationIds['FrameworkCore/Kernel.php']['KERNEL-REQ-001']), 'specification IDs should include kernel requirement');
    assert_true(isset($specificationIds['FrameworkCore/Database.php']['DB-REQ-001']), 'specification IDs should include database requirement');
    assert_true(isset($specificationIds['FrameworkCore/Database.php']['DB-REQ-003']), 'specification IDs should include database hardening requirement');
    assert_true(isset($specificationIds['FrameworkCore/Logger.php']['LOGGER-REQ-001']), 'specification IDs should include logger requirement');
    assert_true(isset($specificationIds['DeploymentCore.php']['DEPLOY-REQ-001']), 'specification IDs should include deployer requirement');
    assert_true(isset($specificationIds['Release']['RELEASE-REQ-001']), 'specification IDs should include release requirement');
    assert_same($specificationIds, $audit['specification_ids'] ?? null, 'audit should include specification IDs');

    $testSpecificationMap = Adlaire::testSpecificationMap();
    assert_true(in_array('CORE-REQ-002', $testSpecificationMap['adlaire_audit'] ?? [], true), 'test map should connect audit test to core spec');
    assert_true(in_array('RELEASE-REQ-002', $testSpecificationMap['adlaire_audit'] ?? [], true), 'test map should connect audit test to release gate');
    assert_true(in_array('KERNEL-REQ-001', $testSpecificationMap['microkernel'] ?? [], true), 'test map should connect microkernel test to kernel spec');
    assert_true(in_array('RELEASE-REQ-011', $testSpecificationMap['production_equivalent_environment'] ?? [], true), 'test map should connect production-equivalent test to Xserver spec');
    assert_true(in_array('DB-REQ-003', $testSpecificationMap['database_runtime_hardening_policy'] ?? [], true), 'test map should connect database hardening test to database spec');
    assert_true(in_array('CORE-REQ-003', $testSpecificationMap['runtime_operations_hardening'] ?? [], true), 'test map should connect runtime operations test to core spec');
    assert_true(in_array('CORE-REQ-004', $testSpecificationMap['operations_dashboard'] ?? [], true), 'test map should connect dashboard test to core spec');
    assert_same($testSpecificationMap, $audit['test_specification_map'] ?? null, 'audit should include test specification map');
    assert_true(in_array('official_debug_test', $audit['required_verifications'] ?? [], true), 'audit should require official debug test');
    assert_true(in_array('xserver_profile_audit', $audit['required_verifications'] ?? [], true), 'audit should require Xserver profile audit');
    assert_same(true, $audit['change_policy']['breaking_changes_allowed'] ?? null, 'audit should allow breaking changes');
    assert_same(false, $audit['change_policy']['compatibility_required'] ?? null, 'audit should not require compatibility');
    assert_same(false, $audit['change_policy']['deployment_system_breaking_changes_allowed'] ?? null, 'audit should forbid deployment system breaking changes');
    assert_same(true, $audit['change_policy']['deployment_system_compatibility_required'] ?? null, 'audit should require deployment system compatibility');
}

function test_license_governance(): void
{
    $license = Adlaire::licensePolicy();
    assert_same(true, $license['open_source'] ?? null, 'license policy should mark project as open source');
    assert_same('open source license', $license['default_license'] ?? null, 'license policy should mark default license as open source');
    assert_same('open source license', $license['commercial_use_license'] ?? null, 'license policy should mark commercial use as open source license');
    assert_same('commercial use license', $license['redistribution_license'] ?? null, 'license policy should mark redistribution as commercial license');
    assert_same('commercial use license', $license['modification_license'] ?? null, 'license policy should mark modification as commercial license');
    assert_same(true, $license['dual_license_model'] ?? null, 'license policy should mark dual license model');

    $prohibited = Adlaire::prohibitedUsePolicy();
    assert_same('prohibited', $prohibited['cloud_business_use'] ?? null, 'prohibited use policy should prohibit cloud business use');
    assert_same(
        ['open source license', 'commercial use license'],
        $prohibited['cloud_business_prohibition_applies_to'] ?? null,
        'prohibited use policy should prohibit cloud business use under both licenses'
    );
    assert_same(false, $prohibited['license_exception'] ?? null, 'prohibited use policy should not allow license exceptions');

    $governance = Adlaire::governancePolicy();
    assert_same(false, $governance['open_contribution'] ?? null, 'governance policy should reject open contribution');
    assert_same('approved maintainers only', $governance['development_participation'] ?? null, 'governance policy should limit development participation');
    assert_same('approval required', $governance['specification_changes'] ?? null, 'governance policy should require approval for specification changes');
    assert_same(false, $governance['external_patch_adoption_guaranteed'] ?? null, 'governance policy should not guarantee external patch adoption');
    assert_same(['specification', 'implementation_plan', 'implementation'], $governance['development_workflow_order'] ?? null, 'governance policy should define development workflow order');
    assert_same(false, $governance['implementation_without_specification_allowed'] ?? null, 'governance policy should forbid implementation without specification');
    assert_same(false, $governance['implementation_without_plan_allowed'] ?? null, 'governance policy should forbid implementation without plan');

    $workflow = Adlaire::developmentWorkflowPolicy();
    assert_same('v0.266', $workflow['version'] ?? null, 'development workflow policy should include version');
    assert_same('Specification-First Development Workflow', $workflow['theme'] ?? null, 'development workflow policy should define theme');
    assert_same(true, $workflow['highest_absolute_principle'] ?? null, 'development workflow should be highest absolute principle');
    assert_same(['specification', 'implementation_plan', 'implementation'], $workflow['required_order'] ?? null, 'development workflow should define required order');
    assert_same(true, $workflow['specification_required_before_plan'] ?? null, 'development workflow should require specification before plan');
    assert_same(true, $workflow['plan_required_before_implementation'] ?? null, 'development workflow should require plan before implementation');
    assert_same(false, $workflow['implementation_without_specification_allowed'] ?? null, 'development workflow should forbid implementation without specification');
    assert_same(false, $workflow['implementation_without_plan_allowed'] ?? null, 'development workflow should forbid implementation without plan');
    assert_true(in_array('documentation_update', $workflow['applies_to'] ?? [], true), 'development workflow should apply to documentation updates');
    assert_same(true, $workflow['repository_wide'] ?? null, 'development workflow should apply repository-wide');
    foreach (['DeploymentCore.php', 'FrameworkCore', 'public_html', 'scripts', 'tests', 'storage', 'Dockerfile.xserver', 'docker-compose.xserver.yml', 'adlaire-ecosystem.md'] as $scope) {
        assert_true(in_array($scope, $workflow['repository_scope'] ?? [], true), "development workflow should include repository scope: {$scope}");
    }
    assert_same([], $workflow['exempt_paths'] ?? null, 'development workflow should define no exempt paths');
    assert_true(in_array('repository_scope_documented', $workflow['required_verifications'] ?? [], true), 'development workflow should require repository scope documentation');

    $officialRelease = Adlaire::officialReleasePolicy();
    assert_same(true, $officialRelease['specification_compliance'] ?? null, 'official release policy should require specification compliance');
    assert_same(true, $officialRelease['official_debug_test_required'] ?? null, 'official release policy should require debug test');
    assert_same(true, $officialRelease['approved_maintainer_release_required'] ?? null, 'official release policy should require approved maintainer release');

    $audit = Adlaire::audit();
    assert_same($license, $audit['license_policy'] ?? null, 'audit should include license policy');
    assert_same($prohibited, $audit['prohibited_use_policy'] ?? null, 'audit should include prohibited use policy');
    assert_same($governance, $audit['governance_policy'] ?? null, 'audit should include governance policy');
    assert_same($workflow, $audit['development_workflow_policy'] ?? null, 'audit should include development workflow policy');
    assert_same($officialRelease, $audit['official_release_policy'] ?? null, 'audit should include official release policy');
}

function test_release_readiness(): void
{
    $audit = Adlaire::audit();
    assert_same('deployment system', $audit['design_philosophy']['framework_axis'] ?? null, 'audit should mark deployment system as framework axis');
    assert_same('distributed autonomous system design philosophy', $audit['design_philosophy']['deployment_system'] ?? null, 'audit should apply distributed autonomous philosophy to deployment system');
    assert_same('deployment system only', $audit['design_philosophy']['distributed_autonomous_scope'] ?? null, 'audit should limit distributed autonomous philosophy to deployment system');
    assert_same('specification-defined general purpose framework architecture', $audit['design_philosophy']['framework_architecture'] ?? null, 'audit should define framework architecture from specification');
    assert_same('general purpose within documented constraints', $audit['design_philosophy']['general_framework'] ?? null, 'audit should keep non-deployment components general purpose');
    assert_same('specification-defined framework module architecture', $audit['design_philosophy']['modules'] ?? null, 'audit should define module architecture from specification');
    assert_same(false, $audit['design_philosophy']['architecture_changed'] ?? null, 'audit should keep current architecture unchanged');
    assert_same(true, $audit['design_philosophy']['composite_framework'] ?? null, 'audit should include composite framework design');
    assert_same(true, $audit['design_philosophy']['standalone_framework_usage'] ?? null, 'audit should include standalone usage design');

    $requirements = Adlaire::releaseRequirementMatrix();
    assert_same('>=8.3', $requirements['php']['requirement'] ?? null, 'release requirement matrix should include PHP requirement');
    assert_same('v0.11', $requirements['formalization']['baseline'] ?? null, 'release requirement matrix should include formalization baseline');
    foreach ($requirements as $name => $entry) {
        assert_same(true, $entry['passed'] ?? null, "release requirement should pass: {$name}");
    }
    assert_same($requirements, $audit['release_requirement_matrix'] ?? null, 'audit should include release requirement matrix');

    $readiness = Adlaire::releaseReadiness();
    assert_same('v0.266', $readiness['version'] ?? null, 'release readiness should include current version');
    assert_same(true, $readiness['ready'] ?? null, 'release readiness should be ready when all checks pass');
    foreach ($readiness['checks'] ?? [] as $name => $passed) {
        assert_same(true, $passed, "release readiness check should pass: {$name}");
    }
}

function test_deployment_axis_policy(): void
{
    $policy = Adlaire::deploymentAxisPolicy();
    assert_same('v0.266', $policy['version'] ?? null, 'deployment axis policy should include version');
    assert_same('deployment system', $policy['framework_axis'] ?? null, 'deployment axis policy should set deployment system as axis');
    assert_same(false, $policy['architecture_changed'] ?? null, 'deployment axis policy should not change architecture');
    assert_same(
        'distributed autonomous system design philosophy',
        $policy['deployment_system']['design_philosophy'] ?? null,
        'deployment axis policy should apply distributed autonomous design to deployment system'
    );
    assert_same('Deployment Core', $policy['deployment_system']['core_name'] ?? null, 'deployment axis policy should define Deployment Core');
    assert_same(null, $policy['deployment_system']['core_directory'] ?? null, 'deployment axis policy should not define DeploymentCore directory');
    assert_same(false, $policy['deployment_system']['directory_required'] ?? null, 'deployment axis policy should not require Deployment Core directory');
    assert_same('root', $policy['deployment_system']['placement'] ?? null, 'deployment axis policy should place Deployment Core at root');
    assert_same('single file', $policy['deployment_system']['file_principle'] ?? null, 'deployment axis policy should define Deployment Core as single file');
    assert_same('DeploymentCore.php', $policy['deployment_system']['core_file'] ?? null, 'deployment axis policy should define DeploymentCore.php as Deployment Core file');
    assert_same(['DeploymentCore.php'], $policy['deployment_system']['components'] ?? null, 'deployment axis policy should aggregate deployer into Deployment Core');
    assert_same('DeploymentCore.php', $policy['deployment_system']['primary_component'] ?? null, 'deployment axis policy should identify DeploymentCore.php as primary component');
    assert_same(true, $policy['deployment_system']['autonomous_operation_required'] ?? null, 'deployment axis policy should require autonomous deployment operation');
    assert_same(true, $policy['deployment_system']['manifest_required'] ?? null, 'deployment axis policy should require deployer manifest');
    assert_same(true, $policy['deployment_system']['readiness_required'] ?? null, 'deployment axis policy should require deployer readiness');
    assert_same(true, $policy['deployment_system']['auris_integration_considered'] ?? null, 'deployment axis policy should consider Auris integration');
    assert_same('general purpose within documented constraints', $policy['general_framework']['policy'] ?? null, 'deployment axis policy should keep other components general purpose');
    assert_same('Framework Core', $policy['general_framework']['core_name'] ?? null, 'deployment axis policy should define Framework Core');
    assert_same('FrameworkCore', $policy['general_framework']['core_directory'] ?? null, 'deployment axis policy should define FrameworkCore directory');
    assert_same($policy['general_framework']['scope'] ?? null, $policy['general_framework']['aggregated_components'] ?? null, 'deployment axis policy should aggregate general framework components into Framework Core');
    assert_same('specification-defined general purpose framework architecture', $policy['general_framework']['design_philosophy'] ?? null, 'deployment axis policy should define framework architecture from specification');
    assert_same(false, $policy['general_framework']['distributed_autonomous_design_applies'] ?? null, 'deployment axis policy should not apply distributed autonomous design to general framework');
    assert_same('documented specification', $policy['general_framework']['architecture_source'] ?? null, 'deployment axis policy should use documented specification as framework architecture source');
    assert_same(false, $policy['general_framework']['root_entrypoints_retained'] ?? null, 'deployment axis policy should not retain general framework root entrypoints');
    assert_same(true, $policy['general_framework']['aggregated_in_core_directory'] ?? null, 'deployment axis policy should aggregate general framework into FrameworkCore');
    assert_true(in_array('FrameworkCore/Core.php', $policy['general_framework']['scope'] ?? [], true), 'deployment axis policy should include Core.php in general framework scope');
    assert_true(in_array('FrameworkCore/Database.php', $policy['general_framework']['scope'] ?? [], true), 'deployment axis policy should include Database.php in general framework scope');
    assert_true(in_array('FrameworkCore/Config.php', $policy['general_framework']['scope'] ?? [], true), 'deployment axis policy should include Config.php in general framework scope');
    assert_same(true, $policy['general_framework']['middleware_available'] ?? null, 'deployment axis policy should include middleware capability');
    assert_same(true, $policy['general_framework']['configuration_repository_available'] ?? null, 'deployment axis policy should include configuration repository');
    assert_same(true, $policy['general_framework']['support_helpers_available'] ?? null, 'deployment axis policy should include support helpers');
    assert_same('specification-defined framework module architecture', $policy['module_policy']['design_philosophy'] ?? null, 'deployment axis policy should define module architecture from specification');
    assert_same(false, $policy['module_policy']['distributed_autonomous_design_applies'] ?? null, 'deployment axis policy should not apply distributed autonomous design to modules');
    assert_same('modules', $policy['module_policy']['base_directory'] ?? null, 'deployment axis policy should define module base directory');
    assert_same(true, $policy['module_policy']['per_module_directory_required'] ?? null, 'deployment axis policy should require per-module directories');
    assert_same('modules/{ModuleName}', $policy['module_policy']['directory_pattern'] ?? null, 'deployment axis policy should define module directory pattern');
    assert_same(['3 files', '5 files', '7 files'], $policy['module_policy']['allowed_file_principles'] ?? null, 'deployment axis policy should allow 3/5/7-file module principles');
    assert_same('3 files', $policy['module_policy']['default_file_principle'] ?? null, 'deployment axis policy should default to 3-file module principle');
    assert_same(true, $policy['module_policy']['kernel_mediated'] ?? null, 'deployment axis policy should keep modules kernel mediated');
    assert_true(in_array('modules/Auris', $policy['module_policy']['official_module_directories'] ?? [], true), 'deployment axis policy should include Auris module directory');
    assert_same(true, $policy['architecture_policy']['current_architecture_retained'] ?? null, 'deployment axis policy should retain architecture');
    assert_same('10 files', $policy['architecture_policy']['file_principle'] ?? null, 'deployment axis policy should retain 10-file principle');
    assert_same('v0.202', $policy['v0_202_target']['version'] ?? null, 'deployment axis policy should define v0.202 target');
    assert_true(in_array('DeploymentCore.php', $policy['v0_202_target']['source_code_scope'] ?? [], true), 'deployment axis target should include DeploymentCore.php');
    assert_true(in_array('FrameworkCore/Middleware.php', $policy['v0_202_target']['source_code_scope'] ?? [], true), 'deployment axis target should include Middleware.php');
    assert_same(true, $policy['v0_202_target']['deployment_system_axis_required'] ?? null, 'deployment axis target should require deployment axis');
    assert_same(true, $policy['v0_202_target']['deployer_manifest_required'] ?? null, 'deployment axis target should require deployer manifest');
    assert_same(true, $policy['v0_202_target']['deployer_readiness_required'] ?? null, 'deployment axis target should require deployer readiness');
    assert_same(true, $policy['v0_202_target']['ten_file_principle_required'] ?? null, 'deployment axis target should require 10-file principle');
    assert_same(true, $policy['v0_202_target']['general_framework_capability_required'] ?? null, 'deployment axis target should require general framework capability');
    assert_same(true, $policy['v0_202_target']['router_middleware_required'] ?? null, 'deployment axis target should require router middleware');
    assert_same(true, $policy['v0_202_target']['backend_framework_capability_required'] ?? null, 'deployment axis target should require backend framework capability');
    assert_same(true, $policy['v0_202_target']['stable_release_required'] ?? null, 'deployment axis target should require stable release');

    $audit = Adlaire::audit();
    assert_same($policy, $audit['deployment_axis_policy'] ?? null, 'audit should include deployment axis policy');

    $manifest = Adlaire::distributionManifest();
    assert_same($policy, $manifest['deployment_axis_policy'] ?? null, 'distribution manifest should include deployment axis policy');

    $readiness = Adlaire::releaseReadiness();
    assert_same(true, $readiness['checks']['deployment_axis_policy'] ?? null, 'release readiness should include deployment axis policy');
}

function test_deployment_axis_map_policy(): void
{
    $policy = Adlaire::deploymentAxisMapPolicy();
    assert_same('v0.266', $policy['version'] ?? null, 'deployment axis map should include version');
    assert_same('Deployment Axis Map', $policy['theme'] ?? null, 'deployment axis map should define theme');
    assert_same('deployment system', $policy['repository_axis'] ?? null, 'deployment axis map should define repository axis');
    assert_same(false, $policy['physical_reorganization_applied'] ?? null, 'deployment axis map should not apply physical reorganization yet');
    assert_same(true, $policy['deployment_core_compatibility_required'] ?? null, 'deployment axis map should require Deployment Core compatibility');
    assert_same(false, $policy['dashboard_execution_enabled'] ?? null, 'deployment axis map should keep dashboard execution disabled');

    assert_same(true, $policy['roles']['deployment_core']['compatibility_domain'] ?? null, 'deployment core should be compatibility domain');
    assert_same(false, $policy['roles']['deployment_core']['breaking_changes_allowed'] ?? null, 'deployment core should reject breaking changes');
    assert_true(in_array('DeploymentCore.php', $policy['roles']['deployment_core']['paths'] ?? [], true), 'deployment core role should include DeploymentCore.php');

    assert_same(true, $policy['roles']['deployment_control_ui']['read_only'] ?? null, 'deployment control UI should stay read-only');
    assert_same(false, $policy['roles']['deployment_control_ui']['command_execution_allowed'] ?? null, 'deployment control UI should not execute commands yet');
    assert_true(in_array('public_html/dashboard.php', $policy['roles']['deployment_control_ui']['paths'] ?? [], true), 'deployment control UI should include dashboard');
    assert_true(in_array('public_html/assets/adlaire-ui.css', $policy['roles']['deployment_control_ui']['paths'] ?? [], true), 'deployment control UI should include UI asset');

    assert_true(in_array('FrameworkCore', $policy['roles']['framework_support']['paths'] ?? [], true), 'framework support should include FrameworkCore');
    assert_true(in_array('tests/debug.php', $policy['roles']['verification']['paths'] ?? [], true), 'verification should include official debug test');
    assert_true(in_array('scripts', $policy['roles']['verification']['paths'] ?? [], true), 'verification should include scripts');
    assert_true(in_array('adlaire-ecosystem.md', $policy['roles']['specification']['paths'] ?? [], true), 'specification should include source of truth');
    assert_true(in_array('README.md', $policy['roles']['specification']['paths'] ?? [], true), 'specification should include README entry point');
    assert_same('adlaire-ecosystem.md', $policy['roles']['specification']['source_of_truth'] ?? null, 'specification role should identify source of truth');

    $audit = Adlaire::audit();
    assert_same($policy, $audit['deployment_axis_map_policy'] ?? null, 'audit should include deployment axis map');

    $manifest = Adlaire::distributionManifest();
    assert_same($policy, $manifest['deployment_axis_map_policy'] ?? null, 'distribution manifest should include deployment axis map');

    $contract = Adlaire::stableReleaseContract();
    assert_true(in_array('deployment axis map', $contract['backend_framework_capabilities'] ?? [], true), 'stable release contract should include deployment axis map capability');
    assert_same(true, $contract['deployment_axis_map'] ?? null, 'stable release contract should mark deployment axis map');

    assert_policy_release_connection('deployment_axis_map_policy', $policy);

    $readiness = Adlaire::releaseReadiness();
    assert_same(true, $readiness['checks']['deployment_axis_map_policy'] ?? null, 'release readiness should include deployment axis map policy');
}

function test_dashboard_deploy_execution_policy(): void
{
    $policy = Adlaire::dashboardDeployExecutionPolicy();
    assert_same('v0.266', $policy['version'] ?? null, 'dashboard deploy execution policy should include version');
    assert_same('Dashboard Deploy Execution Specification', $policy['theme'] ?? null, 'dashboard deploy execution policy should define theme');
    assert_same('specified_not_implemented', $policy['status'] ?? null, 'dashboard deploy execution should be specified but not implemented');
    assert_same(false, $policy['default_enabled'] ?? null, 'dashboard deploy execution should be disabled by default');
    assert_same(false, $policy['implementation_enabled'] ?? null, 'dashboard deploy execution should not be implemented yet');
    assert_same(false, $policy['public_api_required'] ?? null, 'dashboard deploy execution should not require public API');
    assert_same(false, $policy['configuration_files_allowed'] ?? null, 'dashboard deploy execution should not add configuration files');
    assert_same(true, $policy['deployment_core_compatibility_required'] ?? null, 'dashboard deploy execution should require Deployment Core compatibility');
    assert_same(true, $policy['dashboard_default_read_only'] ?? null, 'dashboard should remain read-only by default');
    assert_same(true, $policy['execution_exception_requires_explicit_enable'] ?? null, 'dashboard execution should require explicit enablement');

    foreach (['csrf_required', 'two_step_confirmation_required', 'short_lived_execution_token_required', 'approved_deploy_profile_required', 'preflight_required', 'plan_preview_required', 'dry_run_required_before_apply', 'rollback_preview_required', 'safety_score_required', 'audit_log_required'] as $gate) {
        assert_same(true, $policy['safety_gates'][$gate] ?? null, "dashboard deploy execution safety gate should be required: {$gate}");
    }
    assert_same(70, $policy['safety_gates']['minimum_safety_score'] ?? null, 'dashboard deploy execution should require minimum safety score');
    assert_same('Execution Safety Gate', $policy['future_phases']['v0.235'] ?? null, 'dashboard deploy execution should define next safety phase');

    $audit = Adlaire::audit();
    assert_same($policy, $audit['dashboard_deploy_execution_policy'] ?? null, 'audit should include dashboard deploy execution policy');

    $manifest = Adlaire::distributionManifest();
    assert_same($policy, $manifest['dashboard_deploy_execution_policy'] ?? null, 'distribution manifest should include dashboard deploy execution policy');

    $contract = Adlaire::stableReleaseContract();
    assert_true(in_array('dashboard deploy execution specification', $contract['backend_framework_capabilities'] ?? [], true), 'stable release contract should include dashboard deploy execution specification capability');
    assert_same(true, $contract['dashboard_deploy_execution_specification'] ?? null, 'stable release contract should mark dashboard deploy execution specification');

    assert_policy_release_connection('dashboard_deploy_execution_policy', $policy);

    $readiness = Adlaire::releaseReadiness();
    assert_same(true, $readiness['checks']['dashboard_deploy_execution_policy'] ?? null, 'release readiness should include dashboard deploy execution policy');
}

function test_execution_safety_gate_policy(): void
{
    $policy = Adlaire::executionSafetyGatePolicy();
    assert_same('v0.266', $policy['version'] ?? null, 'execution safety gate policy should include version');
    assert_same('Execution Safety Gate', $policy['theme'] ?? null, 'execution safety gate should define theme');
    assert_same('gate_defined_execution_still_disabled', $policy['status'] ?? null, 'execution safety gate should keep execution disabled');
    assert_same(false, $policy['dashboard_execution_enabled'] ?? null, 'execution safety gate should not enable dashboard execution');
    assert_same(false, $policy['public_api_required'] ?? null, 'execution safety gate should not require public API');
    assert_same(false, $policy['configuration_files_allowed'] ?? null, 'execution safety gate should not allow configuration files');
    assert_same(true, $policy['deployment_core_compatibility_required'] ?? null, 'execution safety gate should preserve Deployment Core compatibility');
    assert_same(70, $policy['minimum_safety_score'] ?? null, 'execution safety gate should enforce minimum safety score');

    foreach (['csrf_token', 'short_lived_execution_token', 'explicit_operator_confirmation', 'approved_deploy_profile', 'preflight_report', 'plan_preview', 'rollback_preview', 'safety_score'] as $input) {
        assert_true(in_array($input, $policy['required_inputs'] ?? [], true), "execution safety gate should require input: {$input}");
    }
    foreach (['dashboard_deploy_execution_policy', 'deployment_preflight_policy', 'deployment_plan_preview_policy', 'deployment_rollback_preview_policy', 'deployment_safety_score_policy', 'deployment_system_compatibility_policy'] as $source) {
        assert_true(in_array($source, $policy['required_source_policies'] ?? [], true), "execution safety gate should require source policy: {$source}");
    }
    foreach (['dashboard_execution_not_explicitly_enabled', 'preflight_failed', 'safety_score_below_minimum', 'deployment_core_compatibility_failed'] as $condition) {
        assert_true(in_array($condition, $policy['blocking_conditions'] ?? [], true), "execution safety gate should define blocking condition: {$condition}");
    }
    assert_same('connect DeploymentCore execute adapter behind the gate', $policy['implementation_plan']['v0.236'] ?? null, 'execution safety gate should define next adapter phase');

    $audit = Adlaire::audit();
    assert_same($policy, $audit['execution_safety_gate_policy'] ?? null, 'audit should include execution safety gate policy');

    $manifest = Adlaire::distributionManifest();
    assert_same($policy, $manifest['execution_safety_gate_policy'] ?? null, 'distribution manifest should include execution safety gate policy');

    $contract = Adlaire::stableReleaseContract();
    assert_true(in_array('execution safety gate', $contract['backend_framework_capabilities'] ?? [], true), 'stable release contract should include execution safety gate capability');
    assert_same(true, $contract['execution_safety_gate'] ?? null, 'stable release contract should mark execution safety gate');

    assert_policy_release_connection('execution_safety_gate_policy', $policy);

    $readiness = Adlaire::releaseReadiness();
    assert_same(true, $readiness['checks']['execution_safety_gate_policy'] ?? null, 'release readiness should include execution safety gate policy');
}

function test_deployment_execute_adapter_policy(): void
{
    $policy = Adlaire::deploymentExecuteAdapterPolicy();
    assert_same('v0.266', $policy['version'] ?? null, 'deployment execute adapter policy should include version');
    assert_same('Deployment Execute Adapter Contract', $policy['theme'] ?? null, 'deployment execute adapter should define theme');
    assert_same('adapter_contract_defined_execution_disabled', $policy['status'] ?? null, 'deployment execute adapter should keep execution disabled');
    assert_same('DeploymentCoreExecuteAdapter', $policy['adapter_name'] ?? null, 'deployment execute adapter should define adapter name');
    assert_same(false, $policy['public_api_required'] ?? null, 'deployment execute adapter should not require public API');
    assert_same(false, $policy['configuration_files_allowed'] ?? null, 'deployment execute adapter should not allow configuration files');
    assert_same(false, $policy['dashboard_execution_enabled'] ?? null, 'deployment execute adapter should not enable dashboard execution');
    assert_same(true, $policy['behind_execution_safety_gate'] ?? null, 'deployment execute adapter should sit behind execution safety gate');
    assert_same(true, $policy['deployment_core_entrypoint_unchanged'] ?? null, 'deployment execute adapter should keep DeploymentCore entrypoint unchanged');
    assert_true(in_array('dry_run', $policy['allowed_operations'] ?? [], true), 'deployment execute adapter should allow dry run');
    assert_true(in_array('apply_deploy', $policy['blocked_operations'] ?? [], true), 'deployment execute adapter should block apply deploy');
    assert_true(in_array('modify_deployment_core_contract', $policy['blocked_operations'] ?? [], true), 'deployment execute adapter should block DeploymentCore contract modification');
    assert_true(in_array('execution_safety_gate_policy', $policy['required_source_policies'] ?? [], true), 'deployment execute adapter should require execution safety gate policy');
    assert_same('freeze pre-reorganization boundary', $policy['implementation_plan']['v0.239'] ?? null, 'deployment execute adapter should define v0.239 boundary');

    $audit = Adlaire::audit();
    assert_same($policy, $audit['deployment_execute_adapter_policy'] ?? null, 'audit should include deployment execute adapter policy');
    assert_same($policy, Adlaire::distributionManifest()['deployment_execute_adapter_policy'] ?? null, 'manifest should include deployment execute adapter policy');

    $contract = Adlaire::stableReleaseContract();
    assert_true(in_array('deployment execute adapter contract', $contract['backend_framework_capabilities'] ?? [], true), 'stable release contract should include deployment execute adapter capability');
    assert_same(true, $contract['deployment_execute_adapter_contract'] ?? null, 'stable release contract should mark deployment execute adapter');
    assert_policy_release_connection('deployment_execute_adapter_policy', $policy);
    assert_same(true, Adlaire::releaseReadiness()['checks']['deployment_execute_adapter_policy'] ?? null, 'release readiness should include deployment execute adapter policy');
}

function test_execution_audit_trail_policy(): void
{
    $policy = Adlaire::executionAuditTrailPolicy();
    assert_same('v0.266', $policy['version'] ?? null, 'execution audit trail policy should include version');
    assert_same('Execution Audit Trail', $policy['theme'] ?? null, 'execution audit trail should define theme');
    assert_same('audit_trail_defined_execution_disabled', $policy['status'] ?? null, 'execution audit trail should keep execution disabled');
    assert_same(true, $policy['append_only'] ?? null, 'execution audit trail should be append only');
    assert_same(true, $policy['json_artifact_allowed'] ?? null, 'execution audit trail should allow JSON artifact');
    assert_same(false, $policy['configuration_files_allowed'] ?? null, 'execution audit trail should not allow configuration files');
    assert_same(false, $policy['public_api_required'] ?? null, 'execution audit trail should not require public API');
    assert_same(false, $policy['dashboard_execution_enabled'] ?? null, 'execution audit trail should not enable dashboard execution');
    assert_true(in_array('gate_evaluated', $policy['required_events'] ?? [], true), 'execution audit trail should include gate event');
    assert_true(in_array('execution_blocked_or_deferred', $policy['required_events'] ?? [], true), 'execution audit trail should include blocked/deferred event');
    assert_true(in_array('blocking_conditions', $policy['required_fields'] ?? [], true), 'execution audit trail should include blocking conditions field');
    assert_same(false, $policy['retention_policy']['contains_secret_values'] ?? null, 'execution audit trail should not contain secret values');
    assert_same(false, $policy['retention_policy']['stores_tokens'] ?? null, 'execution audit trail should not store tokens');
    assert_true(in_array('deployment_execute_adapter_policy', $policy['required_source_policies'] ?? [], true), 'execution audit trail should require deployment adapter policy');

    $audit = Adlaire::audit();
    assert_same($policy, $audit['execution_audit_trail_policy'] ?? null, 'audit should include execution audit trail policy');
    assert_same($policy, Adlaire::distributionManifest()['execution_audit_trail_policy'] ?? null, 'manifest should include execution audit trail policy');

    $contract = Adlaire::stableReleaseContract();
    assert_true(in_array('execution audit trail', $contract['backend_framework_capabilities'] ?? [], true), 'stable release contract should include execution audit trail capability');
    assert_same(true, $contract['execution_audit_trail'] ?? null, 'stable release contract should mark execution audit trail');
    assert_policy_release_connection('execution_audit_trail_policy', $policy);
    assert_same(true, Adlaire::releaseReadiness()['checks']['execution_audit_trail_policy'] ?? null, 'release readiness should include execution audit trail policy');
}

function test_dashboard_gated_controls_policy(): void
{
    $policy = Adlaire::dashboardGatedControlsPolicy();
    assert_same('v0.266', $policy['version'] ?? null, 'dashboard gated controls policy should include version');
    assert_same('Dashboard Gated Controls', $policy['theme'] ?? null, 'dashboard gated controls should define theme');
    assert_same('controls_defined_execution_disabled', $policy['status'] ?? null, 'dashboard gated controls should keep execution disabled');
    assert_same(false, $policy['dashboard_execution_enabled'] ?? null, 'dashboard gated controls should not enable execution');
    assert_same('disabled', $policy['default_control_state'] ?? null, 'dashboard gated controls should default to disabled');
    assert_same(false, $policy['public_api_required'] ?? null, 'dashboard gated controls should not require public API');
    assert_same(false, $policy['configuration_files_allowed'] ?? null, 'dashboard gated controls should not allow configuration files');
    assert_same(true, $policy['requires_execution_safety_gate'] ?? null, 'dashboard gated controls should require execution safety gate');
    assert_same(true, $policy['requires_two_step_confirmation'] ?? null, 'dashboard gated controls should require two step confirmation');
    assert_true(in_array('view_gate_status', $policy['visible_controls'] ?? [], true), 'dashboard gated controls should show gate status');
    assert_true(in_array('run_deploy', $policy['disabled_controls'] ?? [], true), 'dashboard gated controls should disable run deploy');
    assert_true(in_array('write_remote_state', $policy['disabled_controls'] ?? [], true), 'dashboard gated controls should disable remote state writes');
    assert_true(in_array('execution_audit_trail_policy', $policy['required_source_policies'] ?? [], true), 'dashboard gated controls should require execution audit trail policy');

    $audit = Adlaire::audit();
    assert_same($policy, $audit['dashboard_gated_controls_policy'] ?? null, 'audit should include dashboard gated controls policy');
    assert_same($policy, Adlaire::distributionManifest()['dashboard_gated_controls_policy'] ?? null, 'manifest should include dashboard gated controls policy');

    $contract = Adlaire::stableReleaseContract();
    assert_true(in_array('dashboard gated controls', $contract['backend_framework_capabilities'] ?? [], true), 'stable release contract should include dashboard gated controls capability');
    assert_same(true, $contract['dashboard_gated_controls'] ?? null, 'stable release contract should mark dashboard gated controls');
    assert_policy_release_connection('dashboard_gated_controls_policy', $policy);
    assert_same(true, Adlaire::releaseReadiness()['checks']['dashboard_gated_controls_policy'] ?? null, 'release readiness should include dashboard gated controls policy');
}

function test_reorganization_readiness_boundary_policy(): void
{
    $policy = Adlaire::reorganizationReadinessBoundaryPolicy();
    assert_same('v0.266', $policy['version'] ?? null, 'reorganization readiness boundary policy should include version');
    assert_same('Reorganization Readiness Boundary', $policy['theme'] ?? null, 'reorganization readiness boundary should define theme');
    assert_same('pre_reorganization_boundary_fixed', $policy['status'] ?? null, 'reorganization readiness boundary should fix pre-reorganization boundary');
    assert_same('v0.240', $policy['approval_required_from_version'] ?? null, 'reorganization readiness boundary should require approval from v0.240');
    assert_same(true, $policy['current_version_requires_approval'] ?? null, 'v0.266 changes should remain approval-gated');
    assert_same(false, $policy['physical_reorganization_applied'] ?? null, 'reorganization readiness boundary should not apply physical reorganization');
    assert_same(false, $policy['public_api_required'] ?? null, 'reorganization readiness boundary should not require public API');
    assert_same(false, $policy['configuration_files_allowed'] ?? null, 'reorganization readiness boundary should not allow configuration files');
    assert_same(true, $policy['deployment_framework_compatibility_required'] ?? null, 'reorganization readiness boundary should preserve Deployment Framework compatibility');
    assert_true(in_array('physical_directory_reorganization', $policy['protected_until_approval'] ?? [], true), 'reorganization readiness boundary should protect physical reorganization');
    assert_true(in_array('DeploymentCore contract change', $policy['protected_until_approval'] ?? [], true), 'reorganization readiness boundary should protect DeploymentCore contract');
    assert_true(in_array('dashboard_gated_controls_policy', $policy['ready_inputs'] ?? [], true), 'reorganization readiness boundary should include dashboard gated controls input');
    assert_same(true, $policy['v0_240_requires_explicit_change_presentation'] ?? null, 'v0.240+ should require explicit change presentation');
    assert_same(true, $policy['v0_240_requires_user_approval'] ?? null, 'v0.240+ should require user approval');
    assert_same('v0.270 reorganized framework stable release', $policy['release_target'] ?? null, 'reorganization readiness boundary should retain v0.270 target');

    $audit = Adlaire::audit();
    assert_same($policy, $audit['reorganization_readiness_boundary_policy'] ?? null, 'audit should include reorganization readiness boundary policy');
    assert_same($policy, Adlaire::distributionManifest()['reorganization_readiness_boundary_policy'] ?? null, 'manifest should include reorganization readiness boundary policy');

    $contract = Adlaire::stableReleaseContract();
    assert_true(in_array('reorganization readiness boundary', $contract['backend_framework_capabilities'] ?? [], true), 'stable release contract should include reorganization readiness boundary capability');
    assert_same(true, $contract['reorganization_readiness_boundary'] ?? null, 'stable release contract should mark reorganization readiness boundary');
    assert_policy_release_connection('reorganization_readiness_boundary_policy', $policy);
    assert_same(true, Adlaire::releaseReadiness()['checks']['reorganization_readiness_boundary_policy'] ?? null, 'release readiness should include reorganization readiness boundary policy');
}

function test_reorganization_architecture_plan_policy(): void
{
    $policy = Adlaire::reorganizationArchitecturePlanPolicy();
    assert_same('v0.266', $policy['version'] ?? null, 'reorganization architecture plan policy should include version');
    assert_same('Reorganization Architecture Plan', $policy['theme'] ?? null, 'reorganization architecture plan should define theme');
    assert_same('architecture_plan_defined_no_physical_movement', $policy['status'] ?? null, 'reorganization architecture plan should not apply physical movement');
    assert_same(true, $policy['approval_obtained'] ?? null, 'reorganization architecture plan should mark approval obtained');
    assert_same(false, $policy['physical_reorganization_applied'] ?? null, 'reorganization architecture plan should not apply physical reorganization');
    assert_same(false, $policy['public_api_required'] ?? null, 'reorganization architecture plan should not require public API');
    assert_same(false, $policy['configuration_files_allowed'] ?? null, 'reorganization architecture plan should not allow configuration files');
    assert_same(true, $policy['deployment_framework_compatibility_required'] ?? null, 'reorganization architecture plan should preserve Deployment Framework compatibility');
    assert_same(false, $policy['deployment_core_contract_change_allowed'] ?? null, 'reorganization architecture plan should prohibit DeploymentCore contract changes');
    assert_same('v0.270', $policy['target_version'] ?? null, 'reorganization architecture plan should target v0.270');
    assert_same('v0.270 reorganized framework stable release', $policy['stable_release_target'] ?? null, 'reorganization architecture plan should retain stable release target');
    assert_same(true, $policy['target_architecture']['deployment_framework']['compatibility_domain'] ?? null, 'target deployment framework should remain compatibility domain');
    assert_same(false, $policy['target_architecture']['deployment_framework']['contract_breaking_changes_allowed'] ?? null, 'target deployment framework should prohibit contract breaking changes');
    assert_same('planned', $policy['target_architecture']['javascript_framework']['implementation_status'] ?? null, 'target JavaScript framework should remain planned');
    assert_true(in_array('DeploymentCore.php', $policy['target_architecture']['deployment_framework']['current_paths'] ?? [], true), 'target deployment framework should include current DeploymentCore');
    assert_true(in_array('public_html/assets/adlaire-ui.css', $policy['target_architecture']['css_framework']['current_paths'] ?? [], true), 'target CSS framework should include current UI asset');
    assert_same('define approved target architecture', $policy['migration_sequence']['v0.240'] ?? null, 'reorganization architecture plan should define v0.240 migration step');
    assert_true(in_array('physical file movement', $policy['prohibited_in_this_release'] ?? [], true), 'reorganization architecture plan should prohibit physical file movement');
    assert_true(in_array('DeploymentCore contract change', $policy['prohibited_in_this_release'] ?? [], true), 'reorganization architecture plan should prohibit DeploymentCore contract change');
    assert_true(in_array('reorganization_readiness_boundary_policy', $policy['required_source_policies'] ?? [], true), 'reorganization architecture plan should require readiness boundary');

    $audit = Adlaire::audit();
    assert_same($policy, $audit['reorganization_architecture_plan_policy'] ?? null, 'audit should include reorganization architecture plan policy');
    assert_same($policy, Adlaire::distributionManifest()['reorganization_architecture_plan_policy'] ?? null, 'manifest should include reorganization architecture plan policy');

    $contract = Adlaire::stableReleaseContract();
    assert_true(in_array('reorganization architecture plan', $contract['backend_framework_capabilities'] ?? [], true), 'stable release contract should include reorganization architecture plan capability');
    assert_same(true, $contract['reorganization_architecture_plan'] ?? null, 'stable release contract should mark reorganization architecture plan');
    assert_policy_release_connection('reorganization_architecture_plan_policy', $policy);
    assert_same(true, Adlaire::releaseReadiness()['checks']['reorganization_architecture_plan_policy'] ?? null, 'release readiness should include reorganization architecture plan policy');
}

function test_reorganization_preparation_plan_policy(): void
{
    $policy = Adlaire::reorganizationPreparationPlanPolicy();
    assert_same('v0.266', $policy['version'] ?? null, 'reorganization preparation plan policy should include version');
    assert_same('Non-Deployment Migration Preparation Plan', $policy['theme'] ?? null, 'reorganization preparation plan should define theme');
    assert_same('pre_integration_core_wiring_gate_defined_no_physical_movement', $policy['status'] ?? null, 'reorganization preparation plan should define readiness without movement');
    assert_same('v0.251-v0.260', $policy['range'] ?? null, 'reorganization preparation plan should cover v0.251-v0.260');
    assert_same(false, $policy['physical_reorganization_applied'] ?? null, 'reorganization preparation plan should not apply physical reorganization');
    assert_same(false, $policy['public_api_required'] ?? null, 'reorganization preparation plan should not require public API');
    assert_same(false, $policy['configuration_files_allowed'] ?? null, 'reorganization preparation plan should not allow configuration files');
    assert_same(false, $policy['deployment_core_contract_change_allowed'] ?? null, 'reorganization preparation plan should not allow DeploymentCore contract changes');
    assert_same(false, $policy['dashboard_execution_enabled'] ?? null, 'reorganization preparation plan should not enable dashboard execution');
    assert_true(in_array('backend_migration_unit', $policy['migration_units'] ?? [], true), 'migration units should include backend');
    assert_true(in_array('javascript_framework_bootstrap_unit', $policy['migration_units'] ?? [], true), 'migration units should include JavaScript bootstrap');
    assert_true(in_array('DeploymentCore.php remains canonical permanently for compatibility', $policy['compatibility_shims'] ?? [], true), 'compatibility shims should preserve DeploymentCore');
    assert_same('required', $policy['contract_validation_matrix']['release_readiness'] ?? null, 'contract validation matrix should require release readiness');
    assert_same('Frameworks/Deployment', $policy['directory_map']['DeploymentCore.php'] ?? null, 'directory map should map DeploymentCore');
    assert_same('Core', $policy['directory_map']['FrameworkCore/Core.php'] ?? null, 'directory map should map Framework Core');
    assert_same('Frameworks/CSS', $policy['directory_map']['public_html/assets/adlaire-ui.css'] ?? null, 'directory map should map CSS asset');
    assert_same('Adlaire\\Core', $policy['namespace_plan']['Core'] ?? null, 'namespace plan should define Core namespace');
    assert_same('Adlaire\\Frameworks\\Deployment', $policy['namespace_plan']['Deployment Framework'] ?? null, 'namespace plan should define Deployment namespace');
    assert_true(in_array('Deployment Framework must not depend on Frontend Framework', $policy['dependency_boundary'] ?? [], true), 'dependency boundary should protect Deployment Framework');
    assert_true(in_array('execution_safety_gate', $policy['internal_contracts'] ?? [], true), 'internal contracts should include execution safety gate');
    assert_same(true, $policy['dashboard_control_boundary']['run_deploy_disabled'] ?? null, 'dashboard control boundary should disable run deploy');
    assert_same(true, $policy['dashboard_control_boundary']['remote_state_write_disabled'] ?? null, 'dashboard control boundary should disable remote state writes');
    assert_same(true, $policy['pre_migration_readiness_gate']['directory_map_defined'] ?? null, 'pre-migration gate should require directory map');
    assert_same(false, $policy['pre_migration_readiness_gate']['physical_movement_allowed'] ?? null, 'pre-migration gate should prohibit physical movement');
    assert_same(true, $policy['pre_migration_readiness_gate']['ready_for_v0_261_integration_core_wiring'] ?? null, 'pre-migration gate should allow v0.261 Integration Core wiring');
    assert_same(true, $policy['risk_gate']['non_deployment_only'] ?? null, 'risk gate should limit migration to non-deployment code');
    assert_same('blocked', $policy['risk_gate']['deployment_core_contract_risk'] ?? null, 'risk gate should block DeploymentCore contract risk');
    assert_true(in_array('reorganization_architecture_plan_policy', $policy['required_source_policies'] ?? [], true), 'preparation plan should require architecture plan policy');

    $audit = Adlaire::audit();
    assert_same($policy, $audit['reorganization_preparation_plan_policy'] ?? null, 'audit should include reorganization preparation plan policy');
    assert_same($policy, Adlaire::distributionManifest()['reorganization_preparation_plan_policy'] ?? null, 'manifest should include reorganization preparation plan policy');

    $contract = Adlaire::stableReleaseContract();
    assert_true(in_array('non-deployment migration preparation plan', $contract['backend_framework_capabilities'] ?? [], true), 'stable release contract should include reorganization preparation plan capability');
    assert_same(true, $contract['reorganization_preparation_plan'] ?? null, 'stable release contract should mark reorganization preparation plan');
    assert_policy_release_connection('reorganization_preparation_plan_policy', $policy);
    assert_same(true, Adlaire::releaseReadiness()['checks']['reorganization_preparation_plan_policy'] ?? null, 'release readiness should include reorganization preparation plan policy');
}

function test_physical_reorganization_phase_one_policy(): void
{
    $policy = Adlaire::physicalReorganizationPhaseOnePolicy();
    assert_same('v0.266', $policy['version'] ?? null, 'physical reorganization phase one policy should include version');
    assert_same('Physical Reorganization Phase One', $policy['theme'] ?? null, 'physical reorganization phase one should define theme');
    assert_same('core_backend_moved_with_compatibility_shims', $policy['status'] ?? null, 'physical reorganization phase one should define status');
    assert_same('v0.261-v0.263', $policy['range'] ?? null, 'physical reorganization phase one should cover v0.261-v0.263');
    assert_same(true, $policy['approval_obtained'] ?? null, 'physical reorganization phase one should record approval');
    assert_same(true, $policy['physical_reorganization_applied'] ?? null, 'physical reorganization phase one should apply physical reorganization');
    assert_same(true, $policy['deployment_core_root_retained'] ?? null, 'physical reorganization phase one should retain DeploymentCore root');
    assert_same(false, $policy['deployment_core_contract_changed'] ?? null, 'physical reorganization phase one should not change DeploymentCore contract');
    assert_same(false, $policy['public_api_required'] ?? null, 'physical reorganization phase one should not require public API');
    assert_same(false, $policy['configuration_files_allowed'] ?? null, 'physical reorganization phase one should not allow configuration files');
    assert_same(false, $policy['dashboard_execution_enabled'] ?? null, 'physical reorganization phase one should not enable dashboard execution');
    assert_same('Core/Core.php', $policy['moved_paths']['FrameworkCore/Core.php'] ?? null, 'Core.php should move to Core');
    assert_same('Frameworks/Backend/Database.php', $policy['moved_paths']['FrameworkCore/Database.php'] ?? null, 'Database.php should move to Backend framework');
    assert_true(in_array('FrameworkCore/Core.php', $policy['compatibility_shims'] ?? [], true), 'compatibility shims should retain FrameworkCore/Core.php');
    assert_true(in_array('Frameworks/JavaScript', $policy['created_directories'] ?? [], true), 'created directories should include JavaScript framework');
    assert_true(in_array('DeploymentCore.php', $policy['preserved_entrypoints'] ?? [], true), 'preserved entrypoints should include DeploymentCore');
    assert_true(in_array('reorganization_preparation_plan_policy', $policy['required_source_policies'] ?? [], true), 'physical reorganization should require preparation plan');
    assert_true(in_array('frameworkcore_shims_exist', $policy['required_verifications'] ?? [], true), 'physical reorganization should require shim verification');
    foreach (['Core/Core.php', 'Core/Kernel.php', 'Core/Extension.php', 'Frameworks/Backend/Database.php', 'Frameworks/Backend/Config.php', 'Frameworks/Backend/Logger.php', 'Frameworks/Backend/Middleware.php', 'Frameworks/Backend/Support.php', 'FrameworkCore/Core.php', 'FrameworkCore/Database.php', 'DeploymentCore.php'] as $file) {
        assert_true(is_file(__DIR__ . '/../' . $file), "physical reorganization file should exist: {$file}");
    }

    $audit = Adlaire::audit();
    assert_same($policy, $audit['physical_reorganization_phase_one_policy'] ?? null, 'audit should include physical reorganization phase one policy');
    assert_same($policy, Adlaire::distributionManifest()['physical_reorganization_phase_one_policy'] ?? null, 'manifest should include physical reorganization phase one policy');

    $contract = Adlaire::stableReleaseContract();
    assert_true(in_array('physical reorganization phase one', $contract['backend_framework_capabilities'] ?? [], true), 'stable release contract should include physical reorganization capability');
    assert_same(true, $contract['physical_reorganization_phase_one'] ?? null, 'stable release contract should mark physical reorganization phase one');
    assert_policy_release_connection('physical_reorganization_phase_one_policy', $policy);
    assert_same(true, Adlaire::releaseReadiness()['checks']['physical_reorganization_phase_one_policy'] ?? null, 'release readiness should include physical reorganization phase one policy');
}

function test_frontend_reorganization_shim_policy(): void
{
    $policy = Adlaire::frontendReorganizationShimPolicy();
    assert_same('v0.266', $policy['version'] ?? null, 'frontend reorganization shim policy should include version');
    assert_same('Frontend Reorganization Shim', $policy['theme'] ?? null, 'frontend reorganization shim should define theme');
    assert_same('frontend_php_bodies_moved_public_html_shims_retained', $policy['status'] ?? null, 'frontend reorganization shim should define status');
    assert_same('v0.264', $policy['range'] ?? null, 'frontend reorganization shim should cover v0.264');
    assert_same(true, $policy['physical_reorganization_applied'] ?? null, 'frontend reorganization shim should apply physical reorganization');
    assert_same(false, $policy['deployment_core_contract_changed'] ?? null, 'frontend reorganization shim should not change DeploymentCore contract');
    assert_same(false, $policy['public_api_required'] ?? null, 'frontend reorganization shim should not require public API');
    assert_same(false, $policy['configuration_files_allowed'] ?? null, 'frontend reorganization shim should not allow configuration files');
    assert_same(false, $policy['dashboard_execution_enabled'] ?? null, 'frontend reorganization shim should not enable dashboard execution');
    assert_same('public_html', $policy['document_root_retained'] ?? null, 'frontend reorganization shim should retain public_html document root');
    assert_same('Frameworks/Frontend/Index.php', $policy['moved_paths']['public_html/index.php'] ?? null, 'index body should move to Frontend framework');
    assert_same('Frameworks/Frontend/Dashboard.php', $policy['moved_paths']['public_html/dashboard.php'] ?? null, 'dashboard body should move to Frontend framework');
    assert_true(in_array('public_html/dashboard.php', $policy['compatibility_shims'] ?? [], true), 'public_html dashboard shim should remain');
    assert_true(in_array('public_html/assets/adlaire-ui.css', $policy['public_assets_retained'] ?? [], true), 'public UI CSS should remain in document root');
    assert_true(in_array('dashboard entrypoint delegates to frontend classes', $policy['source_code_improvements'] ?? [], true), 'frontend source improvement should delegate dashboard entrypoint');
    foreach (['Frameworks/Frontend/Index.php', 'Frameworks/Frontend/Dashboard.php', 'public_html/index.php', 'public_html/dashboard.php', 'public_html/assets/adlaire-ui.css'] as $file) {
        assert_true(is_file(__DIR__ . '/../' . $file), "frontend reorganization file should exist: {$file}");
    }
    assert_true(str_contains((string)file_get_contents(__DIR__ . '/../public_html/index.php'), 'Frameworks/Frontend/Index.php'), 'public index should be shim');
    assert_true(str_contains((string)file_get_contents(__DIR__ . '/../public_html/dashboard.php'), 'Frameworks/Frontend/Dashboard.php'), 'public dashboard should be shim');
    assert_true(str_contains((string)file_get_contents(__DIR__ . '/../Frameworks/Frontend/Dashboard.php'), 'AdlaireDashboardSecurity::authorized()'), 'dashboard body should delegate authorization to frontend class');

    $audit = Adlaire::audit();
    assert_same($policy, $audit['frontend_reorganization_shim_policy'] ?? null, 'audit should include frontend reorganization shim policy');
    assert_same($policy, Adlaire::distributionManifest()['frontend_reorganization_shim_policy'] ?? null, 'manifest should include frontend reorganization shim policy');

    $contract = Adlaire::stableReleaseContract();
    assert_true(in_array('frontend reorganization shim', $contract['backend_framework_capabilities'] ?? [], true), 'stable release contract should include frontend reorganization shim capability');
    assert_same(true, $contract['frontend_reorganization_shim'] ?? null, 'stable release contract should mark frontend reorganization shim');
    assert_policy_release_connection('frontend_reorganization_shim_policy', $policy);
    assert_same(true, Adlaire::releaseReadiness()['checks']['frontend_reorganization_shim_policy'] ?? null, 'release readiness should include frontend reorganization shim policy');
}

function test_css_framework_source_sync_policy(): void
{
    $policy = Adlaire::cssFrameworkSourceSyncPolicy();
    assert_same('v0.266', $policy['version'] ?? null, 'CSS framework source sync policy should include version');
    assert_same('CSS Framework Source Sync', $policy['theme'] ?? null, 'CSS framework source sync should define theme');
    assert_same('css_source_moved_distribution_asset_retained', $policy['status'] ?? null, 'CSS framework source sync should define status');
    assert_same('v0.265', $policy['range'] ?? null, 'CSS framework source sync should cover v0.265');
    assert_same(true, $policy['physical_reorganization_applied'] ?? null, 'CSS framework source sync should apply physical reorganization');
    assert_same(false, $policy['deployment_core_contract_changed'] ?? null, 'CSS framework source sync should not change DeploymentCore contract');
    assert_same(false, $policy['public_api_required'] ?? null, 'CSS framework source sync should not require public API');
    assert_same(false, $policy['configuration_files_allowed'] ?? null, 'CSS framework source sync should not allow configuration files');
    assert_same(false, $policy['dashboard_execution_enabled'] ?? null, 'CSS framework source sync should not enable dashboard execution');
    assert_same('Frameworks/CSS/adlaire-ui.css', $policy['source_asset'] ?? null, 'CSS framework source should be classified under Frameworks/CSS');
    assert_same('public_html/assets/adlaire-ui.css', $policy['distribution_asset'] ?? null, 'CSS distribution asset should remain under public_html');
    assert_same(true, $policy['sync_required'] ?? null, 'CSS source sync should require synchronization');
    assert_same(true, $policy['document_root_asset_retained'] ?? null, 'CSS source sync should retain document root asset');
    foreach (['Frameworks/CSS/adlaire-ui.css', 'public_html/assets/adlaire-ui.css'] as $file) {
        assert_true(is_file(__DIR__ . '/../' . $file), "CSS framework file should exist: {$file}");
    }
    assert_same(
        hash_file('sha256', __DIR__ . '/../Frameworks/CSS/adlaire-ui.css'),
        hash_file('sha256', __DIR__ . '/../public_html/assets/adlaire-ui.css'),
        'CSS source and distribution asset should match'
    );

    $audit = Adlaire::audit();
    assert_same($policy, $audit['css_framework_source_sync_policy'] ?? null, 'audit should include CSS framework source sync policy');
    assert_same($policy, Adlaire::distributionManifest()['css_framework_source_sync_policy'] ?? null, 'manifest should include CSS framework source sync policy');

    $contract = Adlaire::stableReleaseContract();
    assert_true(in_array('CSS framework source sync', $contract['backend_framework_capabilities'] ?? [], true), 'stable release contract should include CSS framework source sync capability');
    assert_same(true, $contract['css_framework_source_sync'] ?? null, 'stable release contract should mark CSS framework source sync');
    assert_policy_release_connection('css_framework_source_sync_policy', $policy);
    assert_same(true, Adlaire::releaseReadiness()['checks']['css_framework_source_sync_policy'] ?? null, 'release readiness should include CSS framework source sync policy');
}

function test_dashboard_frontend_class_extraction_policy(): void
{
    $policy = Adlaire::dashboardFrontendClassExtractionPolicy();
    assert_same('v0.266', $policy['version'] ?? null, 'dashboard frontend class extraction policy should include version');
    assert_same('Dashboard Frontend Class Extraction', $policy['theme'] ?? null, 'dashboard frontend class extraction should define theme');
    assert_same('dashboard_security_data_view_classes_extracted', $policy['status'] ?? null, 'dashboard frontend class extraction should define status');
    assert_same('v0.266', $policy['range'] ?? null, 'dashboard frontend class extraction should cover v0.266');
    assert_same(true, $policy['physical_reorganization_applied'] ?? null, 'dashboard frontend class extraction should apply physical reorganization');
    assert_same(false, $policy['deployment_core_contract_changed'] ?? null, 'dashboard frontend class extraction should not change DeploymentCore contract');
    assert_same(false, $policy['public_api_required'] ?? null, 'dashboard frontend class extraction should not require public API');
    assert_same(false, $policy['configuration_files_allowed'] ?? null, 'dashboard frontend class extraction should not allow configuration files');
    assert_same(false, $policy['dashboard_execution_enabled'] ?? null, 'dashboard frontend class extraction should not enable dashboard execution');
    assert_same('Frameworks/Frontend/Dashboard.php', $policy['entrypoint'] ?? null, 'dashboard frontend class extraction should define entrypoint');
    foreach ([
        'Frameworks/Frontend/DashboardSecurity.php' => 'AdlaireDashboardSecurity',
        'Frameworks/Frontend/DashboardData.php' => 'AdlaireDashboardData',
        'Frameworks/Frontend/DashboardView.php' => 'AdlaireDashboardView',
    ] as $file => $class) {
        assert_same($class, $policy['extracted_classes'][$file] ?? null, "dashboard frontend class extraction should map {$file}");
        assert_true(is_file(__DIR__ . '/../' . $file), "dashboard frontend class file should exist: {$file}");
        assert_true(str_contains((string)file_get_contents(__DIR__ . '/../' . $file), 'final class ' . $class), "dashboard frontend class should be declared: {$class}");
    }
    $entrypoint = (string)file_get_contents(__DIR__ . '/../Frameworks/Frontend/Dashboard.php');
    assert_true(str_contains($entrypoint, 'AdlaireDashboardSecurity::authorized()'), 'dashboard entrypoint should use security class');
    assert_true(str_contains($entrypoint, 'AdlaireDashboardData::collect($root)'), 'dashboard entrypoint should use data class');
    assert_true(str_contains($entrypoint, 'AdlaireDashboardView::render'), 'dashboard entrypoint should use view class');
    assert_true(!str_contains($entrypoint, 'function adlaire_dashboard_'), 'dashboard entrypoint should not define global helper functions');

    $audit = Adlaire::audit();
    assert_same($policy, $audit['dashboard_frontend_class_extraction_policy'] ?? null, 'audit should include dashboard frontend class extraction policy');
    assert_same($policy, Adlaire::distributionManifest()['dashboard_frontend_class_extraction_policy'] ?? null, 'manifest should include dashboard frontend class extraction policy');

    $contract = Adlaire::stableReleaseContract();
    assert_true(in_array('dashboard frontend class extraction', $contract['backend_framework_capabilities'] ?? [], true), 'stable release contract should include dashboard frontend class extraction capability');
    assert_same(true, $contract['dashboard_frontend_class_extraction'] ?? null, 'stable release contract should mark dashboard frontend class extraction');
    assert_policy_release_connection('dashboard_frontend_class_extraction_policy', $policy);
    assert_same(true, Adlaire::releaseReadiness()['checks']['dashboard_frontend_class_extraction_policy'] ?? null, 'release readiness should include dashboard frontend class extraction policy');
}

function test_framework_classification_policy(): void
{
    $policy = Adlaire::frameworkClassificationPolicy();
    assert_same('v0.266', $policy['version'] ?? null, 'framework classification policy should include version');
    assert_same('Framework Classification Specification', $policy['theme'] ?? null, 'framework classification policy should define theme');
    assert_same('v0.270', $policy['reorganization_target_version'] ?? null, 'framework classification should target v0.270');
    assert_same('v0.270 reorganized framework stable release', $policy['stable_release_target'] ?? null, 'framework classification should define v0.270 stable release target');
    assert_same(false, $policy['physical_reorganization_applied'] ?? null, 'framework classification should not apply physical reorganization yet');

    assert_true(in_array('DeploymentCore.php', $policy['classified_frameworks']['deployment_framework']['current_paths'] ?? [], true), 'deployment framework should include DeploymentCore.php');
    assert_same(true, $policy['classified_frameworks']['deployment_framework']['compatibility_domain'] ?? null, 'deployment framework should be compatibility domain');
    assert_true(in_array('FrameworkCore/Database.php', $policy['classified_frameworks']['backend_framework']['current_paths'] ?? [], true), 'backend framework should include Database.php');
    assert_true(in_array('public_html/dashboard.php', $policy['classified_frameworks']['frontend_framework']['current_paths'] ?? [], true), 'frontend framework should include dashboard');
    assert_true(in_array('public_html/assets/adlaire-ui.css', $policy['classified_frameworks']['css_framework']['current_paths'] ?? [], true), 'CSS framework should include UI CSS');
    assert_same('not_implemented', $policy['classified_frameworks']['javascript_framework']['implementation_status'] ?? null, 'JavaScript framework should not be implemented yet');
    assert_true(in_array('FrameworkCore/Kernel.php', $policy['classified_frameworks']['integration_core']['current_paths'] ?? [], true), 'Integration Core should include Kernel.php');
    assert_true(array_key_exists('v0.261-v0.270', $policy['roadmap'] ?? []), 'framework classification should include final roadmap range');

    $audit = Adlaire::audit();
    assert_same($policy, $audit['framework_classification_policy'] ?? null, 'audit should include framework classification policy');

    $manifest = Adlaire::distributionManifest();
    assert_same($policy, $manifest['framework_classification_policy'] ?? null, 'distribution manifest should include framework classification policy');

    $contract = Adlaire::stableReleaseContract();
    assert_true(in_array('framework classification specification', $contract['backend_framework_capabilities'] ?? [], true), 'stable release contract should include framework classification capability');
    assert_same(true, $contract['framework_classification_specification'] ?? null, 'stable release contract should mark framework classification');

    assert_policy_release_connection('framework_classification_policy', $policy);

    $readiness = Adlaire::releaseReadiness();
    assert_same(true, $readiness['checks']['framework_classification_policy'] ?? null, 'release readiness should include framework classification policy');
}

function test_integration_core_policy(): void
{
    $policy = Adlaire::integrationCorePolicy();
    assert_same('v0.266', $policy['version'] ?? null, 'integration core policy should include version');
    assert_same('Integration Core Concept', $policy['theme'] ?? null, 'integration core policy should define theme');
    assert_same('coordinate classified framework families without public API dependency', $policy['role'] ?? null, 'integration core should define coordination role');
    assert_same(false, $policy['physical_reorganization_applied'] ?? null, 'integration core should not apply physical reorganization yet');
    assert_same(false, $policy['public_api_required'] ?? null, 'integration core should not require public API');
    assert_same(false, $policy['configuration_files_allowed'] ?? null, 'integration core should not allow configuration files');
    assert_same(true, $policy['deployment_framework_compatibility_required'] ?? null, 'integration core should preserve deployment framework compatibility');

    foreach (['deployment_framework', 'backend_framework', 'frontend_framework', 'css_framework', 'javascript_framework'] as $framework) {
        assert_true(in_array($framework, $policy['coordinated_frameworks'] ?? [], true), "integration core should coordinate framework: {$framework}");
    }
    foreach (['framework_family_registry', 'framework_lifecycle', 'dependency_graph', 'policy_audit', 'release_readiness', 'deployment_control_connection', 'compatibility_boundary_management'] as $responsibility) {
        assert_true(in_array($responsibility, $policy['responsibilities'] ?? [], true), "integration core should define responsibility: {$responsibility}");
    }
    assert_same(true, $policy['internal_contracts_only'] ?? null, 'integration core should use internal contracts only');
    assert_true(in_array('FrameworkCore/Core.php', $policy['current_core_paths'] ?? [], true), 'integration core should include Core.php');
    assert_true(in_array('FrameworkCore/Kernel.php', $policy['current_core_paths'] ?? [], true), 'integration core should include Kernel.php');
    assert_same('v0.270 reorganized framework stable release', $policy['release_target'] ?? null, 'integration core should target v0.270 stable release');

    $audit = Adlaire::audit();
    assert_same($policy, $audit['integration_core_policy'] ?? null, 'audit should include integration core policy');

    $manifest = Adlaire::distributionManifest();
    assert_same($policy, $manifest['integration_core_policy'] ?? null, 'distribution manifest should include integration core policy');

    $contract = Adlaire::stableReleaseContract();
    assert_true(in_array('integration core concept', $contract['backend_framework_capabilities'] ?? [], true), 'stable release contract should include integration core capability');
    assert_same(true, $contract['integration_core_concept'] ?? null, 'stable release contract should mark integration core concept');

    assert_policy_release_connection('integration_core_policy', $policy);

    $readiness = Adlaire::releaseReadiness();
    assert_same(true, $readiness['checks']['integration_core_policy'] ?? null, 'release readiness should include integration core policy');
}

function test_auris_integration_policy(): void
{
    $policy = Adlaire::aurisIntegrationPolicy();
    assert_same('v0.266', $policy['version'] ?? null, 'Auris integration policy should include version');
    assert_same(true, $policy['future_integration'] ?? null, 'Auris integration policy should mark future integration');
    assert_same('Auris', $policy['target_system'] ?? null, 'Auris integration policy should identify target system');
    assert_same('https://github.com/fqwink/Auris', $policy['target_repository'] ?? null, 'Auris integration policy should identify target repository');
    assert_same(true, $policy['framework_repository_maintained'] ?? null, 'Auris integration policy should maintain this framework repository');
    assert_same('independent framework repository', $policy['repository_role'] ?? null, 'Auris integration policy should keep independent repository role');
    assert_same('planned', $policy['integration_status'] ?? null, 'Auris integration policy should mark integration as planned');
    assert_same('abolished', $policy['auris_independent_system_after_integration'] ?? null, 'Auris integration policy should abolish Auris as an independent system after integration');
    assert_same('deprecated', $policy['auris_repository_after_integration'] ?? null, 'Auris integration policy should deprecate Auris repository after integration');
    assert_same(true, $policy['auris_name_retained'] ?? null, 'Auris integration policy should retain Auris system name');
    assert_same('Auris', $policy['auris_module_name'] ?? null, 'Auris integration policy should keep Auris as module name');
    assert_same(true, $policy['auris_moduleization'] ?? null, 'Auris integration policy should moduleize Auris');
    assert_same('integrated Adlaire module', $policy['auris_module_role'] ?? null, 'Auris integration policy should define Auris module role');
    assert_same('AurisModule', $policy['auris_module_class'] ?? null, 'Auris integration policy should define Auris module class');
    assert_true(in_array('auris.status', $policy['auris_module_messages'] ?? [], true), 'Auris integration policy should expose status message');
    assert_true(in_array('auris.policy', $policy['auris_module_messages'] ?? [], true), 'Auris integration policy should expose policy message');
    assert_true(in_array('auris.metadata', $policy['auris_module_messages'] ?? [], true), 'Auris integration policy should expose metadata message');
    assert_true(in_array('auris.manifest', $policy['auris_module_messages'] ?? [], true), 'Auris integration policy should expose manifest message');
    assert_true(in_array('auris.validate', $policy['auris_module_messages'] ?? [], true), 'Auris integration policy should expose validation message');
    assert_same(true, $policy['auris_manifest_required'] ?? null, 'Auris integration policy should require module manifest');
    assert_same(true, $policy['auris_policy_validation_required'] ?? null, 'Auris integration policy should require policy validation');
    assert_same(false, $policy['architecture_changed'] ?? null, 'Auris integration policy should not change architecture');

    $audit = Adlaire::audit();
    assert_same($policy, $audit['auris_integration_policy'] ?? null, 'audit should include Auris integration policy');

    $manifest = Adlaire::distributionManifest();
    assert_same($policy, $manifest['auris_integration_policy'] ?? null, 'distribution manifest should include Auris integration policy');

    $readiness = Adlaire::releaseReadiness();
    assert_same(true, $readiness['checks']['auris_integration_policy'] ?? null, 'release readiness should include Auris integration policy');
}

function test_auris_module(): void
{
    $module = new AurisModule();
    assert_same('Auris', $module->id(), 'Auris module should retain Auris name as module id');
    assert_true(str_contains($module->responsibility(), 'moduleized integration'), 'Auris module should describe moduleized integration responsibility');
    assert_true(in_array('deployment system', $module->dependencies(), true), 'Auris module should depend on deployment system');

    $status = $module->handle('auris.status');
    assert_same(true, $status['moduleized'] ?? null, 'Auris status should mark moduleized state');
    assert_same(true, $status['name_retained'] ?? null, 'Auris status should retain name');
    assert_same('abolished', $status['independent_system_after_integration'] ?? null, 'Auris status should abolish independent system after integration');
    assert_same('deprecated', $status['repository_after_integration'] ?? null, 'Auris status should deprecate repository after integration');

    $policy = $module->handle('auris.policy');
    assert_same('integrated Adlaire module', $policy['module_role'] ?? null, 'Auris policy should define integrated module role');
    assert_same(false, $policy['architecture_changed'] ?? null, 'Auris policy should not change architecture');

    $metadata = $module->handle('auris.metadata', ['source' => 'debug']);
    assert_same('Auris', $metadata['module'] ?? null, 'Auris metadata should include module id');
    assert_same('debug', $metadata['payload']['source'] ?? null, 'Auris metadata should echo payload');
    $manifest = $module->handle('auris.manifest');
    assert_same('Auris', $manifest['id'] ?? null, 'Auris manifest should include id');
    assert_same(true, $manifest['moduleized'] ?? null, 'Auris manifest should mark moduleized state');
    assert_true(in_array('auris.validate', $manifest['messages'] ?? [], true), 'Auris manifest should include validation message');
    assert_same('ready', $manifest['health']['status'] ?? null, 'Auris manifest should include health');
    assert_same(false, $manifest['policy']['architecture_changed'] ?? null, 'Auris manifest should include unchanged architecture policy');

    $validation = $module->handle('auris.validate', Adlaire::aurisIntegrationPolicy());
    assert_same(true, $validation['valid'] ?? null, 'Auris module should validate integration policy');
    foreach ($validation['checks'] ?? [] as $name => $passed) {
        assert_same(true, $passed, "Auris validation check should pass: {$name}");
    }

    $invalidValidation = $module->handle('auris.validate', ['auris_module_name' => 'Other']);
    assert_same(false, $invalidValidation['valid'] ?? null, 'Auris module should reject invalid integration policy');
    assert_same(false, $invalidValidation['checks']['module_name'] ?? null, 'Auris validation should detect wrong module name');
    assert_same('ready', $module->health()['status'] ?? null, 'Auris module health should be ready');

    Adlaire::init();
    $kernel = Adlaire::kernel();
    $kernel->registerModule($module);
    assert_true(in_array('Auris', $kernel->modules(), true), 'kernel should register Auris module');
    assert_same(true, $kernel->send('Auris', 'auris.status')['moduleized'] ?? null, 'kernel should send message to Auris module');
    assert_same(true, $kernel->send('Auris', 'auris.validate', Adlaire::aurisIntegrationPolicy())['valid'] ?? null, 'kernel should validate Auris policy through module');
    assert_same('ready', $kernel->healthReport()['modules']['Auris']['status'] ?? null, 'kernel health should include Auris module');
}

function test_official_metadata(): void
{
    $distribution = Adlaire::distributionPolicy();
    assert_same(true, $distribution['official_distribution_required'] ?? null, 'distribution policy should require official distribution');
    assert_same('commercial use license', $distribution['redistribution_license'] ?? null, 'distribution policy should mark redistribution license');
    assert_same('commercial use license', $distribution['modified_distribution_license'] ?? null, 'distribution policy should mark modified distribution license');
    assert_same(false, $distribution['unofficial_distribution_may_claim_official'] ?? null, 'distribution policy should reject unofficial official claims');

    $boundary = Adlaire::cloudBusinessBoundary();
    assert_same('prohibited', $boundary['use'] ?? null, 'cloud boundary should prohibit cloud business use');
    assert_same(['open source license', 'commercial use license'], $boundary['applies_to'] ?? null, 'cloud boundary should apply to both licenses');
    assert_true(in_array('SaaS', $boundary['prohibited_categories'] ?? [], true), 'cloud boundary should include SaaS');
    assert_true(in_array('PaaS', $boundary['prohibited_categories'] ?? [], true), 'cloud boundary should include PaaS');
    assert_true(in_array('DBaaS', $boundary['prohibited_categories'] ?? [], true), 'cloud boundary should include DBaaS');
    assert_true(in_array('managed runtime environment', $boundary['prohibited_categories'] ?? [], true), 'cloud boundary should include managed runtime');

    $metadata = Adlaire::officialMetadata();
    assert_same('v0.266', $metadata['version'] ?? null, 'official metadata should include version');
    assert_same(Adlaire::licensePolicy(), $metadata['license_policy'] ?? null, 'official metadata should include license policy');
    assert_same($distribution, $metadata['distribution_policy'] ?? null, 'official metadata should include distribution policy');
    assert_same($boundary, $metadata['cloud_business_boundary'] ?? null, 'official metadata should include cloud boundary');
    assert_same(true, $metadata['release_readiness_required'] ?? null, 'official metadata should require release readiness');

    $audit = Adlaire::audit();
    assert_same($distribution, $audit['distribution_policy'] ?? null, 'audit should include distribution policy');
    assert_same($boundary, $audit['cloud_business_boundary'] ?? null, 'audit should include cloud boundary');
    assert_same($metadata, $audit['official_metadata'] ?? null, 'audit should include official metadata');
}

function test_specification_integrity(): void
{
    $integrity = Adlaire::specificationIntegrity();
    assert_same(true, $integrity['valid'] ?? null, 'specification integrity should pass');
    foreach ($integrity['checks'] ?? [] as $name => $passed) {
        assert_same(true, $passed, "specification integrity check should pass: {$name}");
    }

    $audit = Adlaire::audit();
    assert_same($integrity, $audit['specification_integrity'] ?? null, 'audit should include specification integrity');

    $readiness = Adlaire::releaseReadiness();
    assert_same(true, $readiness['checks']['specification_integrity'] ?? null, 'release readiness should include specification integrity');
}

function test_specification_drift(): void
{
    $drift = Adlaire::specificationDrift();
    assert_same(false, $drift['drift'] ?? null, 'specification drift should be false');
    assert_same([], $drift['missing_tests'] ?? null, 'specification drift should have no missing tests');
    assert_same([], $drift['unknown_specification_ids'] ?? null, 'specification drift should have no unknown IDs');
    assert_same([], $drift['missing_audit_keys'] ?? null, 'specification drift should have no missing audit keys');
    assert_same([], $drift['missing_readiness_checks'] ?? null, 'specification drift should have no missing readiness checks');

    $audit = Adlaire::audit();
    assert_same($drift, $audit['specification_drift'] ?? null, 'audit should include specification drift');

    $readiness = Adlaire::releaseReadiness();
    assert_same(true, $readiness['checks']['specification_drift'] ?? null, 'release readiness should include specification drift');
}

function test_distribution_manifest(): void
{
    $manifest = Adlaire::distributionManifest();
    assert_same('v0.266', $manifest['version'] ?? null, 'distribution manifest should include version');
    foreach (['FrameworkCore/Core.php', 'FrameworkCore/Kernel.php', 'FrameworkCore/Extension.php', 'FrameworkCore/Database.php', 'DeploymentCore.php', 'FrameworkCore/Logger.php', 'FrameworkCore/Config.php', 'FrameworkCore/Middleware.php', 'FrameworkCore/Support.php', 'tests/debug.php', 'README.md', 'adlaire-ecosystem.md'] as $file) {
        assert_true(in_array($file, $manifest['files'] ?? [], true), "distribution manifest should include file: {$file}");
    }
    assert_same(Adlaire::licensePolicy(), $manifest['license_policy'] ?? null, 'distribution manifest should include license policy');
    assert_same(Adlaire::distributionPolicy(), $manifest['distribution_policy'] ?? null, 'distribution manifest should include distribution policy');
    assert_same(Adlaire::deploymentAxisPolicy(), $manifest['deployment_axis_policy'] ?? null, 'distribution manifest should include deployment axis policy');
    assert_same(Adlaire::aurisIntegrationPolicy(), $manifest['auris_integration_policy'] ?? null, 'distribution manifest should include Auris integration policy');
    assert_same('php -d phar.readonly=0 tests/debug.php', $manifest['official_debug_test'] ?? null, 'distribution manifest should include debug test');
    assert_same(true, $manifest['release_readiness']['ready'] ?? null, 'distribution manifest should mark release readiness');

    $audit = Adlaire::audit();
    assert_same($manifest, $audit['distribution_manifest'] ?? null, 'audit should include distribution manifest');

    $readiness = Adlaire::releaseReadiness();
    assert_same(true, $readiness['checks']['distribution_manifest'] ?? null, 'release readiness should include distribution manifest');
}

function test_microkernel(): void
{
    Adlaire::init();
    $kernel = Adlaire::kernel();
    assert_true($kernel->has('router'), 'kernel should expose router service');
    assert_true($kernel->has('request'), 'kernel should expose request service');
    assert_true($kernel->has('response'), 'kernel should expose response service');
    assert_true($kernel->get('router') instanceof Router, 'kernel router service should be Router');

    $kernel->registerExtension(new DebugExtension());
    assert_true($kernel->get('debug.registered'), 'kernel extension should register services');
    assert_same(['debug'], $kernel->extensions(), 'kernel should expose registered extension names');
    $kernel->boot();
    $kernel->boot();
    assert_true($kernel->booted(), 'kernel should report booted state');
    assert_true($kernel->get('debug.booted'), 'kernel extension should boot');

    try {
        $kernel->registerExtension(new DebugExtension());
        throw new DebugTestFailure('duplicate extension registration should fail');
    } catch (RuntimeException) {
    }
}

function test_autonomous_system(): void
{
    Adlaire::init();
    $kernel = Adlaire::kernel();
    $kernel->requires('debug', []);
    $kernel->configureExtension('debug', ['enabled' => true, 'name' => 'debug'], ['enabled' => 'bool', 'name' => 'string']);
    assert_same(['enabled' => true, 'name' => 'debug'], $kernel->extensionConfig('debug'), 'kernel should expose extension config');
    $kernel->allowServices('debug', ['router']);
    $kernel->registerExtension(new DebugExtension());
    assert_true($kernel->serviceFor('debug', 'router') instanceof Router, 'kernel should allow permitted service access');
    try {
        $kernel->serviceFor('debug', 'request');
        throw new DebugTestFailure('kernel should reject non-allowed service access');
    } catch (RuntimeException) {
    }

    $events = [];
    $kernel->on('debug.event', static function (array $payload) use (&$events): string {
        $events[] = $payload['value'] ?? null;
        return 'handled';
    });
    assert_same(['handled'], $kernel->emit('debug.event', ['value' => 'ok']), 'kernel event bus should return listener result');
    assert_same(['ok'], $events, 'kernel event bus should pass payload');

    $kernel->registerModule(new DebugModule());
    assert_same(['pong' => 'ok'], $kernel->send('debug.module', 'debug.ping', ['value' => 'ok']), 'kernel should send messages to modules');
    $kernel->handle('debug.custom', static fn(string $module, array $payload): array => ['module' => $module, 'value' => $payload['value'] ?? null]);
    assert_same(['module' => 'debug.module', 'value' => 7], $kernel->send('debug.module', 'debug.custom', ['value' => 7]), 'kernel should use message handlers');
    assert_same('ready', $kernel->healthReport()['status'] ?? null, 'kernel health report should be ready');
    assert_true(isset($kernel->extensionManifest()['extensions']['debug']), 'kernel extension manifest should include debug extension');

    assert_same(false, Adlaire::policyDecision('cloud_business_use')['allow'], 'policy should deny cloud business use');
    assert_same(true, Adlaire::policyDecision('commercial_use')['allow'], 'policy should allow non-cloud commercial use');
    assert_same('ready', Adlaire::healthReport()['status'] ?? null, 'Adlaire health report should be ready');
    assert_same(true, Adlaire::stabilityContract()['breaking_changes_allowed'] ?? null, 'stability contract should allow breaking changes');
    assert_same(false, Adlaire::stabilityContract()['compatibility_guaranteed'] ?? null, 'stability contract should not guarantee compatibility');
    assert_same(false, Adlaire::stabilityContract()['deployment_system_breaking_changes_allowed'] ?? null, 'stability contract should protect deployment system');
    assert_same(true, Adlaire::stabilityContract()['deployment_system_compatibility_guaranteed'] ?? null, 'stability contract should guarantee deployment system compatibility');
    $report = Adlaire::autonomousAuditReport();
    assert_same('v0.266', $report['version'] ?? null, 'autonomous audit report should include version');
    assert_true(isset($report['policies']['cloud_business_use']), 'autonomous audit report should include policies');
}

function test_long_term_stability(): void
{
    $registry = Adlaire::officialExtensionRegistry();
    assert_same(false, $registry['unknown_extensions_allowed_as_official'] ?? null, 'official registry should reject unknown official extensions');
    assert_same(true, $registry['cloud_business_prohibition_enforced'] ?? null, 'official registry should enforce cloud prohibition');

    $profiles = Adlaire::releaseProfiles();
    foreach (['minimal', 'standard', 'audited', 'distributed', 'extension_enabled'] as $profile) {
        assert_true(isset($profiles[$profile]), "release profile should exist: {$profile}");
    }

    $migration = Adlaire::migrationPolicy();
    assert_same(true, $migration['breaking_changes'] ?? null, 'migration policy should allow breaking changes');
    assert_same(false, $migration['compatibility_required'] ?? null, 'migration policy should not require compatibility');
    assert_same(false, $migration['deployment_system_breaking_changes'] ?? null, 'migration policy should forbid deployment system breaking changes');
    assert_same(true, $migration['deployment_system_compatibility_required'] ?? null, 'migration policy should require deployment system compatibility');
    assert_same(true, $migration['doc_update_required'] ?? null, 'migration policy should require documentation');

    $support = Adlaire::supportPolicy();
    assert_same(true, $support['long_term_support'] ?? null, 'support policy should mark long term support');
    assert_same(false, $support['compatibility_guaranteed'] ?? null, 'support policy should not guarantee compatibility');

    $security = Adlaire::securityFixProtocol();
    assert_same(['report', 'assess', 'patch', 'test', 'audit', 'release', 'document'], $security['steps'] ?? null, 'security protocol should include required steps');

    $noCompatibility = Adlaire::noCompatibilityPolicy();
    assert_same(false, $noCompatibility['compatibility_guaranteed'] ?? null, 'no compatibility policy should disable compatibility guarantees');
    assert_same(true, $noCompatibility['future_breaking_changes_allowed'] ?? null, 'no compatibility policy should allow future breaking changes');
    assert_same(true, $noCompatibility['scope_exceptions']['deployment_system']['compatibility_guaranteed'] ?? null, 'no compatibility policy should except deployment system');

    $deploymentCompatibility = Adlaire::deploymentSystemCompatibilityPolicy();
    assert_same('DeploymentCore.php', $deploymentCompatibility['core_file'] ?? null, 'deployment compatibility policy should protect DeploymentCore');
    assert_same(true, $deploymentCompatibility['compatibility_guaranteed'] ?? null, 'deployment compatibility policy should guarantee compatibility');
    assert_same(false, $deploymentCompatibility['breaking_changes_allowed'] ?? null, 'deployment compatibility policy should forbid breaking changes');

    $freeze = Adlaire::releaseFreezePolicy();
    assert_true(in_array('breaking_changes', $freeze['allowed_changes'] ?? [], true), 'release freeze should allow breaking changes');
    assert_true(in_array('deployment_system_breaking_changes', $freeze['forbidden_changes'] ?? [], true), 'release freeze should forbid deployment system breaking changes');
    assert_same(false, $freeze['compatibility_required'] ?? null, 'release freeze should not require compatibility');
    assert_same(true, $freeze['deployment_system_compatibility_required'] ?? null, 'release freeze should require deployment system compatibility');

    $lts = Adlaire::longTermStabilityContract();
    assert_same('v0.266', $lts['version'] ?? null, 'long term stability contract should include version');
    assert_same(true, $lts['long_term_stable'] ?? null, 'long term stability contract should mark stable');
    assert_same(false, $lts['no_breaking_changes'] ?? null, 'long term stability contract should not forbid breaking changes');
    assert_same(false, $lts['compatibility_guaranteed'] ?? null, 'long term stability contract should not guarantee compatibility');
    assert_same(true, $lts['deployment_system_no_breaking_changes'] ?? null, 'long term stability contract should protect deployment system');
    assert_same(true, $lts['deployment_system_compatibility_guaranteed'] ?? null, 'long term stability contract should guarantee deployment system compatibility');
    assert_same(true, $lts['docs_are_source_of_truth'] ?? null, 'long term stability contract should keep docs as source of truth');
    assert_same(true, $lts['cloud_business_prohibition_fixed'] ?? null, 'long term stability contract should fix cloud prohibition');

    $audit = Adlaire::audit();
    assert_same($lts, $audit['long_term_stability_contract'] ?? null, 'audit should include long term stability contract');
    assert_true(isset($audit['ecosystem_audit_report']), 'audit should include ecosystem audit report');
    assert_same(true, Adlaire::releaseReadiness()['checks']['long_term_stability_contract'] ?? null, 'release readiness should include long term stability');
}

function test_stable_release_contract(): void
{
    $contract = Adlaire::stableReleaseContract();
    assert_same('v0.266', $contract['version'] ?? null, 'stable release contract should include version');
    assert_same(true, $contract['stable_release'] ?? null, 'stable release contract should mark stable release');
    assert_same('v0.266 dashboard frontend class extraction release', $contract['release_name'] ?? null, 'stable release contract should name release');
    foreach (['routing', 'middleware', 'validation', 'database', 'logging', 'deployment', 'configuration', 'support helpers', 'microkernel', 'Auris module integration', 'SQLite / libSQL API runtime hardening', 'runtime operations hardening', 'operations dashboard', 'configuration file prohibition', 'deployment preflight guard', 'deployment plan preview', 'deployment compatibility snapshot', 'deployment rollback preview', 'deployment safety score', 'dashboard control visibility', 'deployment history visualization', 'deployment control report', 'stable release gate', 'Adlaire UI framework', 'deployment control snapshot', 'deployment safety score details', 'rollback state preview', 'dashboard release gate view', 'deployment timeline view', 'Adlaire UI framework expansion', 'release evidence bundle', 'deployment control diff', 'stable release candidate gate', 'API removal', 'specification-first workflow', 'deployment axis map', 'dashboard deploy execution specification', 'framework classification specification', 'integration core concept', 'execution safety gate', 'deployment execute adapter contract', 'execution audit trail', 'dashboard gated controls', 'reorganization readiness boundary', 'reorganization architecture plan', 'non-deployment migration preparation plan', 'physical reorganization phase one', 'frontend reorganization shim', 'CSS framework source sync', 'dashboard frontend class extraction'] as $capability) {
        assert_true(in_array($capability, $contract['backend_framework_capabilities'] ?? [], true), "stable release contract should include capability: {$capability}");
    }
    assert_same(false, $contract['no_breaking_changes'] ?? null, 'stable release contract should not forbid breaking changes');
    assert_same(true, $contract['breaking_changes_allowed'] ?? null, 'stable release contract should allow breaking changes');
    assert_same(false, $contract['compatibility_guaranteed'] ?? null, 'stable release contract should not guarantee compatibility');
    assert_same(true, $contract['deployment_system_no_breaking_changes'] ?? null, 'stable release contract should protect deployment system');
    assert_same(true, $contract['deployment_system_compatibility_guaranteed'] ?? null, 'stable release contract should guarantee deployment system compatibility');
    assert_same(true, $contract['ten_file_principle'] ?? null, 'stable release contract should retain 10-file principle');
    assert_same(true, $contract['deployment_axis'] ?? null, 'stable release contract should retain deployment axis');
    assert_same(true, $contract['docker_debug_verified'] ?? null, 'stable release contract should require Docker debug verification');
    assert_same(false, $contract['mysql_support_planned'] ?? null, 'stable release contract should not plan MySQL support');
    assert_same(true, $contract['runtime_operations_hardening'] ?? null, 'stable release contract should include runtime operations hardening');
    assert_same(true, $contract['operations_dashboard'] ?? null, 'stable release contract should include operations dashboard');
    assert_same(true, $contract['configuration_file_prohibition'] ?? null, 'stable release contract should include configuration file prohibition');
    assert_same(true, $contract['deployment_preflight_guard'] ?? null, 'stable release contract should include deployment preflight guard');
    assert_same(true, $contract['deployment_plan_preview'] ?? null, 'stable release contract should include deployment plan preview');
    assert_same(true, $contract['deployment_compatibility_snapshot'] ?? null, 'stable release contract should include deployment compatibility snapshot');
    assert_same(true, $contract['deployment_rollback_preview'] ?? null, 'stable release contract should include deployment rollback preview');
    assert_same(true, $contract['deployment_safety_score'] ?? null, 'stable release contract should include deployment safety score');
    assert_same(true, $contract['dashboard_control_visibility'] ?? null, 'stable release contract should include dashboard control visibility');
    assert_same(true, $contract['deployment_history_visualization'] ?? null, 'stable release contract should include deployment history visualization');
    assert_same(true, $contract['deployment_control_report'] ?? null, 'stable release contract should include deployment control report');
    assert_same(true, $contract['stable_release_gate'] ?? null, 'stable release contract should include stable release gate');
    assert_same(true, $contract['adlaire_ui_framework'] ?? null, 'stable release contract should include UI framework');
    assert_same(true, $contract['deployment_control_snapshot'] ?? null, 'stable release contract should include deployment control snapshot');
    assert_same(true, $contract['deployment_safety_score_details'] ?? null, 'stable release contract should include deployment safety score details');
    assert_same(true, $contract['rollback_state_preview'] ?? null, 'stable release contract should include rollback state preview');
    assert_same(true, $contract['dashboard_release_gate_view'] ?? null, 'stable release contract should include dashboard release gate view');
    assert_same(true, $contract['deployment_timeline_view'] ?? null, 'stable release contract should include deployment timeline view');
    assert_same(true, $contract['adlaire_ui_framework_expansion'] ?? null, 'stable release contract should include UI framework expansion');
    assert_same(true, $contract['release_evidence_bundle'] ?? null, 'stable release contract should include release evidence bundle');
    assert_same(true, $contract['deployment_control_diff'] ?? null, 'stable release contract should include deployment control diff');
    assert_same(true, $contract['stable_release_candidate_gate'] ?? null, 'stable release contract should include stable release candidate gate');
    assert_same(true, $contract['api_removal'] ?? null, 'stable release contract should include API removal');
    assert_same(true, $contract['specification_first_workflow'] ?? null, 'stable release contract should include specification-first workflow');
    assert_same(true, $contract['deployment_axis_map'] ?? null, 'stable release contract should include deployment axis map');
    assert_same(true, $contract['dashboard_deploy_execution_specification'] ?? null, 'stable release contract should include dashboard deploy execution specification');
    assert_same(true, $contract['framework_classification_specification'] ?? null, 'stable release contract should include framework classification specification');
    assert_same(true, $contract['integration_core_concept'] ?? null, 'stable release contract should include integration core concept');
    assert_same(true, $contract['execution_safety_gate'] ?? null, 'stable release contract should include execution safety gate');
    assert_same(true, $contract['deployment_execute_adapter_contract'] ?? null, 'stable release contract should include deployment execute adapter contract');
    assert_same(true, $contract['execution_audit_trail'] ?? null, 'stable release contract should include execution audit trail');
    assert_same(true, $contract['dashboard_gated_controls'] ?? null, 'stable release contract should include dashboard gated controls');
    assert_same(true, $contract['reorganization_readiness_boundary'] ?? null, 'stable release contract should include reorganization readiness boundary');
    assert_same(true, $contract['reorganization_architecture_plan'] ?? null, 'stable release contract should include reorganization architecture plan');
    assert_same(true, $contract['reorganization_preparation_plan'] ?? null, 'stable release contract should include reorganization preparation plan');
    assert_same(true, $contract['physical_reorganization_phase_one'] ?? null, 'stable release contract should include physical reorganization phase one');
    assert_same(true, $contract['frontend_reorganization_shim'] ?? null, 'stable release contract should include frontend reorganization shim');
    assert_same(true, $contract['css_framework_source_sync'] ?? null, 'stable release contract should include CSS framework source sync');
    assert_same(true, $contract['dashboard_frontend_class_extraction'] ?? null, 'stable release contract should include dashboard frontend class extraction');

    $audit = Adlaire::audit();
    assert_same($contract, $audit['stable_release_contract'] ?? null, 'audit should include stable release contract');
    assert_same($contract, Adlaire::distributionManifest()['stable_release_contract'] ?? null, 'manifest should include stable release contract');
    assert_same(true, Adlaire::specificationIntegrity()['checks']['stable_release_contract'] ?? null, 'specification integrity should include stable release contract');
    assert_same(true, Adlaire::releaseReadiness()['checks']['stable_release_contract'] ?? null, 'release readiness should include stable release contract');
}

function test_production_equivalent_environment(): void
{
    $policy = Adlaire::productionEnvironmentPolicy();
    assert_same('v0.266', $policy['version'] ?? null, 'production environment policy should include version');
    assert_same('Xserver rental server', $policy['production_provider'] ?? null, 'production environment policy should identify Xserver');
    assert_same('Xserver shared rental server', $policy['production_environment'] ?? null, 'production environment policy should identify rental server environment');
    assert_same(true, $policy['production_equivalent_testing_required'] ?? null, 'production-equivalent testing should be required');
    assert_same('Docker php:8.3-apache profile', $policy['local_test_environment'] ?? null, 'local test environment should use Docker Apache profile');
    assert_same('>=8.3', $policy['php_requirement'] ?? null, 'production profile should keep PHP 8.3 requirement');
    assert_same('PHP 8.3.x', $policy['php_profile'] ?? null, 'production profile should use PHP 8.3 profile');
    assert_same('Apache shared hosting', $policy['web_server_profile'] ?? null, 'production profile should use Apache shared hosting');
    assert_same('public_html', $policy['document_root'] ?? null, 'production profile should define public_html document root');
    assert_same(true, $policy['htaccess_required'] ?? null, 'production profile should require htaccess');
    assert_same(false, $policy['composer_required'] ?? null, 'production profile should not require Composer');
    assert_same(false, $policy['external_service_required_for_tests'] ?? null, 'production-equivalent test should avoid external services');
    assert_same(true, $policy['database_profile']['sqlite_for_local_debug'] ?? null, 'production profile should allow SQLite local debug');
    assert_same(false, $policy['database_profile']['mysql_compatible_production'] ?? null, 'production profile should not plan MySQL');
    assert_same('DeploymentCore.php', $policy['deployment_profile']['root_deployment_core'] ?? null, 'production profile should keep DeploymentCore at root');
    assert_same('FrameworkCore', $policy['deployment_profile']['framework_core_directory'] ?? null, 'production profile should keep FrameworkCore directory');
    assert_same(true, $policy['deployment_profile']['no_deployment_core_directory'] ?? null, 'production profile should prohibit DeploymentCore directory');
    assert_true(in_array('xserver_profile_audit', $policy['required_verifications'] ?? [], true), 'production profile should require Xserver audit');

    $requirements = Adlaire::releaseRequirementMatrix();
    assert_same('Xserver rental server', $requirements['production_equivalent']['provider'] ?? null, 'release requirement matrix should include Xserver profile');
    assert_same(true, $requirements['production_equivalent']['passed'] ?? null, 'Xserver profile requirement should pass');
    assert_same($policy, $requirements['production_equivalent']['profile'] ?? null, 'release requirement matrix should embed production policy');
    assert_same(true, $requirements['deployment_system_compatibility']['passed'] ?? null, 'release requirement matrix should pass deployment system compatibility');
    assert_same(Adlaire::deploymentSystemCompatibilityPolicy(), $requirements['deployment_system_compatibility']['profile'] ?? null, 'release requirement matrix should embed deployment compatibility policy');

    $audit = Adlaire::audit();
    assert_same($policy, $audit['production_environment_policy'] ?? null, 'audit should include production environment policy');
    assert_same(true, $audit['specification_integrity']['checks']['production_environment_policy'] ?? null, 'specification integrity should include production environment policy');

    $readiness = Adlaire::releaseReadiness();
    assert_same(true, $readiness['checks']['production_environment_policy'] ?? null, 'release readiness should include production environment policy');
    assert_same($policy, Adlaire::distributionManifest()['production_environment_policy'] ?? null, 'distribution manifest should include production environment policy');
}

function test_database_runtime_hardening_policy(): void
{
    $policy = Adlaire::databaseRuntimeHardeningPolicy();
    assert_same('v0.266', $policy['version'] ?? null, 'database hardening policy should include version');
    assert_same('SQLite / libSQL API Runtime Hardening', $policy['theme'] ?? null, 'database hardening policy should define theme');
    assert_same(false, $policy['mysql_support_planned'] ?? null, 'database hardening policy should not plan MySQL support');
    assert_true(in_array('sqlite-file', $policy['supported_database_profiles'] ?? [], true), 'database hardening policy should support SQLite file profile');
    assert_true(in_array('libsql-api', $policy['supported_database_profiles'] ?? [], true), 'database hardening policy should support internal libSQL API profile');
    assert_true(in_array('libsql-websocket-fallback', $policy['supported_database_profiles'] ?? [], true), 'database hardening policy should support libSQL websocket fallback profile');
    assert_same(true, $policy['sqlite_profile']['foreign_keys_enabled_by_default'] ?? null, 'SQLite hardening should enable foreign keys by default');
    assert_same(5000, $policy['sqlite_profile']['busy_timeout_ms_default'] ?? null, 'SQLite hardening should define busy timeout default');
    assert_same(true, $policy['sqlite_profile']['wal_for_file_databases_by_default'] ?? null, 'SQLite hardening should use WAL for file databases');
    assert_same(false, $policy['api_transport_profile']['public_api_available'] ?? null, 'database hardening should not expose public API');
    assert_same(true, $policy['api_transport_profile']['internal_libsql_api_available'] ?? null, 'database hardening should expose internal libSQL API');
    assert_same(true, $policy['api_transport_profile']['timeout_configurable'] ?? null, 'libSQL API hardening should make timeout configurable');
    assert_same(true, $policy['api_transport_profile']['retries_configurable'] ?? null, 'libSQL API hardening should make retries configurable');
    assert_same(true, $policy['api_transport_profile']['custom_transport_for_tests'] ?? null, 'libSQL API hardening should provide test transport hook');
    assert_same(true, $policy['configuration_profile']['database_from_config_available'] ?? null, 'database hardening should expose fromConfig');
    assert_true(in_array('database_from_config', $policy['required_verifications'] ?? [], true), 'database hardening should require fromConfig verification');

    $requirements = Adlaire::releaseRequirementMatrix();
    assert_same($policy, $requirements['database_runtime_hardening']['profile'] ?? null, 'release requirement matrix should include database hardening policy');
    assert_same(true, $requirements['database_runtime_hardening']['passed'] ?? null, 'database hardening requirement should pass');

    $audit = Adlaire::audit();
    assert_same($policy, $audit['database_runtime_hardening_policy'] ?? null, 'audit should include database hardening policy');
    assert_same(true, $audit['specification_integrity']['checks']['database_runtime_hardening_policy'] ?? null, 'specification integrity should include database hardening policy');

    $readiness = Adlaire::releaseReadiness();
    assert_same(true, $readiness['checks']['database_runtime_hardening_policy'] ?? null, 'release readiness should include database hardening policy');
    assert_same($policy, Adlaire::distributionManifest()['database_runtime_hardening_policy'] ?? null, 'distribution manifest should include database hardening policy');
}

function test_runtime_operations_hardening(): void
{
    $tmp = sys_get_temp_dir();
    putenv('APP_ENV=production');
    putenv('APP_DEBUG=false');
    putenv('ADLAIRE_REQUIRED=ready');

    $health = Adlaire::health(['writable_paths' => ['tmp' => $tmp]]);
    assert_same('ok', $health['status'] ?? null, 'runtime health should pass basic checks');
    assert_same('v0.266', $health['version'] ?? null, 'runtime health should include version');
    assert_same('ok', $health['checks']['php']['status'] ?? null, 'runtime health should check PHP');
    assert_same('production', $health['checks']['runtime']['environment'] ?? null, 'runtime health should include APP_ENV');
    assert_same('ok', $health['checks']['writable_paths']['tmp']['status'] ?? null, 'runtime health should check writable paths');

    $auditResult = Adlaire::configAudit([
        'required_env' => ['ADLAIRE_REQUIRED'],
        'writable_paths' => ['tmp' => $tmp],
    ]);
    assert_same(true, $auditResult['valid'] ?? null, 'config audit should pass valid production config');
    assert_same(true, $auditResult['checks']['required_env'] ?? null, 'config audit should check required env');
    assert_same(true, $auditResult['checks']['production_debug_disabled'] ?? null, 'config audit should reject production debug mode');

    putenv('APP_DEBUG=true');
    $failingAudit = Adlaire::configAudit();
    assert_same(false, $failingAudit['valid'] ?? null, 'config audit should fail production debug mode');
    assert_same(false, $failingAudit['checks']['production_debug_disabled'] ?? null, 'config audit should mark production debug failure');

    putenv('APP_ENV');
    putenv('APP_DEBUG');
    putenv('ADLAIRE_REQUIRED');

    $policy = Adlaire::runtimeOperationsHardeningPolicy();
    assert_same('v0.266', $policy['version'] ?? null, 'runtime operations policy should include version');
    assert_same('Runtime Operations Hardening', $policy['theme'] ?? null, 'runtime operations policy should define theme');
    assert_same(true, $policy['standard_health_available'] ?? null, 'runtime operations should expose standard health');
    assert_same(true, $policy['config_audit_available'] ?? null, 'runtime operations should expose config audit');
    assert_same(false, $policy['provider_specific_requirement'] ?? null, 'runtime operations should avoid provider-specific requirements');
    assert_same('php -d phar.readonly=0 tests/debug.php', $policy['stable_release_efficiency']['single_official_debug_command'] ?? null, 'runtime operations should expose official debug command');
    assert_same('sh scripts/release-check.sh', $policy['stable_release_efficiency']['single_release_check_command'] ?? null, 'runtime operations should expose single release check command');
    assert_true(in_array('runtime_health', $policy['required_verifications'] ?? [], true), 'runtime operations should require health verification');

    $requirements = Adlaire::releaseRequirementMatrix();
    assert_same($policy, $requirements['runtime_operations_hardening']['profile'] ?? null, 'release requirement matrix should include runtime operations policy');
    assert_same(true, $requirements['runtime_operations_hardening']['passed'] ?? null, 'runtime operations requirement should pass');

    $audit = Adlaire::audit();
    assert_same($policy, $audit['runtime_operations_hardening_policy'] ?? null, 'audit should include runtime operations policy');
    assert_same(true, $audit['specification_integrity']['checks']['runtime_operations_hardening_policy'] ?? null, 'specification integrity should include runtime operations policy');

    $readiness = Adlaire::releaseReadiness();
    assert_same(true, $readiness['checks']['runtime_operations_hardening_policy'] ?? null, 'release readiness should include runtime operations policy');
    assert_same($policy, Adlaire::distributionManifest()['runtime_operations_hardening_policy'] ?? null, 'distribution manifest should include runtime operations policy');
}

function test_operations_dashboard(): void
{
    putenv('ADLAIRE_DASHBOARD_ENABLED');
    putenv('ADLAIRE_DASHBOARD_TOKEN');

    $policy = Adlaire::dashboardPolicy();
    assert_same('v0.266', $policy['version'] ?? null, 'dashboard policy should include version');
    assert_same('Operations Dashboard', $policy['theme'] ?? null, 'dashboard policy should define theme');
    assert_same(false, $policy['default_enabled'] ?? null, 'dashboard should be disabled by default');
    assert_same(true, $policy['read_only'] ?? null, 'dashboard should be read only');
    assert_same(true, $policy['auth_required'] ?? null, 'dashboard should require auth');
    assert_same(false, $policy['command_execution_allowed'] ?? null, 'dashboard should not allow command execution');
    assert_same(false, $policy['writes_allowed'] ?? null, 'dashboard should not allow writes');
    assert_same(false, $policy['external_network_allowed'] ?? null, 'dashboard should not allow external network calls');
    assert_same(false, $policy['secret_values_exposed'] ?? null, 'dashboard should not expose secrets');
    assert_same(false, $policy['json_format_available'] ?? null, 'dashboard should not expose JSON output');
    assert_true(in_array('dashboard_html_only', $policy['required_verifications'] ?? [], true), 'dashboard policy should require HTML-only verification');
    assert_true(in_array('deployment_control', $policy['sections'] ?? [], true), 'dashboard policy should include deployment control section');
    assert_true(in_array('safety_score', $policy['sections'] ?? [], true), 'dashboard policy should include safety score section');
    assert_true(in_array('deploy_history', $policy['sections'] ?? [], true), 'dashboard policy should include deploy history section');
    assert_true(is_file(__DIR__ . '/../public_html/assets/adlaire-ui.css'), 'dashboard UI stylesheet should exist');
    assert_true(str_contains((string)file_get_contents(__DIR__ . '/../Frameworks/Frontend/Dashboard.php'), '/assets/adlaire-ui.css'), 'dashboard should load UI stylesheet');
    assert_same(false, Adlaire::dashboardEnabled(), 'dashboard should be disabled without env flag');
    assert_same(false, Adlaire::dashboardTokenConfigured(), 'dashboard token should be absent by default');

    putenv('ADLAIRE_DASHBOARD_ENABLED=true');
    putenv('ADLAIRE_DASHBOARD_TOKEN=secret-token');
    assert_same(true, Adlaire::dashboardEnabled(), 'dashboard should read enabled env flag');
    assert_same(true, Adlaire::dashboardTokenConfigured(), 'dashboard should detect configured token');

    putenv('ADLAIRE_DASHBOARD_ENABLED');
    putenv('ADLAIRE_DASHBOARD_TOKEN');

    $requirements = Adlaire::releaseRequirementMatrix();
    assert_same($policy, $requirements['operations_dashboard']['profile'] ?? null, 'release requirement matrix should include dashboard policy');
    assert_same(true, $requirements['operations_dashboard']['passed'] ?? null, 'dashboard requirement should pass');

    $audit = Adlaire::audit();
    assert_same($policy, $audit['dashboard_policy'] ?? null, 'audit should include dashboard policy');
    assert_same(true, $audit['specification_integrity']['checks']['dashboard_policy'] ?? null, 'specification integrity should include dashboard policy');

    $readiness = Adlaire::releaseReadiness();
    assert_same(true, $readiness['checks']['dashboard_policy'] ?? null, 'release readiness should include dashboard policy');
    assert_same($policy, Adlaire::distributionManifest()['dashboard_policy'] ?? null, 'distribution manifest should include dashboard policy');
}

function test_configuration_file_policy(): void
{
    $policy = Adlaire::configurationFilePolicy();
    assert_same('v0.266', $policy['version'] ?? null, 'configuration file policy should include version');
    assert_same('Configuration File Prohibition', $policy['theme'] ?? null, 'configuration file policy should define theme');
    assert_same(false, $policy['framework_configuration_files_allowed'] ?? null, 'framework configuration files should be prohibited');
    assert_same(false, $policy['env_files_allowed'] ?? null, 'env files should be prohibited');
    assert_same(false, $policy['env_loader_allowed'] ?? null, 'env loader should be prohibited');
    assert_same(true, $policy['runtime_array_config_allowed'] ?? null, 'runtime array config should remain allowed');
    assert_same(true, $policy['environment_variables_allowed'] ?? null, 'environment variables should remain allowed');
    assert_same(true, $policy['config_repository_allowed'] ?? null, 'ConfigRepository should remain allowed');
    assert_same(true, $policy['json_metadata_exception'] ?? null, 'JSON metadata exception should be allowed');
    assert_true(in_array('manifest', $policy['json_allowed_uses'] ?? [], true), 'JSON manifest metadata should be allowed');
    assert_same(false, $policy['json_for_secret_configuration_allowed'] ?? null, 'JSON secret configuration should be prohibited');
    assert_true(in_array('.env*', $policy['prohibited_patterns'] ?? [], true), 'env pattern should be prohibited');
    assert_true(in_array('*.ini', $policy['prohibited_patterns'] ?? [], true), 'ini pattern should be prohibited');
    assert_true(in_array('*.conf', $policy['prohibited_patterns'] ?? [], true), 'conf pattern should be prohibited');
    assert_true(in_array('*.yaml', $policy['prohibited_patterns'] ?? [], true), 'yaml pattern should be prohibited');
    assert_true(in_array('docker-compose.xserver.yml', $policy['tooling_exceptions'] ?? [], true), 'compose tooling exception should be documented');

    foreach ($policy['removed_files'] ?? [] as $file) {
        assert_true(!is_file((string)$file), "removed config file should be absent: {$file}");
    }

    $requirements = Adlaire::releaseRequirementMatrix();
    assert_same($policy, $requirements['configuration_file_policy']['profile'] ?? null, 'release requirement matrix should include configuration file policy');
    assert_same(true, $requirements['configuration_file_policy']['passed'] ?? null, 'configuration file policy requirement should pass');

    $audit = Adlaire::audit();
    assert_same($policy, $audit['configuration_file_policy'] ?? null, 'audit should include configuration file policy');
    assert_same(true, $audit['specification_integrity']['checks']['configuration_file_policy'] ?? null, 'specification integrity should include configuration file policy');

    $readiness = Adlaire::releaseReadiness();
    assert_same(true, $readiness['checks']['configuration_file_policy'] ?? null, 'release readiness should include configuration file policy');
    assert_same($policy, Adlaire::distributionManifest()['configuration_file_policy'] ?? null, 'distribution manifest should include configuration file policy');
}

function test_deployment_preflight_policy(): void
{
    $policy = Adlaire::deploymentPreflightPolicy();
    assert_same('v0.266', $policy['version'] ?? null, 'deployment preflight policy should include version');
    assert_same('Deployment Preflight Guard', $policy['theme'] ?? null, 'deployment preflight policy should define theme');
    assert_same('DeploymentCore.php', $policy['component'] ?? null, 'deployment preflight should target DeploymentCore');
    assert_same(true, $policy['compatibility_guaranteed'] ?? null, 'deployment preflight should guarantee deployment compatibility');
    assert_same(false, $policy['breaking_changes_allowed'] ?? null, 'deployment preflight should forbid deployment breaking changes');
    assert_same(true, $policy['read_only'] ?? null, 'deployment preflight should be read only');
    assert_same(false, $policy['command_execution_allowed'] ?? null, 'deployment preflight should avoid command execution');
    assert_true(in_array('deploy_allowlist_configured', $policy['checks'] ?? [], true), 'deployment preflight should check deploy allowlist');
    assert_true(in_array('deployment_preflight_ready', $policy['required_verifications'] ?? [], true), 'deployment preflight should require readiness verification');

    $requirements = Adlaire::releaseRequirementMatrix();
    assert_same($policy, $requirements['deployment_preflight_policy']['profile'] ?? null, 'release requirement matrix should include deployment preflight policy');
    assert_same(true, $requirements['deployment_preflight_policy']['passed'] ?? null, 'deployment preflight requirement should pass');

    $audit = Adlaire::audit();
    assert_same($policy, $audit['deployment_preflight_policy'] ?? null, 'audit should include deployment preflight policy');
    assert_same(true, $audit['specification_integrity']['checks']['deployment_preflight_policy'] ?? null, 'specification integrity should include deployment preflight policy');

    $readiness = Adlaire::releaseReadiness();
    assert_same(true, $readiness['checks']['deployment_preflight_policy'] ?? null, 'release readiness should include deployment preflight policy');
    assert_same($policy, Adlaire::distributionManifest()['deployment_preflight_policy'] ?? null, 'distribution manifest should include deployment preflight policy');
}

function test_deployment_plan_preview_policy(): void
{
    $policy = Adlaire::deploymentPlanPreviewPolicy();
    assert_same('v0.266', $policy['version'] ?? null, 'deployment plan preview policy should include version');
    assert_same('Deployment Plan Preview', $policy['theme'] ?? null, 'deployment plan preview policy should define theme');
    assert_same('DeploymentCore.php', $policy['component'] ?? null, 'deployment plan preview should target DeploymentCore');
    assert_same(true, $policy['read_only'] ?? null, 'deployment plan preview should be read only');
    assert_same(false, $policy['command_execution_allowed'] ?? null, 'deployment plan preview should avoid command execution');
    assert_same(false, $policy['writes_allowed'] ?? null, 'deployment plan preview should avoid writes');
    assert_true(in_array('added', $policy['classifications'] ?? [], true), 'deployment plan preview should classify added files');
    assert_true(in_array('modified', $policy['classifications'] ?? [], true), 'deployment plan preview should classify modified files');
    assert_true(in_array('skipped', $policy['classifications'] ?? [], true), 'deployment plan preview should classify skipped files');

    $requirements = Adlaire::releaseRequirementMatrix();
    assert_same($policy, $requirements['deployment_plan_preview_policy']['profile'] ?? null, 'release requirement matrix should include deployment plan preview policy');
    assert_same(true, $requirements['deployment_plan_preview_policy']['passed'] ?? null, 'deployment plan preview requirement should pass');

    $audit = Adlaire::audit();
    assert_same($policy, $audit['deployment_plan_preview_policy'] ?? null, 'audit should include deployment plan preview policy');
    assert_same(true, $audit['specification_integrity']['checks']['deployment_plan_preview_policy'] ?? null, 'specification integrity should include deployment plan preview policy');

    $readiness = Adlaire::releaseReadiness();
    assert_same(true, $readiness['checks']['deployment_plan_preview_policy'] ?? null, 'release readiness should include deployment plan preview policy');
    assert_same($policy, Adlaire::distributionManifest()['deployment_plan_preview_policy'] ?? null, 'distribution manifest should include deployment plan preview policy');
}

function test_deployment_compatibility_snapshot_policy(): void
{
    $policy = Adlaire::deploymentCompatibilitySnapshotPolicy();
    assert_same('v0.266', $policy['version'] ?? null, 'deployment compatibility snapshot policy should include version');
    assert_same('Deployment Compatibility Snapshot', $policy['theme'] ?? null, 'deployment compatibility snapshot policy should define theme');
    assert_same('DeploymentCore.php', $policy['component'] ?? null, 'deployment compatibility snapshot should target DeploymentCore');
    assert_same(true, $policy['compatibility_guaranteed'] ?? null, 'deployment compatibility snapshot should guarantee deployment compatibility');
    assert_same(false, $policy['breaking_changes_allowed'] ?? null, 'deployment compatibility snapshot should forbid deployment breaking changes');
    assert_same(true, $policy['read_only'] ?? null, 'deployment compatibility snapshot should be read only');
    assert_same(false, $policy['command_execution_allowed'] ?? null, 'deployment compatibility snapshot should avoid command execution');
    assert_same(false, $policy['writes_allowed'] ?? null, 'deployment compatibility snapshot should avoid writes');
    assert_true(in_array('deployment_core_component', $policy['snapshot_evidence'] ?? [], true), 'deployment compatibility snapshot should include component evidence');
    assert_true(in_array('preflight_ready', $policy['snapshot_evidence'] ?? [], true), 'deployment compatibility snapshot should include preflight evidence');
    assert_true(in_array('deployment_compatibility_snapshot_ready', $policy['required_verifications'] ?? [], true), 'deployment compatibility snapshot should require readiness verification');

    $requirements = Adlaire::releaseRequirementMatrix();
    assert_same($policy, $requirements['deployment_compatibility_snapshot_policy']['profile'] ?? null, 'release requirement matrix should include deployment compatibility snapshot policy');
    assert_same(true, $requirements['deployment_compatibility_snapshot_policy']['passed'] ?? null, 'deployment compatibility snapshot requirement should pass');

    $audit = Adlaire::audit();
    assert_same($policy, $audit['deployment_compatibility_snapshot_policy'] ?? null, 'audit should include deployment compatibility snapshot policy');
    assert_same(true, $audit['specification_integrity']['checks']['deployment_compatibility_snapshot_policy'] ?? null, 'specification integrity should include deployment compatibility snapshot policy');

    $readiness = Adlaire::releaseReadiness();
    assert_same(true, $readiness['checks']['deployment_compatibility_snapshot_policy'] ?? null, 'release readiness should include deployment compatibility snapshot policy');
    assert_same($policy, Adlaire::distributionManifest()['deployment_compatibility_snapshot_policy'] ?? null, 'distribution manifest should include deployment compatibility snapshot policy');
}

function assert_policy_release_connection(string $key, array $policy): void
{
    $requirements = Adlaire::releaseRequirementMatrix();
    assert_same($policy, $requirements[$key]['profile'] ?? null, "release requirement matrix should include policy: {$key}");
    assert_same(true, $requirements[$key]['passed'] ?? null, "release requirement should pass: {$key}");

    $audit = Adlaire::audit();
    assert_same($policy, $audit[$key] ?? null, "audit should include policy: {$key}");
    assert_same(true, $audit['specification_integrity']['checks'][$key] ?? null, "specification integrity should include policy: {$key}");

    $readiness = Adlaire::releaseReadiness();
    assert_same(true, $readiness['checks'][$key] ?? null, "release readiness should include policy: {$key}");
    assert_same($policy, Adlaire::distributionManifest()[$key] ?? null, "distribution manifest should include policy: {$key}");
}

function test_deployment_control_policy_suite(): void
{
    $rollback = Adlaire::deploymentRollbackPreviewPolicy();
    assert_same('v0.266', $rollback['version'] ?? null, 'rollback preview policy should include version');
    assert_same('Deployment Rollback Preview', $rollback['theme'] ?? null, 'rollback preview policy should define theme');
    assert_same(true, $rollback['read_only'] ?? null, 'rollback preview policy should be read only');
    assert_true(in_array('restore', $rollback['classifications'] ?? [], true), 'rollback preview should classify restore files');
    assert_policy_release_connection('deployment_rollback_preview_policy', $rollback);

    $score = Adlaire::deploymentSafetyScorePolicy();
    assert_same('Deployment Safety Score', $score['theme'] ?? null, 'safety score policy should define theme');
    assert_same(70, $score['minimum_release_score'] ?? null, 'safety score policy should define minimum score');
    assert_true(in_array('safe', $score['grades'] ?? [], true), 'safety score policy should define safe grade');
    assert_policy_release_connection('deployment_safety_score_policy', $score);

    $control = Adlaire::dashboardControlVisibilityPolicy();
    assert_same('Dashboard Control Visibility', $control['theme'] ?? null, 'dashboard control policy should define theme');
    assert_true(in_array('deployment_control', $control['sections'] ?? [], true), 'dashboard control policy should include deployment control section');
    assert_policy_release_connection('dashboard_control_visibility_policy', $control);

    $history = Adlaire::deploymentHistoryVisualizationPolicy();
    assert_same('Deployment History Visualization', $history['theme'] ?? null, 'history visualization policy should define theme');
    assert_true(in_array('deploy_history', $history['sections'] ?? [], true), 'history visualization policy should include deploy history');
    assert_policy_release_connection('deployment_history_visualization_policy', $history);

    $report = Adlaire::deploymentControlReportPolicy();
    assert_same('Deployment Control Report', $report['theme'] ?? null, 'control report policy should define theme');
    assert_true(in_array('safety_score', $report['sections'] ?? [], true), 'control report should include safety score');
    assert_policy_release_connection('deployment_control_report_policy', $report);

    $gate = Adlaire::stableReleaseGatePolicy();
    assert_same('Stable Release Gate', $gate['theme'] ?? null, 'stable release gate policy should define theme');
    assert_same(70, $gate['minimum_deployment_safety_score'] ?? null, 'stable release gate should define minimum score');
    assert_policy_release_connection('stable_release_gate_policy', $gate);

    $ui = Adlaire::uiFrameworkPolicy();
    assert_same('Adlaire UI Framework', $ui['theme'] ?? null, 'UI framework policy should define theme');
    assert_same('Frameworks/CSS/adlaire-ui.css', $ui['source_asset'] ?? null, 'UI framework policy should define source asset');
    assert_same('public_html/assets/adlaire-ui.css', $ui['asset'] ?? null, 'UI framework policy should define asset');
    assert_same(false, $ui['configuration_files_allowed'] ?? null, 'UI framework should not allow configuration files');
    assert_policy_release_connection('ui_framework_policy', $ui);

    $controlSnapshot = Adlaire::deploymentControlSnapshotPolicy();
    assert_same('Deployment Control Snapshot', $controlSnapshot['theme'] ?? null, 'control snapshot policy should define theme');
    assert_same(true, $controlSnapshot['json_audit_artifact_allowed'] ?? null, 'control snapshot should allow JSON audit artifact');
    assert_same(false, $controlSnapshot['configuration_files_allowed'] ?? null, 'control snapshot should not allow configuration files');
    assert_policy_release_connection('deployment_control_snapshot_policy', $controlSnapshot);

    $scoreDetails = Adlaire::deploymentSafetyScoreDetailsPolicy();
    assert_same('Deployment Safety Score Details', $scoreDetails['theme'] ?? null, 'score details policy should define theme');
    assert_true(in_array('critical', $scoreDetails['severity_levels'] ?? [], true), 'score details should define severity levels');
    assert_policy_release_connection('deployment_safety_score_details_policy', $scoreDetails);

    $rollbackState = Adlaire::rollbackStatePreviewPolicy();
    assert_same('Rollback State Preview', $rollbackState['theme'] ?? null, 'rollback state policy should define theme');
    assert_same(true, $rollbackState['projected_state_available'] ?? null, 'rollback state policy should expose projected state');
    assert_policy_release_connection('rollback_state_preview_policy', $rollbackState);

    $releaseGateView = Adlaire::dashboardReleaseGateViewPolicy();
    assert_same('Dashboard Release Gate View', $releaseGateView['theme'] ?? null, 'dashboard release gate policy should define theme');
    assert_true(in_array('release_gate', $releaseGateView['sections'] ?? [], true), 'dashboard release gate should include release gate section');
    assert_policy_release_connection('dashboard_release_gate_view_policy', $releaseGateView);

    $timeline = Adlaire::deploymentTimelinePolicy();
    assert_same('Deployment Timeline View', $timeline['theme'] ?? null, 'deployment timeline policy should define theme');
    assert_true(in_array('preflight', $timeline['events'] ?? [], true), 'deployment timeline should include preflight');
    assert_policy_release_connection('deployment_timeline_policy', $timeline);

    $uiExpansion = Adlaire::uiFrameworkExpansionPolicy();
    assert_same('Adlaire UI Framework Expansion', $uiExpansion['theme'] ?? null, 'UI expansion policy should define theme');
    assert_true(in_array('status_layout', $uiExpansion['components'] ?? [], true), 'UI expansion should include status layout');
    assert_policy_release_connection('ui_framework_expansion_policy', $uiExpansion);

    $bundle = Adlaire::releaseEvidenceBundlePolicy();
    assert_same('Release Evidence Bundle', $bundle['theme'] ?? null, 'release evidence bundle policy should define theme');
    assert_true(in_array('control_report', $bundle['required_evidence'] ?? [], true), 'release evidence bundle should include control report');
    assert_policy_release_connection('release_evidence_bundle_policy', $bundle);

    $diff = Adlaire::deploymentControlDiffPolicy();
    assert_same('Deployment Control Diff', $diff['theme'] ?? null, 'deployment control diff policy should define theme');
    assert_true(in_array('safety_score', $diff['sections'] ?? [], true), 'deployment control diff should include safety score');
    assert_policy_release_connection('deployment_control_diff_policy', $diff);

    $candidate = Adlaire::stableReleaseCandidateGatePolicy();
    assert_same('Stable Release Candidate Gate', $candidate['theme'] ?? null, 'stable release candidate gate policy should define theme');
    assert_same(70, $candidate['minimum_deployment_safety_score'] ?? null, 'stable release candidate gate should define minimum score');
    assert_policy_release_connection('stable_release_candidate_gate_policy', $candidate);

    $apiRemoval = Adlaire::apiRemovalPolicy();
    assert_same('API Removal', $apiRemoval['theme'] ?? null, 'API removal policy should define theme');
    assert_same(false, $apiRemoval['public_api_available'] ?? null, 'API removal should disable public API');
    assert_same(false, $apiRemoval['json_response_available'] ?? null, 'API removal should disable JSON responses');
    assert_same(false, $apiRemoval['json_request_parsing_available'] ?? null, 'API removal should disable JSON request parsing');
    assert_same(false, $apiRemoval['cors_available'] ?? null, 'API removal should disable CORS');
    assert_same(true, $apiRemoval['json_metadata_exception_retained'] ?? null, 'API removal should retain JSON metadata exception');
    assert_same(true, $apiRemoval['internal_libsql_api_allowed'] ?? null, 'API removal should allow internal libSQL API transport');
    assert_same(false, method_exists(Response::class, 'json'), 'Response::json should be removed');
    assert_same(false, method_exists(Response::class, 'success'), 'Response::success should be removed');
    assert_same(false, method_exists(Response::class, 'paginated'), 'Response::paginated should be removed');
    assert_same(false, method_exists(Response::class, 'cors'), 'Response::cors should be removed');
    assert_same(false, method_exists(Request::class, 'isJson'), 'Request::isJson should be removed');
    assert_same(false, method_exists(Request::class, 'expectsJson'), 'Request::expectsJson should be removed');
    assert_policy_release_connection('api_removal_policy', $apiRemoval);

    $documentation = Adlaire::documentationConsistencyPolicy();
    assert_same('Repository Documentation Consistency', $documentation['theme'] ?? null, 'documentation consistency policy should define theme');
    assert_same(false, $documentation['xserver_required'] ?? null, 'documentation consistency should not require Xserver');
    assert_same(false, $documentation['mysql_support_planned'] ?? null, 'documentation consistency should not plan MySQL');
    assert_true(in_array('sqlite', $documentation['database_axis'] ?? [], true), 'documentation consistency should keep SQLite axis');
    assert_true(in_array('internal_libsql_api_transport', $documentation['database_axis'] ?? [], true), 'documentation consistency should keep internal libSQL API transport axis');
    assert_same(false, $documentation['framework_configuration_files_allowed'] ?? null, 'documentation consistency should prohibit framework configuration files');
    assert_same(false, $documentation['json_configuration_files_allowed'] ?? null, 'documentation consistency should not allow JSON configuration files');
    assert_same(false, $documentation['public_api_available'] ?? null, 'documentation consistency should not restore public API');
    assert_true(in_array('README.md', $documentation['checked_documents'] ?? [], true), 'documentation consistency should check README');
    assert_true(in_array('docs/xserver-production-equivalent.md', $documentation['checked_documents'] ?? [], true), 'documentation consistency should check Xserver profile document');

    $readme = file_get_contents(__DIR__ . '/../README.md');
    assert_true($readme !== false, 'README should be readable');
    assert_true(stripos($readme, 'source of truth') !== false, 'README should point to the source of truth');
    assert_true(stripos($readme, 'Public API: removed') !== false, 'README should document public API removal');
    assert_true(stripos($readme, 'Configuration files') !== false, 'README should document configuration file prohibition');

    $document = file_get_contents(__DIR__ . '/../docs/xserver-production-equivalent.md');
    assert_true($document !== false, 'Xserver profile document should be readable');
    assert_same(false, stripos($document, 'mysql-compatible') !== false, 'Xserver profile document should not contain stale MySQL-compatible guidance');
    assert_same(false, stripos($document, 'ignored deployment-specific env file') !== false, 'Xserver profile document should not allow ignored env files');
    assert_true(stripos($document, 'MySQL support is not planned') !== false, 'Xserver profile document should state MySQL is not planned');
    assert_true(stripos($document, 'Framework configuration files') !== false, 'Xserver profile document should document configuration file prohibition');
    assert_policy_release_connection('documentation_consistency_policy', $documentation);
}

function test_router(): void
{
    $router = new Router();
    $router->get('/users/{id}', static function (Request $request): void {
        throw new DebugRouteHit($request->param());
    })->where('id', '\d+')->name('users.show');

    assert_same('/users/42', $router->url('users.show', ['id' => 42]), 'named route should build URL');
    assert_true($router->has('users.show'), 'router should report named route existence');
    assert_same(['GET'], $router->methodsFor('/users/42'), 'router should report matching methods');
    assert_true(count($router->routes()) >= 1, 'router should expose registered routes');

    $order = [];
    $router->middleware(static function (Request $request, Response $response, callable $next) use (&$order): mixed {
        $order[] = 'global';
        return $next($request, $response);
    });
    $router->get('/middleware', static function () use (&$order): void {
        $order[] = 'handler';
        throw new DebugRouteHit(['order' => $order]);
    })->middleware(static function (Request $request, Response $response, callable $next) use (&$order): mixed {
        $order[] = 'route';
        return $next($request, $response);
    });
    assert_same(1, $router->routes()[1]['middleware_count'] ?? null, 'route should expose middleware count');

    try {
        $router->dispatch(make_request('GET', '/middleware'), new Response());
    } catch (DebugRouteHit $hit) {
        assert_same(['global', 'route', 'handler'], $hit->params['order'] ?? null, 'router middleware should run in order');
    }

    $groupOrder = [];
    $router->group('/system', static function (Router $router) use (&$groupOrder): void {
        $router->get('/status', static function () use (&$groupOrder): void {
            $groupOrder[] = 'handler';
            throw new DebugRouteHit(['group_order' => $groupOrder]);
        });
    }, [
        static function (Request $request, Response $response, callable $next) use (&$groupOrder): mixed {
            $groupOrder[] = 'group';
            return $next($request, $response);
        },
    ]);

    try {
        $router->dispatch(make_request('GET', '/system/status'), new Response());
    } catch (DebugRouteHit $hit) {
        assert_same(['group', 'handler'], $hit->params['group_order'] ?? null, 'router group middleware should run in order');
    }

    $router->options('/users/{id}', static function (): void {
        throw new DebugRouteHit(['options' => 'ok']);
    })->where('id', '\d+');

    try {
        $router->dispatch(make_request('GET', '/users/42'), new Response());
    } catch (DebugRouteHit $hit) {
        assert_same(['id' => '42'], $hit->params, 'router should capture constrained param');
    }

    try {
        $router->dispatch(make_request('OPTIONS', '/users/42'), new Response());
    } catch (DebugRouteHit $hit) {
        assert_same(['options' => 'ok'], $hit->params, 'router should dispatch OPTIONS routes');
        return;
    }

    throw new DebugTestFailure('router did not hit expected routes');
}

function test_general_framework_support(): void
{
    $config = new ConfigRepository(['app' => ['name' => 'adlaire']]);
    assert_same('adlaire', $config->get('app.name'), 'config repository should read dot keys');
    assert_same(false, $config->has('app.debug'), 'config repository should report missing keys');
    $config->set('app.debug', true)->merge(['db' => ['driver' => 'sqlite'], 'http' => ['port' => '8080']]);
    assert_same(true, $config->get('app.debug'), 'config repository should write dot keys');
    assert_same('sqlite', $config->get('db.driver'), 'config repository should merge arrays');
    assert_same('sqlite', $config->required('db.driver'), 'config repository should require present keys');
    assert_same(true, $config->boolean('app.debug'), 'config repository should cast booleans');
    assert_same(8080, $config->integer('http.port'), 'config repository should cast integers');

    $pipeline = (new MiddlewarePipeline())
        ->through([
            static fn(array $payload, callable $next): array => $next([...$payload, 'a']),
            static fn(array $payload, callable $next): array => $next([...$payload, 'b']),
        ]);
    assert_same(['a', 'b', 'done'], $pipeline->process([], static fn(array $payload): array => [...$payload, 'done']), 'middleware pipeline should process in order');
    assert_same(2, count($pipeline->pipes()), 'middleware pipeline should expose registered pipes');

    $data = [];
    AdlaireSupport::dataSet($data, 'user.name', 'Auris');
    assert_same('Auris', AdlaireSupport::dataGet($data, 'user.name'), 'support helper should set and get dot data');
    assert_same('auris-module', AdlaireSupport::slug('Auris Module'), 'support helper should generate slug');
    assert_same('auris_module', AdlaireSupport::snake('AurisModule'), 'support helper should generate snake case');
    assert_same('ConfigRepository', AdlaireSupport::classBasename(ConfigRepository::class), 'support helper should read class basename');
}

function test_response_security(): void
{
    $response = new Response();
    $response->headers(['X-App' => 'adlaire'])->securityHeaders();
    $headers = $response->headers();
    assert_same('adlaire', $headers['X-App'] ?? null, 'response should set multiple headers');
    assert_same('nosniff', $headers['X-Content-Type-Options'] ?? null, 'securityHeaders should set nosniff');
    assert_same('DENY', $headers['X-Frame-Options'] ?? null, 'securityHeaders should set frame options');
    assert_same('no-referrer', $headers['Referrer-Policy'] ?? null, 'securityHeaders should set referrer policy');
    assert_true(isset($headers['Permissions-Policy']), 'securityHeaders should set permissions policy');
}

function test_database(): void
{
    if (!extension_loaded('pdo_sqlite')) {
        echo "SKIP database: pdo_sqlite extension is not loaded\n";
        return;
    }

    $database = Database::connect(':memory:');
    $runtimeProfile = $database->runtimeProfile();
    assert_same('sqlite', $runtimeProfile['driver'] ?? null, 'runtime profile should identify SQLite driver');
    assert_same(true, $runtimeProfile['foreign_keys'] ?? null, 'SQLite foreign keys should be enabled by default');
    assert_same(5000, $runtimeProfile['busy_timeout_ms'] ?? null, 'SQLite busy timeout should default to 5000ms');
    assert_same('memory', $runtimeProfile['journal_mode'] ?? null, 'memory SQLite should not force WAL mode');
    $database->enableQueryLog(0.0);
    $database->statement('CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL, score INTEGER NOT NULL, nickname TEXT NULL)');
    $database->statement('CREATE TABLE posts (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER NOT NULL, title TEXT NOT NULL)');

    $database->table('users')->insert([
        ['name' => 'alice', 'score' => 10, 'nickname' => null],
        ['name' => 'bob', 'score' => 20, 'nickname' => 'b'],
    ]);
    $database->table('posts')->insert([
        ['user_id' => 1, 'title' => 'a'],
        ['user_id' => 1, 'title' => 'b'],
        ['user_id' => 2, 'title' => 'c'],
    ]);

    assert_same(2, $database->table('users')->count(), 'count aggregate should return int');
    assert_same(30, $database->table('users')->sum('score'), 'sum aggregate should return numeric value');
    assert_same('bob', $database->table('users')->where('score', '>', 10)->first()['name'] ?? null, 'where and first should find row');
    assert_true($database->queryLog() !== [], 'query log should capture executed statements');
    assert_true(count($database->queryLog()) <= 1000, 'query log should respect default max size');

    $raw = $database->table('users AS u')->selectRaw('u.name')->whereRaw('u.score >= ?', [10])->orderBy('u.id')->first();
    assert_same('alice', $raw['name'] ?? null, 'raw select/where and table alias should work');

    $unionRows = $database->table('users')->select('name')->where('name', 'alice')
        ->union($database->table('users')->select('name')->where('name', 'bob'))
        ->get();
    assert_same(2, count($unionRows), 'union should combine query results');

    $page = $database->table('users')->orderBy('id')->paginate(1, 2);
    assert_same(2, $page['total'], 'paginate should include total');
    assert_same(2, $page['current_page'], 'paginate should include current page');
    assert_same('bob', $page['data'][0]['name'] ?? null, 'paginate should return page data');

    $builder = $database->table('users')->orderBy('id')->limit(2);
    $builder->paginate(1, 1);
    assert_same(2, count($builder->get()), 'paginate should not mutate existing limit state');
    assert_same(1, $database->table('users')->whereNull('nickname')->count(), 'whereNull should count null values');
    assert_same(1, $database->table('users')->whereNotNull('nickname')->count(), 'whereNotNull should count non-null values');
    assert_same(2, $database->table('users')->whereBetween('score', 10, 20)->count(), 'whereBetween should include range endpoints');
    assert_true($database->table('users')->where('name', 'alice')->exists(), 'exists should return true for matching row');
    assert_true(!$database->table('users')->where('name', 'missing')->exists(), 'exists should return false for missing row');
    $insertedId = $database->table('users')->insertGetId(['name' => 'erin', 'score' => 50, 'nickname' => null]);
    assert_true($insertedId > 0, 'insertGetId should return inserted primary key');
    assert_same(['alice', 'bob', 'erin'], $database->table('users')->orderBy('id')->limit(3)->pluck('name'), 'pluck should return column values');
    assert_same('alice', $database->table('users')->orderBy('id')->value('name'), 'value should return first column value');

    $withPosts = $database->table('users')->with('posts', 'posts', 'id', 'user_id')->orderBy('id')->get();
    assert_same(2, count($withPosts[0]['posts']), 'eager loading should attach related rows');

    try {
        $database->table('users')->update(['score' => 0]);
        throw new DebugTestFailure('update without WHERE should fail');
    } catch (RuntimeException) {
    }

    try {
        $database->table('users')->delete();
        throw new DebugTestFailure('delete without WHERE should fail');
    } catch (RuntimeException) {
    }

    assert_true($database->table('users')->allowWithoutWhere()->update(['score' => 1]) > 0, 'explicit full update should pass');

    $unique = new Validator();
    assert_true(
        !$unique->validate(['name' => 'alice'], ['name' => 'unique:users,name']),
        'unique validation should fail existing DB row'
    );

    $database->transaction(function (Database $db): void {
        $db->table('users')->insert(['name' => 'carol', 'score' => 30]);
        $db->transaction(function (Database $nested): void {
            $nested->table('users')->insert(['name' => 'dave', 'score' => 40]);
        });
    });

    assert_same(5, $database->table('users')->count(), 'nested transaction should commit');

    $path = sys_get_temp_dir() . '/adlaire_file_url.sqlite';
    if (is_file($path)) {
        assert_true(unlink($path), 'old file URL database should be removed');
    }
    $fileDatabase = new Database('file:' . $path);
    $fileProfile = $fileDatabase->runtimeProfile();
    assert_same('sqlite', $fileProfile['driver'] ?? null, 'file runtime profile should identify SQLite');
    assert_same('wal', $fileProfile['journal_mode'] ?? null, 'file SQLite should default to WAL mode');
    $fileDatabase->statement('CREATE TABLE checks (id INTEGER PRIMARY KEY, name TEXT NOT NULL)');
    $fileDatabase->table('checks')->insert(['id' => 1, 'name' => 'file-url']);
    assert_same('file-url', $fileDatabase->table('checks')->first()['name'] ?? null, 'file: SQLite URL should work');

    Database::resetConnectionsForTesting();
    Database::addConnection('one', ':memory:', true);
    assert_true(Database::connection('one') instanceof Database, 'test connection reset should allow fresh named connection');
    Database::resetConnectionsForTesting();
    $configuredPath = sys_get_temp_dir() . '/adlaire_configured.sqlite';
    if (is_file($configuredPath)) {
        assert_true(unlink($configuredPath), 'old configured database should be removed');
    }
    $configured = Database::fromConfig(new ConfigRepository([
        'name' => 'configured',
        'url' => 'file:' . $configuredPath,
        'default' => true,
        'options' => [
            'sqlite' => [
                'busy_timeout_ms' => 2500,
                'journal_mode' => 'DELETE',
            ],
        ],
    ]));
    assert_same($configured, Database::default(), 'Database::fromConfig should register the default connection');
    assert_same(2500, $configured->runtimeProfile()['busy_timeout_ms'] ?? null, 'fromConfig should apply SQLite options');
    assert_same('delete', $configured->runtimeProfile()['journal_mode'] ?? null, 'fromConfig should apply journal mode option');
    Database::resetConnectionsForTesting();

    try {
        new Database('mysql:host=localhost;dbname=adlaire');
        throw new DebugTestFailure('MySQL DSN should not be supported');
    } catch (InvalidArgumentException) {
    }

    $captured = [];
    $libsql = new Database('https://db.example.com', 'token-123', [
        'timeout_seconds' => 7,
        'retries' => 1,
        'token_required' => true,
        'consistency' => 'eventual',
        'transport' => static function (string $endpoint, string $payload, array $headers, int $timeout) use (&$captured): array {
            $captured = compact('endpoint', 'payload', 'headers', 'timeout');
            return [
                'status' => 200,
                'body' => '{"results":[{"columns":["name"],"rows":[["libsql-api"]],"affected_row_count":1}]}',
            ];
        },
    ]);
    $libsqlProfile = $libsql->runtimeProfile();
    assert_same('libsql-api', $libsqlProfile['driver'] ?? null, 'libSQL API runtime profile should identify driver');
    assert_same(7, $libsqlProfile['timeout_seconds'] ?? null, 'libSQL API timeout should be configurable');
    assert_same(1, $libsqlProfile['retries'] ?? null, 'libSQL API retries should be configurable');
    assert_same(true, $libsqlProfile['token_required'] ?? null, 'libSQL API token required profile should be retained');
    assert_same('eventual', $libsqlProfile['consistency'] ?? null, 'libSQL API consistency should be configurable');
    assert_same('libsql-api', $libsql->statement('SELECT ?', ['libsql-api'])->fetch()['name'] ?? null, 'libSQL API response should map rows to columns');
    assert_same('https://db.example.com/v2/pipeline', $captured['endpoint'] ?? null, 'libSQL API should target pipeline endpoint');
    assert_same(7, $captured['timeout'] ?? null, 'libSQL API transport should receive timeout');
    assert_true(in_array('Authorization: Bearer token-123', $captured['headers'] ?? [], true), 'libSQL API transport should send bearer token');
    $payload = json_decode((string)($captured['payload'] ?? ''), true);
    assert_same('SELECT ?', $payload['statements'][0]['q'] ?? null, 'libSQL API payload should include SQL');
    assert_same(['libsql-api'], $payload['statements'][0]['params'] ?? null, 'libSQL API payload should include bindings');

    $fallback = new Database('wss://db.example.com', null, [
        'transport' => static fn(): array => ['status' => 200, 'body' => '{"results":[{"columns":[],"rows":[]}]}'],
    ]);
    assert_same('libsql-websocket-fallback', $fallback->runtimeProfile()['driver'] ?? null, 'wss libSQL URL should use API fallback profile');

    try {
        new Database('https://db.example.com', null, [
            'token_required' => true,
            'transport' => static fn(): array => ['status' => 200, 'body' => '{}'],
        ]);
        throw new DebugTestFailure('libSQL API token_required should reject missing token');
    } catch (InvalidArgumentException) {
    }
}

function test_logger(): void
{
    $file = sys_get_temp_dir() . '/adlaire_debug.log';
    if (is_file($file)) {
        assert_true(unlink($file), 'old debug log should be removed');
    }
    if (is_file($file . '.hmac')) {
        assert_true(unlink($file . '.hmac'), 'old debug log hmac should be removed');
    }

    $logger = new Logger($file, 'DEBUG', 'secret');
    $logger->info('debug logger test', [
        'password' => 'hidden',
        'access_token_value' => 'token-hidden',
        'nested' => [
            'clientSecret' => 'secret-hidden',
        ],
    ]);
    $content = (string)file_get_contents($file);
    assert_true(str_contains($content, '[masked]'), 'logger should mask configured fields');
    assert_true(!str_contains($content, 'token-hidden'), 'logger should mask keys containing token');
    assert_true(!str_contains($content, 'secret-hidden'), 'logger should mask nested keys containing secret');
    assert_true(is_file($file . '.hmac'), 'logger should write hmac file');

    $debugFile = sys_get_temp_dir() . '/adlaire_request_debug.log';
    if (is_file($debugFile)) {
        assert_true(unlink($debugFile), 'old request debug log should be removed');
    }
    $request = make_request('GET', '/debug');
    $request->setRouteInfo([
        'matched' => true,
        'path' => '/debug',
        'method' => 'GET',
        'name' => 'debug.show',
        'params' => [],
    ]);
    $response = (new Response())->status(202)->header('X-Debug', 'yes');
    $debugLogger = new Logger($debugFile, 'DEBUG', null, 1048576, 5, ['password'], 4096, true);
    $debugLogger->debugRequest($request, $response, microtime(true), [
        ['sql' => 'SELECT 1', 'bindings' => [], 'duration_ms' => 0.1, 'slow' => false],
    ]);
    $debugContent = (string)file_get_contents($debugFile);
    assert_true(str_contains($debugContent, '"status_code":202'), 'logger should record response status code');
    assert_true(str_contains($debugContent, '"X-Debug":"yes"'), 'logger should record response headers');
    assert_true(str_contains($debugContent, '"name":"debug.show"'), 'logger should record matched route name');
    assert_true(str_contains($debugContent, '"queries"'), 'logger should record query log entries');
    assert_true(str_contains($debugContent, '"component":"logger"'), 'logger should record component');
    assert_true(str_contains($debugContent, '"request_id"'), 'logger should record request id');

    $rotateFile = sys_get_temp_dir() . '/adlaire_rotate.log';
    foreach ([$rotateFile, $rotateFile . '.1', $rotateFile . '.2', $rotateFile . '.3'] as $candidate) {
        if (is_file($candidate)) {
            assert_true(unlink($candidate), 'old rotate log should be removed');
        }
    }
    $rotateLogger = new Logger($rotateFile, 'DEBUG', null, 1, 2);
    $rotateLogger->info(str_repeat('a', 64));
    $rotateLogger->info(str_repeat('b', 64));
    $rotateLogger->info(str_repeat('c', 64));
    $rotateLogger->info(str_repeat('d', 64));
    assert_true(is_file($rotateFile . '.1'), 'logger should keep first rotated generation');
    assert_true(is_file($rotateFile . '.2'), 'logger should keep second rotated generation');
    assert_true(!is_file($rotateFile . '.3'), 'logger should not exceed configured rotated generations');

    $requestIdFile = sys_get_temp_dir() . '/adlaire_request_id.log';
    if (is_file($requestIdFile)) {
        assert_true(unlink($requestIdFile), 'old request id log should be removed');
    }
    $requestLogger = new Logger($requestIdFile, 'DEBUG', 'secret', 1048576, 5, ['password'], 4096, false, 'rid-123', 'core');
    $requestLogger->info('request id test');
    $requestLogger->withComponent('database')->info('component child test');
    $requestLogger->auditEvent('audit.release_readiness_checked', [
        'ready' => true,
        'password' => 'hidden-password',
    ]);
    $requestIdContent = (string)file_get_contents($requestIdFile);
    assert_true(str_contains($requestIdContent, '"request_id":"rid-123"'), 'logger should use configured request id');
    assert_true(str_contains($requestIdContent, '"component":"core"'), 'logger should use configured component');
    assert_true(str_contains($requestIdContent, '"component":"database"'), 'logger component clone should override component');
    assert_true(str_contains($requestIdContent, '"component":"audit"'), 'logger audit event should use audit component');
    assert_true(str_contains($requestIdContent, '"event":"audit.release_readiness_checked"'), 'logger audit event should record event name');
    assert_true(!str_contains($requestIdContent, 'hidden-password'), 'logger audit event should mask sensitive values');
}

function test_deployer_config(): void
{
    $base = sys_get_temp_dir() . '/adlaire_deploy_debug';
    foreach (['target', 'work', 'backup'] as $dir) {
        if (!is_dir($base . '/' . $dir)) {
            assert_true(mkdir($base . '/' . $dir, 0775, true) || is_dir($base . '/' . $dir), "debug deploy directory should be created: {$dir}");
        }
    }

    $config = DeployConfig::fromArray([
        'repository' => 'git@example.com:owner/repo.git',
        'branch' => 'main',
        'target_dir' => $base . '/target',
        'work_dir' => $base . '/work',
        'backup_dir' => $base . '/backup',
        'log_file' => $base . '/deploy.log',
        'integration_modules' => ['Auris'],
        'deploy_allowlist' => ['*.php', 'adlaire-ecosystem.md'],
    ]);

    $deployer = new Deployer($config);
    assert_true($deployer instanceof Deployer, 'deployer should be constructed from valid config');
    $configManifest = $config->deploymentManifest();
    assert_same('deployment system', $configManifest['axis'] ?? null, 'deploy config manifest should mark deployment axis');
    assert_true(in_array('Auris', $configManifest['integration_modules'] ?? [], true), 'deploy config manifest should include Auris integration module');
    assert_same(true, $configManifest['auris_integration_considered'] ?? null, 'deploy config manifest should consider Auris integration');
    assert_same(false, $configManifest['architecture_changed'] ?? null, 'deploy config manifest should not change architecture');

    $validation = $deployer->validateOnly();
    assert_true($validation['valid'] === true, 'deployer validateOnly should return valid result');
    assert_same('main', $validation['branch'], 'deployer validateOnly should include branch');
    assert_same('deployment system', $validation['deployment_axis'] ?? null, 'deployer validateOnly should include deployment axis');

    $deploymentManifest = $deployer->deploymentSystemManifest();
    assert_same('DeploymentCore.php', $deploymentManifest['component'] ?? null, 'deployer manifest should identify component');
    assert_same('deployment system', $deploymentManifest['axis'] ?? null, 'deployer manifest should identify deployment axis');
    assert_same('distributed autonomous system design philosophy', $deploymentManifest['design_philosophy'] ?? null, 'deployer manifest should include design philosophy');
    assert_same(true, $deploymentManifest['auris_integration_considered'] ?? null, 'deployer manifest should consider Auris integration');
    assert_same(false, $deploymentManifest['architecture_changed'] ?? null, 'deployer manifest should not change architecture');

    $deploymentReadiness = $deployer->deploymentReadiness();
    assert_same(true, $deploymentReadiness['ready'] ?? null, 'deployer readiness should pass for prepared config');
    foreach ($deploymentReadiness['checks'] ?? [] as $name => $passed) {
        assert_same(true, $passed, "deployer readiness check should pass: {$name}");
    }

    $preflight = $deployer->preflight();
    assert_same(true, $preflight['ready'] ?? null, 'deployer preflight should pass for prepared config');
    assert_same('DeploymentCore.php', $preflight['component'] ?? null, 'deployer preflight should identify DeploymentCore');
    assert_same(true, $preflight['compatibility_guaranteed'] ?? null, 'deployer preflight should guarantee deployment compatibility');
    assert_same(false, $preflight['breaking_changes_allowed'] ?? null, 'deployer preflight should forbid deployment breaking changes');
    assert_same(true, $preflight['checks']['deploy_allowlist_configured'] ?? null, 'deployer preflight should require deploy allowlist');
    assert_same(true, $preflight['checks']['lock_available'] ?? null, 'deployer preflight should verify lock availability');

    $previewSource = $base . '/source_preview';
    if (!is_dir($previewSource)) {
        assert_true(mkdir($previewSource, 0775, true) || is_dir($previewSource), 'deployment preview source should be created');
    }
    assert_true(file_put_contents($base . '/target/modified.php', '<?php echo "old";') !== false, 'preview modified target should be prepared');
    assert_true(file_put_contents($base . '/target/unchanged.php', '<?php echo "same";') !== false, 'preview unchanged target should be prepared');
    assert_true(file_put_contents($previewSource . '/added.php', '<?php echo "added";') !== false, 'preview added source should be prepared');
    assert_true(file_put_contents($previewSource . '/modified.php', '<?php echo "new";') !== false, 'preview modified source should be prepared');
    assert_true(file_put_contents($previewSource . '/unchanged.php', '<?php echo "same";') !== false, 'preview unchanged source should be prepared');
    assert_true(file_put_contents($previewSource . '/notes.txt', 'skip') !== false, 'preview skipped source should be prepared');
    assert_true(file_put_contents($previewSource . '/DeploymentCore.php', '<?php echo "core";') !== false, 'preview deployment core source should be prepared');

    $plan = $deployer->planPreview($previewSource);
    assert_same(true, $plan['ready'] ?? null, 'deployment plan preview should be ready');
    assert_same(true, $plan['read_only'] ?? null, 'deployment plan preview should be read only');
    assert_same(false, $plan['command_execution_allowed'] ?? null, 'deployment plan preview should avoid command execution');
    assert_same(false, $plan['writes_allowed'] ?? null, 'deployment plan preview should avoid writes');
    assert_same('DeploymentCore.php', $plan['component'] ?? null, 'deployment plan preview should identify DeploymentCore');
    assert_same(true, $plan['deployment_core_change_detected'] ?? null, 'deployment plan preview should detect DeploymentCore changes');
    assert_true(in_array('added.php', $plan['files']['added'] ?? [], true), 'deployment plan preview should classify added files');
    assert_true(in_array('DeploymentCore.php', $plan['files']['added'] ?? [], true), 'deployment plan preview should classify added DeploymentCore file');
    assert_true(in_array('modified.php', $plan['files']['modified'] ?? [], true), 'deployment plan preview should classify modified files');
    assert_true(in_array('unchanged.php', $plan['files']['unchanged'] ?? [], true), 'deployment plan preview should classify unchanged files');
    assert_true(in_array('notes.txt', $plan['files']['skipped'] ?? [], true), 'deployment plan preview should classify skipped files');
    assert_same(2, $plan['summary']['added'] ?? null, 'deployment plan preview should count added files');
    assert_same(1, $plan['summary']['modified'] ?? null, 'deployment plan preview should count modified files');
    assert_same(1, $plan['summary']['unchanged'] ?? null, 'deployment plan preview should count unchanged files');
    assert_same(1, $plan['summary']['skipped'] ?? null, 'deployment plan preview should count skipped files');
    assert_same(3, $plan['summary']['changes'] ?? null, 'deployment plan preview should count change files');

    $snapshot = $deployer->compatibilitySnapshot($previewSource);
    assert_same(true, $snapshot['ready'] ?? null, 'deployment compatibility snapshot should be ready');
    assert_same(true, $snapshot['read_only'] ?? null, 'deployment compatibility snapshot should be read only');
    assert_same(false, $snapshot['command_execution_allowed'] ?? null, 'deployment compatibility snapshot should avoid command execution');
    assert_same(false, $snapshot['writes_allowed'] ?? null, 'deployment compatibility snapshot should avoid writes');
    assert_same('DeploymentCore.php', $snapshot['component'] ?? null, 'deployment compatibility snapshot should identify DeploymentCore');
    assert_same(true, $snapshot['compatibility_guaranteed'] ?? null, 'deployment compatibility snapshot should guarantee deployment compatibility');
    assert_same(false, $snapshot['breaking_changes_allowed'] ?? null, 'deployment compatibility snapshot should forbid deployment breaking changes');
    assert_same(true, $snapshot['deployment_core_change_detected'] ?? null, 'deployment compatibility snapshot should preserve DeploymentCore change evidence');
    assert_same(true, $snapshot['checks']['deployment_core_component'] ?? null, 'deployment compatibility snapshot should check component');
    assert_same(true, $snapshot['checks']['deployment_axis_retained'] ?? null, 'deployment compatibility snapshot should check deployment axis');
    assert_same(true, $snapshot['checks']['architecture_unchanged'] ?? null, 'deployment compatibility snapshot should check unchanged architecture');
    assert_same(true, $snapshot['checks']['preflight_ready'] ?? null, 'deployment compatibility snapshot should check preflight readiness');
    assert_same(true, $snapshot['checks']['plan_preview_read_only'] ?? null, 'deployment compatibility snapshot should check plan preview read-only status');
    assert_same($plan['summary'], $snapshot['plan_summary'] ?? null, 'deployment compatibility snapshot should include plan summary evidence');

    $targetFile = $base . '/target/current.txt';
    assert_true(file_put_contents($targetFile, 'current') !== false, 'target file should be prepared');
    $snapshot = $base . '/backup/20000101_000000_previous';
    if (!is_dir($snapshot)) {
        assert_true(mkdir($snapshot, 0775, true) || is_dir($snapshot), 'previous snapshot should be created');
    }
    assert_true(file_put_contents($snapshot . '/manifest.json', json_encode([
        'created_at' => date('c'),
        'files' => ['current.txt'],
    ], JSON_THROW_ON_ERROR)) !== false, 'previous snapshot manifest should be written');
    assert_true(file_put_contents($snapshot . '/current.txt', 'old') !== false, 'previous snapshot file should be written');

    $rollbackPreview = $deployer->rollbackPreview();
    assert_same(true, $rollbackPreview['ready'] ?? null, 'rollback preview should be ready when snapshot exists');
    assert_same(true, $rollbackPreview['read_only'] ?? null, 'rollback preview should be read only');
    assert_same(false, $rollbackPreview['command_execution_allowed'] ?? null, 'rollback preview should avoid command execution');
    assert_same(false, $rollbackPreview['writes_allowed'] ?? null, 'rollback preview should avoid writes');
    assert_true(in_array('current.txt', $rollbackPreview['files']['restore'] ?? [], true), 'rollback preview should classify restore files');
    assert_true(in_array('modified.php', $rollbackPreview['files']['remove'] ?? [], true), 'rollback preview should classify remove files');

    $safety = $deployer->deploymentSafetyScore($previewSource);
    assert_same(true, $safety['read_only'] ?? null, 'deployment safety score should be read only');
    assert_same(false, $safety['command_execution_allowed'] ?? null, 'deployment safety score should avoid command execution');
    assert_same(false, $safety['writes_allowed'] ?? null, 'deployment safety score should avoid writes');
    assert_true(($safety['score'] ?? 0) >= 70, 'deployment safety score should pass minimum threshold');
    assert_same(true, $safety['compatibility_snapshot_ready'] ?? null, 'deployment safety score should include compatibility evidence');
    assert_same(true, $safety['rollback_preview_ready'] ?? null, 'deployment safety score should include rollback evidence');

    $controlReport = $deployer->deploymentControlReport($previewSource);
    assert_same('v0.266', $controlReport['version'] ?? null, 'deployment control report should include version');
    assert_same(true, $controlReport['read_only'] ?? null, 'deployment control report should be read only');
    assert_same(false, $controlReport['command_execution_allowed'] ?? null, 'deployment control report should avoid command execution');
    assert_same(false, $controlReport['writes_allowed'] ?? null, 'deployment control report should avoid writes');
    assert_true(isset($controlReport['preflight'], $controlReport['plan_preview'], $controlReport['compatibility_snapshot'], $controlReport['rollback_preview'], $controlReport['safety_score'], $controlReport['history']), 'deployment control report should include all control sections');
    assert_true(isset($controlReport['safety_score_details']), 'deployment control report should include safety score details');

    $scoreDetails = $deployer->deploymentSafetyScoreDetails($previewSource);
    assert_same(true, $scoreDetails['read_only'] ?? null, 'deployment safety score details should be read only');
    assert_same($safety['score'], $scoreDetails['score'] ?? null, 'deployment safety score details should include score');

    $statePreview = $deployer->rollbackStatePreview();
    assert_same(true, $statePreview['read_only'] ?? null, 'rollback state preview should be read only');
    assert_same(true, $statePreview['ready'] ?? null, 'rollback state preview should be ready');
    assert_same(1, $statePreview['projected_state']['restored_files'] ?? null, 'rollback state preview should count restored files');

    $controlDiff = $deployer->deploymentControlDiff($controlReport, $previewSource);
    assert_same(true, $controlDiff['read_only'] ?? null, 'deployment control diff should be read only');
    assert_same(0, $controlDiff['summary']['changes'] ?? null, 'deployment control diff should be empty against same report');

    $evidence = $deployer->releaseEvidenceBundle($previewSource);
    assert_same('v0.266', $evidence['version'] ?? null, 'release evidence bundle should include version');
    assert_same(true, $evidence['read_only'] ?? null, 'release evidence bundle should be read only');
    assert_true(isset($evidence['evidence']['control_report'], $evidence['evidence']['release_gate_inputs']), 'release evidence bundle should include required evidence');

    $candidateGate = $deployer->stableReleaseCandidateGate($previewSource);
    assert_same(true, $candidateGate['rc_ready'] ?? null, 'stable release candidate gate should pass prepared deployment evidence');
    assert_same('release-candidate', $candidateGate['grade'] ?? null, 'stable release candidate gate should expose release candidate grade');

    $recorded = $deployer->recordDeploymentControlSnapshot($previewSource);
    assert_same(true, $recorded['recorded'] ?? null, 'deployment control snapshot should be recorded');
    assert_same(true, $recorded['audit_artifact'] ?? null, 'deployment control snapshot should be an audit artifact');
    assert_same(false, $recorded['configuration_file'] ?? null, 'deployment control snapshot should not be a configuration file');
    assert_true(is_file($base . '/backup/deployment_control_snapshots.jsonl'), 'deployment control snapshot JSONL should be written');

    $failingConfig = DeployConfig::fromArray([
        'repository' => '/definitely/missing/repository.git',
        'branch' => 'main',
        'target_dir' => $base . '/target',
        'work_dir' => $base . '/work',
        'backup_dir' => $base . '/backup',
        'log_file' => $base . '/deploy-failing.log',
        'lock_file' => $base . '/work/deploy-failing.lock',
    ]);

    try {
        (new Deployer($failingConfig))->run();
        throw new DebugTestFailure('failing fetch should throw');
    } catch (RuntimeException) {
    }

    assert_same('current', (string)file_get_contents($targetFile), 'failed fetch before backup should not rollback previous snapshot');

    $initialBase = sys_get_temp_dir() . '/adlaire_deploy_initial_debug_' . bin2hex(random_bytes(4));
    assert_true(mkdir($initialBase . '/work', 0775, true), 'initial work directory should be created');
    assert_true(mkdir($initialBase . '/backup', 0775, true), 'initial backup directory should be created');
    $initialConfig = DeployConfig::fromArray([
        'repository' => '/definitely/missing/repository.git',
        'branch' => 'main',
        'target_dir' => $initialBase . '/target',
        'work_dir' => $initialBase . '/work',
        'backup_dir' => $initialBase . '/backup',
        'log_file' => $initialBase . '/deploy.log',
    ]);
    $initialDeployer = new Deployer($initialConfig);
    $backup = new ReflectionMethod($initialDeployer, 'backup');
    $backup->setAccessible(true);
    $snapshotPath = $backup->invoke($initialDeployer, []);
    assert_true(is_dir($initialBase . '/target'), 'initial backup should create missing target directory');
    assert_true(is_file($snapshotPath . '/manifest.json'), 'initial backup should write manifest for empty target');

    $allowed = new ReflectionMethod($initialDeployer, 'allowed');
    $allowed->setAccessible(true);
    assert_true($allowed->invoke($initialDeployer, 'FrameworkCore/Core.php', []), 'empty deploy allowlist should allow all files');
    assert_true($allowed->invoke($initialDeployer, 'FrameworkCore/Core.php', ['*.php']), 'matching deploy allowlist should allow file');
    assert_true(!$allowed->invoke($initialDeployer, 'notes.txt', ['*.php']), 'non-matching deploy allowlist should reject file');
    foreach (['../Core.php', '/Core.php', 'nested/../Core.php', '', "bad\0path"] as $badPath) {
        try {
            $allowed->invoke($initialDeployer, $badPath, []);
            throw new DebugTestFailure('invalid deploy path should fail: ' . $badPath);
        } catch (InvalidArgumentException) {
        }
    }

    $recordHistory = new ReflectionMethod($initialDeployer, 'recordHistory');
    $recordHistory->setAccessible(true);
    $recordHistory->invoke($initialDeployer, $snapshotPath, ['FrameworkCore/Core.php']);
    $historyLines = file($initialBase . '/backup/deploy_history.jsonl', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    assert_true(is_array($historyLines) && $historyLines !== [], 'deploy history should be written');
    $lastHistory = json_decode((string)end($historyLines), true, flags: JSON_THROW_ON_ERROR);
    assert_same('deploy', $lastHistory['phase'] ?? null, 'deploy history should include phase');
    assert_same('completed', $lastHistory['status'] ?? null, 'deploy history should include status');

    $history = $initialDeployer->deploymentHistorySummary();
    assert_same(true, $history['read_only'] ?? null, 'deploy history summary should be read only');
    assert_same(1, $history['summary']['total'] ?? null, 'deploy history summary should include total entries');
    assert_same(1, $history['summary']['completed'] ?? null, 'deploy history summary should include completed entries');
}

$tests = [
    'request' => test_request_helpers(...),
    'core_config' => test_core_config(...),
    'adlaire_audit' => test_adlaire_audit(...),
    'license_governance' => test_license_governance(...),
    'release_readiness' => test_release_readiness(...),
    'deployment_axis_policy' => test_deployment_axis_policy(...),
    'deployment_axis_map_policy' => test_deployment_axis_map_policy(...),
    'dashboard_deploy_execution_policy' => test_dashboard_deploy_execution_policy(...),
    'execution_safety_gate_policy' => test_execution_safety_gate_policy(...),
    'deployment_execute_adapter_policy' => test_deployment_execute_adapter_policy(...),
    'execution_audit_trail_policy' => test_execution_audit_trail_policy(...),
    'dashboard_gated_controls_policy' => test_dashboard_gated_controls_policy(...),
    'reorganization_readiness_boundary_policy' => test_reorganization_readiness_boundary_policy(...),
    'reorganization_architecture_plan_policy' => test_reorganization_architecture_plan_policy(...),
    'reorganization_preparation_plan_policy' => test_reorganization_preparation_plan_policy(...),
    'physical_reorganization_phase_one_policy' => test_physical_reorganization_phase_one_policy(...),
    'frontend_reorganization_shim_policy' => test_frontend_reorganization_shim_policy(...),
    'css_framework_source_sync_policy' => test_css_framework_source_sync_policy(...),
    'dashboard_frontend_class_extraction_policy' => test_dashboard_frontend_class_extraction_policy(...),
    'framework_classification_policy' => test_framework_classification_policy(...),
    'integration_core_policy' => test_integration_core_policy(...),
    'auris_integration_policy' => test_auris_integration_policy(...),
    'auris_module' => test_auris_module(...),
    'official_metadata' => test_official_metadata(...),
    'specification_integrity' => test_specification_integrity(...),
    'specification_drift' => test_specification_drift(...),
    'distribution_manifest' => test_distribution_manifest(...),
    'microkernel' => test_microkernel(...),
    'autonomous_system' => test_autonomous_system(...),
    'long_term_stability' => test_long_term_stability(...),
    'stable_release_contract' => test_stable_release_contract(...),
    'production_equivalent_environment' => test_production_equivalent_environment(...),
    'database_runtime_hardening_policy' => test_database_runtime_hardening_policy(...),
    'runtime_operations_hardening' => test_runtime_operations_hardening(...),
    'operations_dashboard' => test_operations_dashboard(...),
    'configuration_file_policy' => test_configuration_file_policy(...),
    'deployment_preflight_policy' => test_deployment_preflight_policy(...),
    'deployment_plan_preview_policy' => test_deployment_plan_preview_policy(...),
    'deployment_compatibility_snapshot_policy' => test_deployment_compatibility_snapshot_policy(...),
    'deployment_control_policy_suite' => test_deployment_control_policy_suite(...),
    'validator' => test_validator(...),
    'router' => test_router(...),
    'general_framework_support' => test_general_framework_support(...),
    'response_security' => test_response_security(...),
    'database' => test_database(...),
    'logger' => test_logger(...),
    'deployer_config' => test_deployer_config(...),
];

foreach ($tests as $name => $test) {
    try {
        $test();
        echo "PASS {$name}\n";
    } catch (Throwable $exception) {
        fwrite(STDERR, "FAIL {$name}: {$exception->getMessage()}\n");
        throw $exception;
    }
}

echo "OK\n";
