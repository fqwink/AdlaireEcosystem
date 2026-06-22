#!/bin/sh
set -eu

: "${ADLAIRE_HEALTH_URL:=http://web/health}"
: "${ADLAIRE_SQLITE_PATH:=/data/adlaire-check.sqlite}"

php -r '
foreach (["json", "PDO", "pdo_sqlite"] as $extension) {
    if (!extension_loaded($extension)) {
        fwrite(STDERR, "missing extension: {$extension}\n");
        exit(1);
    }
}
echo "extensions ok\n";
'

php -r '
require "/app/Core/Database.php";
$path = getenv("ADLAIRE_SQLITE_PATH") ?: "/data/adlaire-check.sqlite";
@unlink($path);
AdlaireDatabase::reset();
$status = AdlaireDatabase::enableSQLite($path);
if (($status["enabled"] ?? false) !== true) {
    fwrite(STDERR, "sqlite enable failed\n");
    exit(1);
}
AdlaireDatabase::defineCollection("production_check", "system", ["name" => "string"], ["name"], "hard");
$record = AdlaireDatabase::create("production_check", ["name" => "docker"]);
AdlaireDatabase::reset();
AdlaireDatabase::enableSQLite($path);
$loaded = AdlaireDatabase::get("production_check", $record["id"]);
if (!is_array($loaded) || ($loaded["data"]["name"] ?? null) !== "docker") {
    fwrite(STDERR, "sqlite persistence failed\n");
    exit(1);
}
echo "sqlite persistence ok\n";
'

php -r '
$url = getenv("ADLAIRE_HEALTH_URL") ?: "http://web/health";
for ($i = 0; $i < 30; $i++) {
    $body = @file_get_contents($url);
    if ($body !== false) {
        $payload = json_decode($body, true);
        if (is_array($payload) && ($payload["status"] ?? null) === "ok") {
            echo "http health ok\n";
            exit(0);
        }
    }
    usleep(200000);
}
fwrite(STDERR, "http health failed\n");
exit(1);
'

php -r '
$body = file_get_contents("http://web/");
$payload = json_decode($body === false ? "" : $body, true);
if (!is_array($payload) || ($payload["database_ready"] ?? false) !== true || ($payload["sqlite_enabled"] ?? false) !== true) {
    fwrite(STDERR, "web database check failed\n");
    exit(1);
}
echo "web database ok\n";
'
