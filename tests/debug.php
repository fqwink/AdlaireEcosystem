<?php

declare(strict_types=1);

require_once __DIR__ . '/../Core.php';
require_once __DIR__ . '/../Logger.php';
require_once __DIR__ . '/../Database.php';
require_once __DIR__ . '/../Deployer.php';

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
    $logger->info('debug logger test', ['password' => 'hidden']);
    $content = (string)file_get_contents($file);
    assert_true(str_contains($content, '[masked]'), 'logger should mask configured fields');
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
    $requestIdContent = (string)file_get_contents($requestIdFile);
    assert_true(str_contains($requestIdContent, '"request_id":"rid-123"'), 'logger should use configured request id');
    assert_true(str_contains($requestIdContent, '"component":"core"'), 'logger should use configured component');
    assert_true(str_contains($requestIdContent, '"component":"database"'), 'logger component clone should override component');
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
    ]);

    $deployer = new Deployer($config);
    assert_true($deployer instanceof Deployer, 'deployer should be constructed from valid config');
    $validation = $deployer->validateOnly();
    assert_true($validation['valid'] === true, 'deployer validateOnly should return valid result');
    assert_same('main', $validation['branch'], 'deployer validateOnly should include branch');

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
    assert_true($allowed->invoke($initialDeployer, 'Core.php', []), 'empty deploy allowlist should allow all files');
    assert_true($allowed->invoke($initialDeployer, 'Core.php', ['*.php']), 'matching deploy allowlist should allow file');
    assert_true(!$allowed->invoke($initialDeployer, 'notes.txt', ['*.php']), 'non-matching deploy allowlist should reject file');

    $recordHistory = new ReflectionMethod($initialDeployer, 'recordHistory');
    $recordHistory->setAccessible(true);
    $recordHistory->invoke($initialDeployer, $snapshotPath, ['Core.php']);
    $historyLines = file($initialBase . '/backup/deploy_history.jsonl', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    assert_true(is_array($historyLines) && $historyLines !== [], 'deploy history should be written');
    $lastHistory = json_decode((string)end($historyLines), true, flags: JSON_THROW_ON_ERROR);
    assert_same('deploy', $lastHistory['phase'] ?? null, 'deploy history should include phase');
    assert_same('completed', $lastHistory['status'] ?? null, 'deploy history should include status');
}

$tests = [
    'request' => test_request_helpers(...),
    'core_config' => test_core_config(...),
    'validator' => test_validator(...),
    'router' => test_router(...),
    'database' => test_database(...),
    'logger' => test_logger(...),
    'deployer_config' => test_deployer_config(...),
];

foreach ($tests as $name => $test) {
    $test();
    echo "PASS {$name}\n";
}

echo "OK\n";
