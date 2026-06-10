<?php

/**
 * Adlaire Ecosystem - Deployment Framework bootstrap
 *
 * @version v0.277
 * @php     >= 8.3
 */

declare(strict_types=1);

if (PHP_VERSION_ID < 80300) {
    echo json_encode(['error' => 'Adlaire Ecosystem requires PHP 8.3 or higher. Current version: ' . PHP_VERSION]);
    exit(1);
}

require_once __DIR__ . '/DeployConfig.php';
require_once __DIR__ . '/Deployer.php';
require_once __DIR__ . '/DeploymentPaths.php';
require_once __DIR__ . '/DeploymentEvidence.php';
