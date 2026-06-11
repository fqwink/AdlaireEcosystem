<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);

require_once $root . '/Core/Core.php';

final class AdlaireIndexView
{
    public static function home(): string
    {
        return '<!doctype html><html lang="en"><head><meta charset="utf-8"><title>Adlaire Ecosystem</title></head><body>'
            . '<h1>Adlaire Ecosystem</h1>'
            . '<p>Version: ' . self::escape(Adlaire::version()) . '</p>'
            . '<p>Environment: ' . self::escape((string)Adlaire::env('APP_ENV', 'production')) . '</p>'
            . '</body></html>';
    }

    private static function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}

final class AdlaireIndexApplication
{
    public static function dispatch(): void
    {
        $router = new Router();

        $router->get('/', static function (Request $request, Response $response): never {
            header('Content-Type: text/html; charset=utf-8');
            echo AdlaireIndexView::home();
            exit;
        });

        $router->get('/health', static function (Request $request, Response $response): never {
            header('Content-Type: text/plain; charset=utf-8');
            echo 'ok';
            exit;
        });

        $router->dispatch(new Request(), new Response());
    }
}

Adlaire::init();
AdlaireIndexApplication::dispatch();
