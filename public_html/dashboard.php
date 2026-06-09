<?php

declare(strict_types=1);

require_once __DIR__ . '/../FrameworkCore/Core.php';

Adlaire::init();

function dashboard_forbidden(): never
{
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Forbidden';
    exit;
}

function dashboard_token_from_header(): ?string
{
    $header = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
    if (!is_string($header) || preg_match('/^Bearer\s+(.+)$/i', trim($header), $matches) !== 1) {
        return null;
    }
    return trim($matches[1]);
}

function dashboard_authorized(): bool
{
    if (!Adlaire::dashboardEnabled() || !Adlaire::dashboardTokenConfigured()) {
        return false;
    }

    $expected = (string)getenv('ADLAIRE_DASHBOARD_TOKEN');
    $provided = dashboard_token_from_header();
    if ($provided !== null && hash_equals($expected, $provided)) {
        return true;
    }

    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        $posted = is_string($_POST['token'] ?? null) ? (string)$_POST['token'] : '';
        if ($posted !== '' && hash_equals($expected, $posted)) {
            session_start();
            $_SESSION['adlaire_dashboard_token'] = hash('sha256', $posted);
            return true;
        }
    }

    session_start();
    $sessionToken = $_SESSION['adlaire_dashboard_token'] ?? null;
    return is_string($sessionToken) && hash_equals(hash('sha256', $expected), $sessionToken);
}

function dashboard_badge(string $status): string
{
    $class = $status === 'ok' || $status === 'ready' ? 'ok' : ($status === 'failed' ? 'failed' : 'warning');
    return '<span class="badge ' . $class . '">' . htmlspecialchars($status, ENT_QUOTES, 'UTF-8') . '</span>';
}

function dashboard_table(array $rows): string
{
    $html = '<table><tbody>';
    foreach ($rows as $key => $value) {
        if (is_bool($value)) {
            $value = $value ? 'true' : 'false';
        } elseif (is_array($value)) {
            $value = '<pre>' . htmlspecialchars(json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '[]', ENT_QUOTES, 'UTF-8') . '</pre>';
        } else {
            $value = htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
        }
        $html .= '<tr><th>' . htmlspecialchars((string)$key, ENT_QUOTES, 'UTF-8') . '</th><td>' . $value . '</td></tr>';
    }
    return $html . '</tbody></table>';
}

function dashboard_collect_data(): array
{
    $health = Adlaire::health([
        'writable_paths' => [
            'storage' => dirname(__DIR__) . '/storage',
        ],
    ]);
    $configAudit = Adlaire::configAudit([
        'writable_paths' => [
            'storage' => dirname(__DIR__) . '/storage',
        ],
    ]);
    $releaseReadiness = Adlaire::releaseReadiness();
    $distribution = Adlaire::distributionManifest();
    $database = ['configured' => false];

    try {
        $database = [
            'configured' => true,
            'runtime_profile' => Database::default()->runtimeProfile(),
        ];
    } catch (Throwable) {
    }

    $failed = ($health['status'] ?? 'failed') !== 'ok'
        || ($configAudit['valid'] ?? false) !== true
        || ($releaseReadiness['ready'] ?? false) !== true;

    return [
        'status' => $failed ? 'failed' : 'ok',
        'version' => Adlaire::version(),
        'sections' => [
            'overview' => [
                'framework_version' => Adlaire::version(),
                'runtime_status' => $health['status'] ?? 'unknown',
                'release_ready' => $releaseReadiness['ready'] ?? false,
                'environment' => Adlaire::env('APP_ENV', 'production'),
                'php_version' => PHP_VERSION,
            ],
            'health' => $health,
            'config_audit' => $configAudit,
            'release_readiness' => [
                'ready' => $releaseReadiness['ready'] ?? false,
                'checks' => $releaseReadiness['checks'] ?? [],
            ],
            'deployment_control' => [
                'control_visibility' => Adlaire::dashboardControlVisibilityPolicy(),
                'control_report' => Adlaire::deploymentControlReportPolicy(),
                'stable_release_gate' => Adlaire::stableReleaseGatePolicy(),
            ],
            'safety_score' => Adlaire::deploymentSafetyScorePolicy(),
            'deploy_history' => Adlaire::deploymentHistoryVisualizationPolicy(),
            'distribution' => [
                'version' => $distribution['version'] ?? Adlaire::version(),
                'files' => $distribution['files'] ?? [],
                'required_verifications' => Adlaire::audit()['required_verifications'] ?? [],
            ],
            'database' => $database,
            'security' => [
                'dashboard_enabled' => Adlaire::dashboardEnabled(),
                'auth_required' => Adlaire::dashboardPolicy()['auth_required'],
                'auth_configured' => Adlaire::dashboardTokenConfigured(),
                'app_debug' => Adlaire::env('APP_DEBUG', false),
                'secret_values_exposed' => false,
            ],
        ],
    ];
}

if (!dashboard_authorized()) {
    http_response_code(403);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><html><head><meta charset="utf-8"><title>Adlaire Dashboard</title><style>body{font-family:system-ui,sans-serif;margin:40px;max-width:420px}label,input,button{display:block;width:100%;box-sizing:border-box;margin-top:10px}input,button{padding:10px}button{cursor:pointer}</style></head><body><h1>Adlaire Dashboard</h1><form method="post"><label>Token<input type="password" name="token" autocomplete="current-password"></label><button type="submit">Open Dashboard</button></form></body></html>';
    exit;
}

$data = dashboard_collect_data();

header('Content-Type: text/html; charset=utf-8');
$sections = $data['sections'];
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Adlaire Operations Dashboard</title>
<link rel="stylesheet" href="/assets/adlaire-ui.css">
</head>
<body>
<header>
<h1>Adlaire Operations Dashboard</h1>
<?= dashboard_badge((string)$data['status']) ?>
</header>
<main>
<div class="grid">
<section><h2>Overview</h2><?= dashboard_table($sections['overview']) ?></section>
<section><h2>Security</h2><?= dashboard_table($sections['security']) ?></section>
</div>
<section><h2>Health</h2><?= dashboard_table($sections['health']['checks'] ?? []) ?></section>
<section><h2>Config Audit</h2><?= dashboard_table(['valid' => $sections['config_audit']['valid'] ?? false, 'checks' => $sections['config_audit']['checks'] ?? [], 'details' => $sections['config_audit']['details'] ?? []]) ?></section>
<section><h2>Release Readiness</h2><?= dashboard_table(['ready' => $sections['release_readiness']['ready'] ?? false, 'checks' => $sections['release_readiness']['checks'] ?? []]) ?></section>
<section><h2>Deployment Control</h2><?= dashboard_table($sections['deployment_control']) ?></section>
<section><h2>Safety Score</h2><?= dashboard_table($sections['safety_score']) ?></section>
<section><h2>Deploy History</h2><?= dashboard_table($sections['deploy_history']) ?></section>
<section><h2>Database</h2><?= dashboard_table($sections['database']) ?></section>
<section><h2>Distribution</h2><details open><summary>Files</summary><?= dashboard_table(['files' => $sections['distribution']['files'] ?? []]) ?></details><details><summary>Required Verifications</summary><?= dashboard_table(['required_verifications' => $sections['distribution']['required_verifications'] ?? []]) ?></details></section>
</main>
</body>
</html>
