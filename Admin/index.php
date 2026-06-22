<?php

declare(strict_types=1);

require_once __DIR__ . '/../Core/Database/Database.php';
require_once __DIR__ . '/../Core/Auth/Auth.php';
require_once __DIR__ . '/../Core/EventLog.php';

$databaseReadiness = AdlaireDatabase::readiness();
$databasePlanned = AdlaireDatabase::plannedState();
$authReadiness = AdlaireAuth::readiness();
$authPlanned = AdlaireAuth::plannedState();
$eventLogRole = AdlaireEventLog::role();
$eventLogRegistry = AdlaireEventLog::typeRegistry();

$dashboard = [
    'project' => 'Adlaire Ecosystem',
    'dashboard' => 'BaaS Admin Dashboard',
    'mode' => 'read_only',
    'baas_features' => [
        'Realtime Database',
        'Authentication / Authorization',
        'BaaS Admin Dashboard',
    ],
    'core_foundation' => [
        'Realtime Database' => [
            'file' => 'Core/Database/Database.php',
            'ready' => (bool)$databaseReadiness['ready'],
            'version' => (string)$databasePlanned['version'],
        ],
        'Authentication / Authorization' => [
            'file' => 'Core/Auth/Auth.php',
            'ready' => (bool)$authReadiness['ready'],
            'version' => (string)$authPlanned['version'],
        ],
        'Event Log' => [
            'file' => 'Core/EventLog.php',
            'ready' => $eventLogRole['trust_foundation'] === true,
            'version' => 'core_foundation',
        ],
    ],
    'operations_command_center' => [
        'current_status' => ((bool)$databaseReadiness['ready'] && (bool)$authReadiness['ready']) ? 'ready' : 'attention_required',
        'degraded_domain' => 'none',
        'active_incident' => 'none',
        'critical_warning' => 'none',
        'pending_manual_review' => 'none',
        'latest_trusted_event' => 'event_log_available',
    ],
    'severity_model' => [
        'levels' => ['info', 'warning', 'high', 'critical'],
        'domains' => ['database', 'event_log', 'authentication', 'authorization'],
    ],
    'incident_lifecycle' => [
        'states' => ['detected', 'reviewing', 'acknowledged', 'mitigated', 'closed'],
        'auto_resolution' => false,
    ],
    'manual_acknowledgement' => [
        'handled_fields' => ['actor', 'acknowledged_at', 'reason', 'evidence', 'sequence'],
        'auto_acknowledgement' => false,
    ],
    'evidence_integrity_view' => [
        'event_hash' => true,
        'previous_hash_continuity' => true,
        'snapshot_link' => true,
        'replay_proof' => true,
        'evidence_seal' => true,
    ],
    'prohibitions' => [
        'destructive_operation' => false,
        'auto_repair' => false,
        'auto_compaction' => false,
        'auto_delete' => false,
        'external_dependency' => false,
        'external_cdn' => false,
        'external_ui_library' => false,
        'external_authentication' => false,
        'docker_verification_report_management' => false,
    ],
    'core_boundary' => [
        'core_files' => ['Core/Database/Database.php', 'Core/EventLog.php', 'Core/Auth/Auth.php'],
        'core_folders' => ['Core/Database', 'Core/Auth'],
        'deployment_system' => 'blank',
        'runtime' => 'removed',
    ],
    'applications_modules' => [
        'admin_dashboard_included' => false,
        'baas_feature_included' => false,
    ],
    'event_log' => [
        'shared_by' => $eventLogRole['shared_by'],
        'domains' => $eventLogRegistry['domains'],
        'types_count' => count($eventLogRegistry['types']),
    ],
];

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function bool_label(bool $value): string
{
    return $value ? 'true' : 'false';
}

function status_class(bool $ready): string
{
    return $ready ? 'ok' : 'warn';
}

function render_list(array $items): string
{
    $html = '<ul>';
    foreach ($items as $item) {
        $html .= '<li>' . e((string)$item) . '</li>';
    }
    return $html . '</ul>';
}

function render_map(array $items): string
{
    $html = '<dl>';
    foreach ($items as $key => $value) {
        $html .= '<dt>' . e((string)$key) . '</dt>';
        if (is_bool($value)) {
            $html .= '<dd>' . e(bool_label($value)) . '</dd>';
        } elseif (is_array($value)) {
            $html .= '<dd>' . render_list($value) . '</dd>';
        } else {
            $html .= '<dd>' . e((string)$value) . '</dd>';
        }
    }
    return $html . '</dl>';
}

