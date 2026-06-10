<?php

declare(strict_types=1);

final class AdlaireDashboardView
{
    public static function login(): string
    {
        return '<!doctype html><html><head><meta charset="utf-8"><title>Adlaire Dashboard</title><style>body{font-family:system-ui,sans-serif;margin:40px;max-width:420px}label,input,button{display:block;width:100%;box-sizing:border-box;margin-top:10px}input,button{padding:10px}button{cursor:pointer}</style></head><body><h1>Adlaire Dashboard</h1><form method="post"><label>Token<input type="password" name="token" autocomplete="current-password"></label><button type="submit">Open Dashboard</button></form></body></html>';
    }

    public static function render(array $data): string
    {
        $sections = is_array($data['sections'] ?? null) ? $data['sections'] : [];

        return '<!doctype html>
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
' . self::badge((string)($data['status'] ?? 'unknown')) . '
</header>
<main>
<div class="grid">
<section><h2>Overview</h2>' . self::table(self::section($sections, 'overview')) . '</section>
<section><h2>Security</h2>' . self::table(self::section($sections, 'security')) . '</section>
</div>
<section><h2>Health</h2>' . self::table(self::section($sections, 'health')['checks'] ?? []) . '</section>
<section><h2>Config Audit</h2>' . self::table(['valid' => self::section($sections, 'config_audit')['valid'] ?? false, 'checks' => self::section($sections, 'config_audit')['checks'] ?? [], 'details' => self::section($sections, 'config_audit')['details'] ?? []]) . '</section>
<section><h2>Release Readiness</h2>' . self::table(['ready' => self::section($sections, 'release_readiness')['ready'] ?? false, 'checks' => self::section($sections, 'release_readiness')['checks'] ?? []]) . '</section>
<section><h2>Deployment Control</h2>' . self::table(self::section($sections, 'deployment_control')) . '</section>
<section><h2>Safety Score</h2>' . self::table(self::section($sections, 'safety_score')) . '</section>
<section><h2>Deploy History</h2>' . self::table(self::section($sections, 'deploy_history')) . '</section>
<section><h2>Database</h2>' . self::table(self::section($sections, 'database')) . '</section>
<section><h2>Distribution</h2><details open><summary>Files</summary>' . self::table(['files' => self::section($sections, 'distribution')['files'] ?? []]) . '</details><details><summary>Required Verifications</summary>' . self::table(['required_verifications' => self::section($sections, 'distribution')['required_verifications'] ?? []]) . '</details></section>
</main>
</body>
</html>';
    }

    private static function badge(string $status): string
    {
        $class = $status === 'ok' || $status === 'ready' ? 'ok' : ($status === 'failed' ? 'failed' : 'warning');
        return '<span class="badge ' . $class . '">' . self::escape($status) . '</span>';
    }

    private static function table(array $rows): string
    {
        $html = '<table><tbody>';
        foreach ($rows as $key => $value) {
            if (is_bool($value)) {
                $value = $value ? 'true' : 'false';
            } elseif (is_array($value)) {
                $value = '<pre>' . self::escape(json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '[]') . '</pre>';
            } else {
                $value = self::escape((string)$value);
            }
            $html .= '<tr><th>' . self::escape((string)$key) . '</th><td>' . $value . '</td></tr>';
        }

        return $html . '</tbody></table>';
    }

    private static function section(array $sections, string $key): array
    {
        return is_array($sections[$key] ?? null) ? $sections[$key] : [];
    }

    private static function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}
