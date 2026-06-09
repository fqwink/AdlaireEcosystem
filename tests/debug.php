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

    $jsonRequest = make_request('POST', '/json', [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_ACCEPT' => 'application/json',
    ]);
    assert_true($jsonRequest->isJson(), 'request should detect JSON content type');
    assert_true($jsonRequest->expectsJson(), 'request should detect JSON accept header');

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
    assert_same('v0.202', Adlaire::version(), 'Adlaire version should follow cumulative v0.x release format');
    $api = Adlaire::publicApi();
    assert_true(in_array('Request', $api['FrameworkCore/Core.php'] ?? [], true), 'public API should include Request');
    assert_true(in_array('MicroKernel', $api['FrameworkCore/Kernel.php'] ?? [], true), 'public API should include MicroKernel');
    assert_true(in_array('AdlaireExtension', $api['FrameworkCore/Extension.php'] ?? [], true), 'public API should include AdlaireExtension');
    assert_true(in_array('AutonomousModule', $api['FrameworkCore/Extension.php'] ?? [], true), 'public API should include AutonomousModule');
    assert_true(in_array('PolicyRule', $api['FrameworkCore/Extension.php'] ?? [], true), 'public API should include PolicyRule');
    assert_true(in_array('AurisModule', $api['FrameworkCore/Extension.php'] ?? [], true), 'public API should include AurisModule');
    assert_true(in_array('Database', $api['FrameworkCore/Database.php'] ?? [], true), 'public API should include Database');
    assert_true(in_array('Deployer', $api['DeploymentCore.php'] ?? [], true), 'public API should include Deployer');
    assert_true(in_array('Logger', $api['FrameworkCore/Logger.php'] ?? [], true), 'public API should include Logger');
    assert_true(in_array('ConfigRepository', $api['FrameworkCore/Config.php'] ?? [], true), 'public API should include ConfigRepository');
    assert_true(in_array('MiddlewarePipeline', $api['FrameworkCore/Middleware.php'] ?? [], true), 'public API should include MiddlewarePipeline');
    assert_true(in_array('AdlaireSupport', $api['FrameworkCore/Support.php'] ?? [], true), 'public API should include AdlaireSupport');

    $audit = Adlaire::audit();
    assert_same('v0.202', $audit['version'] ?? null, 'audit should include version');
    assert_same('>=8.3', $audit['php'] ?? null, 'audit should include PHP requirement');
    assert_same('v0.x', $audit['version_format'] ?? null, 'audit should include cumulative version format');
    assert_same(true, $audit['cumulative_version'] ?? null, 'audit should mark cumulative versions');
    assert_same('v0.202', $audit['formalization_version'] ?? null, 'audit should include formalization version');
    assert_same('10 files', $audit['file_principle'] ?? null, 'audit should include 10-file principle');
    assert_same('php -d phar.readonly=0 tests/debug.php', $audit['official_debug_test'] ?? null, 'audit should include official debug test command');

    $specificationIds = Adlaire::specificationIds();
    assert_true(isset($specificationIds['FrameworkCore/Core.php']['CORE-REQ-001']), 'specification IDs should include core requirement');
    assert_true(isset($specificationIds['FrameworkCore/Kernel.php']['KERNEL-REQ-001']), 'specification IDs should include kernel requirement');
    assert_true(isset($specificationIds['FrameworkCore/Database.php']['DB-REQ-001']), 'specification IDs should include database requirement');
    assert_true(isset($specificationIds['FrameworkCore/Logger.php']['LOGGER-REQ-001']), 'specification IDs should include logger requirement');
    assert_true(isset($specificationIds['DeploymentCore.php']['DEPLOY-REQ-001']), 'specification IDs should include deployer requirement');
    assert_true(isset($specificationIds['Release']['RELEASE-REQ-001']), 'specification IDs should include release requirement');
    assert_same($specificationIds, $audit['specification_ids'] ?? null, 'audit should include specification IDs');

    $testSpecificationMap = Adlaire::testSpecificationMap();
    assert_true(in_array('CORE-REQ-002', $testSpecificationMap['adlaire_audit'] ?? [], true), 'test map should connect audit test to core spec');
    assert_true(in_array('RELEASE-REQ-002', $testSpecificationMap['adlaire_audit'] ?? [], true), 'test map should connect audit test to release gate');
    assert_true(in_array('KERNEL-REQ-001', $testSpecificationMap['microkernel'] ?? [], true), 'test map should connect microkernel test to kernel spec');
    assert_true(in_array('RELEASE-REQ-011', $testSpecificationMap['production_equivalent_environment'] ?? [], true), 'test map should connect production-equivalent test to Xserver spec');
    assert_same($testSpecificationMap, $audit['test_specification_map'] ?? null, 'audit should include test specification map');
    assert_true(in_array('official_debug_test', $audit['required_verifications'] ?? [], true), 'audit should require official debug test');
    assert_true(in_array('xserver_profile_audit', $audit['required_verifications'] ?? [], true), 'audit should require Xserver profile audit');
    assert_same('forbidden', $audit['breaking_change_policy']['public_api_removal'] ?? null, 'audit should forbid public API removal');
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

    $officialRelease = Adlaire::officialReleasePolicy();
    assert_same(true, $officialRelease['specification_compliance'] ?? null, 'official release policy should require specification compliance');
    assert_same(true, $officialRelease['official_debug_test_required'] ?? null, 'official release policy should require debug test');
    assert_same(true, $officialRelease['approved_maintainer_release_required'] ?? null, 'official release policy should require approved maintainer release');

    $audit = Adlaire::audit();
    assert_same($license, $audit['license_policy'] ?? null, 'audit should include license policy');
    assert_same($prohibited, $audit['prohibited_use_policy'] ?? null, 'audit should include prohibited use policy');
    assert_same($governance, $audit['governance_policy'] ?? null, 'audit should include governance policy');
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

    $compatibility = Adlaire::compatibilityMatrix();
    assert_same('>=8.3', $compatibility['php']['requirement'] ?? null, 'compatibility matrix should include PHP requirement');
    assert_same('v0.10', $compatibility['public_api']['baseline'] ?? null, 'compatibility matrix should include public API baseline');
    assert_same('v0.11', $compatibility['formalization']['baseline'] ?? null, 'compatibility matrix should include formalization baseline');
    foreach ($compatibility as $name => $entry) {
        assert_same(true, $entry['compatible'] ?? null, "compatibility entry should pass: {$name}");
    }
    assert_same($compatibility, $audit['compatibility_matrix'] ?? null, 'audit should include compatibility matrix');

    $readiness = Adlaire::releaseReadiness();
    assert_same('v0.202', $readiness['version'] ?? null, 'release readiness should include current version');
    assert_same(true, $readiness['ready'] ?? null, 'release readiness should be ready when all checks pass');
    foreach ($readiness['checks'] ?? [] as $name => $passed) {
        assert_same(true, $passed, "release readiness check should pass: {$name}");
    }
}