?><!doctype html>
<html lang="ja">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($dashboard['dashboard']) ?></title>
    <style>
        :root {
            color-scheme: light;
            --bg: #f7f8fa;
            --panel: #ffffff;
            --text: #1f2933;
            --muted: #627084;
            --line: #d9dee7;
            --ok: #146c43;
            --warn: #9a3412;
            --accent: #0f5c7a;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            background: var(--bg);
            color: var(--text);
            font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            font-size: 15px;
            line-height: 1.55;
        }
        header, main {
            max-width: 1180px;
            margin: 0 auto;
            padding: 24px;
        }
        header {
            border-bottom: 1px solid var(--line);
        }
        h1, h2, h3, p {
            margin: 0;
        }
        h1 {
            font-size: 28px;
            font-weight: 700;
        }
        h2 {
            font-size: 18px;
            margin-bottom: 12px;
        }
        h3 {
            font-size: 15px;
            margin-bottom: 8px;
        }
        .summary {
            color: var(--muted);
            margin-top: 6px;
        }
        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
            gap: 14px;
            margin-bottom: 18px;
        }
        section {
            background: var(--panel);
            border: 1px solid var(--line);
            border-radius: 8px;
            padding: 16px;
        }
        .status {
            display: inline-flex;
            align-items: center;
            min-height: 28px;
            padding: 2px 8px;
            border-radius: 6px;
            border: 1px solid var(--line);
            font-weight: 650;
        }
        .status.ok {
            color: var(--ok);
            border-color: #9bd3b4;
            background: #ecfdf3;
        }
        .status.warn {
            color: var(--warn);
            border-color: #fdba74;
            background: #fff7ed;
        }
        ul {
            margin: 0;
            padding-left: 18px;
        }
        li + li {
            margin-top: 4px;
        }
        dl {
            display: grid;
            grid-template-columns: minmax(120px, 0.8fr) minmax(0, 1.2fr);
            gap: 8px 12px;
            margin: 0;
        }
        dt {
            color: var(--muted);
            min-width: 0;
            overflow-wrap: anywhere;
        }
        dd {
            margin: 0;
            min-width: 0;
            overflow-wrap: anywhere;
            font-weight: 600;
        }
        .wide {
            grid-column: 1 / -1;
        }
        .feature {
            display: flex;
            justify-content: space-between;
            gap: 12px;
            align-items: flex-start;
        }
        .feature code {
            display: block;
            color: var(--muted);
            font-size: 13px;
            margin-top: 4px;
            white-space: normal;
            overflow-wrap: anywhere;
        }
        .accent {
            color: var(--accent);
            font-weight: 700;
        }
        @media (max-width: 640px) {
            header, main {
                padding: 18px;
            }
            dl {
                grid-template-columns: 1fr;
            }
            .feature {
                display: block;
            }
            .status {
                margin-top: 8px;
            }
        }
    </style>
</head>
<body>
<header>
    <h1><?= e($dashboard['dashboard']) ?></h1>
    <p class="summary"><?= e($dashboard['project']) ?> / <?= e($dashboard['mode']) ?></p>
</header>
<main>
    <div class="grid">
        <section>
            <h2>BaaS Features</h2>
            <?= render_list($dashboard['baas_features']) ?>
        </section>
        <section>
            <h2>Operations Command Center</h2>
            <?= render_map($dashboard['operations_command_center']) ?>
        </section>
    </div>

    <div class="grid">
        <?php foreach ($dashboard['core_foundation'] as $name => $core): ?>
            <section>
                <div class="feature">
                    <div>
                        <h2><?= e((string)$name) ?></h2>
                        <code><?= e((string)$core['file']) ?></code>
                        <p class="summary">version: <?= e((string)$core['version']) ?></p>
                    </div>
                    <span class="status <?= e(status_class((bool)$core['ready'])) ?>">
                        <?= e((bool)$core['ready'] ? 'ready' : 'attention') ?>
                    </span>
                </div>
            </section>
        <?php endforeach; ?>
    </div>

    <div class="grid">
        <section>
            <h2>Severity Model</h2>
            <?= render_map($dashboard['severity_model']) ?>
        </section>
        <section>
            <h2>Incident Lifecycle</h2>
            <?= render_map($dashboard['incident_lifecycle']) ?>
        </section>
        <section>
            <h2>Manual Acknowledgement</h2>
            <?= render_map($dashboard['manual_acknowledgement']) ?>
        </section>
        <section>
            <h2>Evidence Integrity View</h2>
            <?= render_map($dashboard['evidence_integrity_view']) ?>
        </section>
    </div>

    <div class="grid">
        <section>
            <h2>Event Log</h2>
            <?= render_map($dashboard['event_log']) ?>
        </section>
        <section>
            <h2>Core Boundary</h2>
            <?= render_map($dashboard['core_boundary']) ?>
        </section>
        <section>
            <h2>Applications Modules</h2>
            <?= render_map($dashboard['applications_modules']) ?>
        </section>
        <section>
            <h2>Prohibitions</h2>
            <?= render_map($dashboard['prohibitions']) ?>
        </section>
    </div>
</main>
</body>
</html>
