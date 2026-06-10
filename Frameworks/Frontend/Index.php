<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/FrameworkCore/Core.php';

Adlaire::init();

$router = new Router();

$router->get('/', static function (Request $request, Response $response): never {
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><title>Adlaire Ecosystem</title></head><body>';
    echo '<h1>Adlaire Ecosystem</h1>';
    echo '<p>Version: ' . htmlspecialchars(Adlaire::version(), ENT_QUOTES, 'UTF-8') . '</p>';
    echo '<p>Environment: ' . htmlspecialchars((string)Adlaire::env('APP_ENV', 'production'), ENT_QUOTES, 'UTF-8') . '</p>';
    echo '</body></html>';
    exit;
});

$router->get('/health', static function (Request $request, Response $response): never {
    header('Content-Type: text/plain; charset=utf-8');
    echo 'ok';
    exit;
});

$router->dispatch(new Request(), new Response());