function test_deployment_axis_policy(): void
{
    $policy = Adlaire::deploymentAxisPolicy();
    assert_same('v0.202', $policy['version'] ?? null, 'deployment axis policy should include version');
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

function test_auris_integration_policy(): void
{
    $policy = Adlaire::aurisIntegrationPolicy();
    assert_same('v0.202', $policy['version'] ?? null, 'Auris integration policy should include version');
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
    assert_same('v0.202', $metadata['version'] ?? null, 'official metadata should include version');
    assert_same(Adlaire::publicApi(), $metadata['public_api'] ?? null, 'official metadata should include public API');
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
    assert_same('v0.202', $manifest['version'] ?? null, 'distribution manifest should include version');
    foreach (['FrameworkCore/Core.php', 'FrameworkCore/Kernel.php', 'FrameworkCore/Extension.php', 'FrameworkCore/Database.php', 'DeploymentCore.php', 'FrameworkCore/Logger.php', 'FrameworkCore/Config.php', 'FrameworkCore/Middleware.php', 'FrameworkCore/Support.php', 'tests/debug.php', 'adlaire-ecosystem.md'] as $file) {
        assert_true(in_array($file, $manifest['files'] ?? [], true), "distribution manifest should include file: {$file}");
    }
    assert_same(Adlaire::publicApi(), $manifest['public_api'] ?? null, 'distribution manifest should include public API');
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
    assert_same(true, Adlaire::stabilityContract()['breaking_changes_forbidden'] ?? null, 'stability contract should forbid breaking changes');
    $report = Adlaire::autonomousAuditReport();
    assert_same('v0.202', $report['version'] ?? null, 'autonomous audit report should include version');
    assert_true(isset($report['policies']['cloud_business_use']), 'autonomous audit report should include policies');
}

function test_long_term_stability(): void
{
    $registry = Adlaire::officialExtensionRegistry();
    assert_same(false, $registry['unknown_extensions_allowed_as_official'] ?? null, 'official registry should reject unknown official extensions');
    assert_same(true, $registry['cloud_business_prohibition_enforced'] ?? null, 'official registry should enforce cloud prohibition');

    $profiles = Adlaire::compatibilityProfiles();
    foreach (['minimal', 'standard', 'audited', 'distributed', 'extension_enabled'] as $profile) {
        assert_true(isset($profiles[$profile]), "compatibility profile should exist: {$profile}");
    }

    $migration = Adlaire::migrationPolicy();
    assert_same(false, $migration['breaking_changes'] ?? null, 'migration policy should forbid breaking changes');
    assert_same(true, $migration['doc_update_required'] ?? null, 'migration policy should require documentation');

    $support = Adlaire::supportPolicy();
    assert_same(true, $support['long_term_support'] ?? null, 'support policy should mark long term support');
    assert_true(in_array('breaking public API changes', $support['unsupported_changes'] ?? [], true), 'support policy should reject breaking changes');

    $security = Adlaire::securityFixProtocol();
    assert_same(['report', 'assess', 'patch', 'test', 'audit', 'release', 'document'], $security['steps'] ?? null, 'security protocol should include required steps');

    $guarantee = Adlaire::compatibilityGuarantee();
    assert_same('fixed', $guarantee['public_api'] ?? null, 'compatibility guarantee should fix public API');
    assert_same('fixed', $guarantee['audit_api'] ?? null, 'compatibility guarantee should fix audit API');

    $freeze = Adlaire::releaseFreezePolicy();
    assert_true(in_array('breaking_changes', $freeze['forbidden_changes'] ?? [], true), 'release freeze should forbid breaking changes');

    $lts = Adlaire::longTermStabilityContract();
    assert_same('v0.202', $lts['version'] ?? null, 'long term stability contract should include version');
    assert_same(true, $lts['long_term_stable'] ?? null, 'long term stability contract should mark stable');
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
    assert_same('v0.202', $contract['version'] ?? null, 'stable release contract should include version');
    assert_same(true, $contract['stable_release'] ?? null, 'stable release contract should mark stable release');
    assert_same('v0.202 stable backend framework release', $contract['release_name'] ?? null, 'stable release contract should name release');
    foreach (['routing', 'middleware', 'validation', 'database', 'logging', 'deployment', 'configuration', 'support helpers', 'microkernel', 'Auris module integration'] as $capability) {
        assert_true(in_array($capability, $contract['backend_framework_capabilities'] ?? [], true), "stable release contract should include capability: {$capability}");
    }
    assert_same(true, $contract['no_breaking_changes'] ?? null, 'stable release contract should forbid breaking changes');
    assert_same(true, $contract['ten_file_principle'] ?? null, 'stable release contract should retain 10-file principle');
    assert_same(true, $contract['deployment_axis'] ?? null, 'stable release contract should retain deployment axis');
    assert_same(true, $contract['docker_debug_verified'] ?? null, 'stable release contract should require Docker debug verification');

    $audit = Adlaire::audit();
    assert_same($contract, $audit['stable_release_contract'] ?? null, 'audit should include stable release contract');
    assert_same($contract, Adlaire::distributionManifest()['stable_release_contract'] ?? null, 'manifest should include stable release contract');
    assert_same(true, Adlaire::specificationIntegrity()['checks']['stable_release_contract'] ?? null, 'specification integrity should include stable release contract');
    assert_same(true, Adlaire::releaseReadiness()['checks']['stable_release_contract'] ?? null, 'release readiness should include stable release contract');
}

function test_production_equivalent_environment(): void
{
    $policy = Adlaire::productionEnvironmentPolicy();
    assert_same('v0.202', $policy['version'] ?? null, 'production environment policy should include version');
    assert_same('Xserver rental server', $policy['production_provider'] ?? null, 'production environment policy should identify Xserver');
    assert_same('Xserver shared rental server', $policy['production_environment'] ?? null, 'production environment policy should identify rental server environment');
    assert_same(true, $policy['production_equivalent_testing_required'] ?? null, 'production-equivalent testing should be required');
    assert_same('Docker php:8.3-apache with Xserver compatibility profile', $policy['local_test_environment'] ?? null, 'local test environment should emulate Xserver profile');
    assert_same('>=8.3', $policy['php_requirement'] ?? null, 'production profile should keep PHP 8.3 requirement');
    assert_same('PHP 8.3.x compatible', $policy['php_profile'] ?? null, 'production profile should use PHP 8.3 compatible profile');
    assert_same('Apache compatible shared hosting', $policy['web_server_profile'] ?? null, 'production profile should use Apache compatible shared hosting');
    assert_same('public_html', $policy['document_root'] ?? null, 'production profile should define public_html document root');
    assert_same(true, $policy['htaccess_required'] ?? null, 'production profile should require htaccess compatibility');
    assert_same(false, $policy['composer_required'] ?? null, 'production profile should not require Composer');
    assert_same(false, $policy['external_service_required_for_tests'] ?? null, 'production-equivalent test should avoid external services');
    assert_same(true, $policy['database_profile']['sqlite_for_local_debug'] ?? null, 'production profile should allow SQLite local debug');
    assert_same(true, $policy['database_profile']['mysql_compatible_production'] ?? null, 'production profile should account for MySQL compatible production');
    assert_same('DeploymentCore.php', $policy['deployment_profile']['root_deployment_core'] ?? null, 'production profile should keep DeploymentCore at root');
    assert_same('FrameworkCore', $policy['deployment_profile']['framework_core_directory'] ?? null, 'production profile should keep FrameworkCore directory');
    assert_same(true, $policy['deployment_profile']['no_deployment_core_directory'] ?? null, 'production profile should prohibit DeploymentCore directory');
    assert_true(in_array('xserver_profile_audit', $policy['required_verifications'] ?? [], true), 'production profile should require Xserver audit');

    $compatibility = Adlaire::compatibilityMatrix();
    assert_same('Xserver rental server', $compatibility['production_equivalent']['provider'] ?? null, 'compatibility matrix should include Xserver profile');
    assert_same(true, $compatibility['production_equivalent']['compatible'] ?? null, 'Xserver profile compatibility should pass');
    assert_same($policy, $compatibility['production_equivalent']['profile'] ?? null, 'compatibility matrix should embed production policy');

    $audit = Adlaire::audit();
    assert_same($policy, $audit['production_environment_policy'] ?? null, 'audit should include production environment policy');
    assert_same(true, $audit['specification_integrity']['checks']['production_environment_policy'] ?? null, 'specification integrity should include production environment policy');

    $readiness = Adlaire::releaseReadiness();
    assert_same(true, $readiness['checks']['production_environment_policy'] ?? null, 'release readiness should include production environment policy');
    assert_same($policy, Adlaire::distributionManifest()['production_environment_policy'] ?? null, 'distribution manifest should include production environment policy');
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
    $router->group('/api', static function (Router $router) use (&$groupOrder): void {
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
        $router->dispatch(make_request('GET', '/api/status'), new Response());
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
    $fileDatabase->statement('CREATE TABLE checks (id INTEGER PRIMARY KEY, name TEXT NOT NULL)');
    $fileDatabase->table('checks')->insert(['id' => 1, 'name' => 'file-url']);
    assert_same('file-url', $fileDatabase->table('checks')->first()['name'] ?? null, 'file: SQLite URL should work');

    Database::resetConnectionsForTesting();
    Database::addConnection('one', ':memory:', true);
    assert_true(Database::connection('one') instanceof Database, 'test connection reset should allow fresh named connection');
    Database::resetConnectionsForTesting();
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
        'api_token_value' => 'token-hidden',
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
}

$tests = [
    'request' => test_request_helpers(...),
    'core_config' => test_core_config(...),
    'adlaire_audit' => test_adlaire_audit(...),
    'license_governance' => test_license_governance(...),
    'release_readiness' => test_release_readiness(...),
    'deployment_axis_policy' => test_deployment_axis_policy(...),
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
    'validator' => test_validator(...),
    'router' => test_router(...),
    'general_framework_support' => test_general_framework_support(...),
    'response_security' => test_response_security(...),
    'database' => test_database(...),
    'logger' => test_logger(...),
    'deployer_config' => test_deployer_config(...),
];

foreach ($tests as $name => $test) {
    $test();
    echo "PASS {$name}\n";
}

echo "OK\n";
