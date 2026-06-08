<?php

declare(strict_types=1);

require_once __DIR__ . '/../Core.php';
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
    $request = make_request('GET', '/users/42?active=1', [
        'HTTP_AUTHORIZATION' => 'Bearer token-123',
        'HTTP_X_FORWARDED_FOR' => '203.0.113.10, 10.0.0.1',
    ]);

    assert_same('/users/42', $request->uri(), 'request URI should normalize path and drop query');
    assert_same('token-123', $request->bearerToken(), 'bearer token should parse authorization header');
    assert_same('203.0.113.10', $request->ip(), 'client IP should prefer forwarded first IP');
}

function test_validator(): void
{
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

function test_router(): void
{
    $router = new Router();
    $router->get('/users/{id}', static function (Request $request): void {
        throw new DebugRouteHit($request->param());
    })->where('id', '\d+')->name('users.show');

    assert_same('/users/42', $router->url('users.show', ['id' => 42]), 'named route should build URL');

    try {
        $router->dispatch(make_request('GET', '/users/42'), new Response());
    } catch (DebugRouteHit $hit) {
        assert_same(['id' => '42'], $hit->params, 'router should capture constrained param');
        return;
    }

    throw new DebugTestFailure('router did not hit expected route');
}

function test_database(): void
{
    if (!extension_loaded('pdo_sqlite')) {
        echo "SKIP database: pdo_sqlite extension is not loaded\n";
        return;
    }

    $database = Database::connect(':memory:');
    $database->enableQueryLog(0.0);
    $database->statement('CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL, score INTEGER NOT NULL)');
    $database->statement('CREATE TABLE posts (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER NOT NULL, title TEXT NOT NULL)');

    $database->table('users')->insert([
        ['name' => 'alice', 'score' => 10],
        ['name' => 'bob', 'score' => 20],
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

    $unionRows = $database->table('users')->select('name')->where('name', 'alice')
        ->union($database->table('users')->select('name')->where('name', 'bob'))
        ->get();
    assert_same(2, count($unionRows), 'union should combine query results');

    $withPosts = $database->table('users')->with('posts', 'posts', 'id', 'user_id')->orderBy('id')->get();
    assert_same(2, count($withPosts[0]['posts']), 'eager loading should attach related rows');

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

    assert_same(4, $database->table('users')->count(), 'nested transaction should commit');
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
}

$tests = [
    'request' => test_request_helpers(...),
    'validator' => test_validator(...),
    'router' => test_router(...),
    'database' => test_database(...),
    'deployer_config' => test_deployer_config(...),
];

foreach ($tests as $name => $test) {
    $test();
    echo "PASS {$name}\n";
}

echo "OK\n";
