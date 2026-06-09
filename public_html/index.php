<?php

declare(strict_types=1);

require_once __DIR__ . '/../FrameworkCore/Core.php';

Adlaire::init();

$router = new Router();

$router->get('/', static function (Request $request, Response $response): never {
    $response->json([
        'service' => 'Adlaire Ecosystem',
        'version' => Adlaire::version(),
        'environment' => Adlaire::env('APP_ENV', 'production'),
        'production_provider' => Adlaire::productionEnvironmentPolicy()['production_provider'],
    ]);
});

$router->get('/health', static function (Request $request, Response $response): never {
    $response->json([
        'status' => 'ok',
        'php' => PHP_VERSION,
        'document_root' => basename((string)($_SERVER['DOCUMENT_ROOT'] ?? '')),
    ]);
});

$router->dispatch(new Request(), new Response());
