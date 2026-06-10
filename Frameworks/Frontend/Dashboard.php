<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);

require_once $root . '/FrameworkCore/Core.php';
require_once __DIR__ . '/DashboardSecurity.php';
require_once __DIR__ . '/DashboardData.php';
require_once __DIR__ . '/DashboardView.php';

Adlaire::init();

if (!AdlaireDashboardSecurity::authorized()) {
    http_response_code(403);
    header('Content-Type: text/html; charset=utf-8');
    echo AdlaireDashboardView::login();
    exit;
}

header('Content-Type: text/html; charset=utf-8');
echo AdlaireDashboardView::render(AdlaireDashboardData::collect($root));
