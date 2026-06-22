#!/bin/sh
set -eu

mkdir -p /data /var/www/html
chmod 0777 /data

cat > /var/www/html/index.php <<'PHP'
<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

$path = getenv('ADLAIRE_SQLITE_PATH') ?: '/data/adlaire.sqlite';
$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$payload = [
    'status' => 'ok',
    'service' => 'adlaire-ecosystem',
    'php' => PHP_VERSION,
    'sqlite_path' => $path,
    'extensions' => [
        'json' => extension_loaded('json'),
        'PDO' => extension_loaded('PDO'),
        'pdo_sqlite' => extension_loaded('pdo_sqlite'),
    ],
];

if (!str_starts_with($uri, '/health')) {
    require_once '/app/Core/Database.php';
    AdlaireDatabase::reset();
    $storage = AdlaireDatabase::enableSQLite($path);
    $payload['database_ready'] = AdlaireDatabase::readiness()['ready'];
    $payload['sqlite_enabled'] = $storage['enabled'] ?? false;
}

echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
PHP

mkdir -p /var/www/html/health
cp /var/www/html/index.php /var/www/html/health/index.php

exec "$@"
