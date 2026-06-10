#!/bin/sh
set -eu

php -d phar.readonly=0 -l DeploymentCore.php >/dev/null
php -d phar.readonly=0 -l Frameworks/Deployment/DeploymentCore.php >/dev/null
php -d phar.readonly=0 -l Frameworks/Deployment/DeployConfig.php >/dev/null
php -d phar.readonly=0 -l Frameworks/Deployment/Deployer.php >/dev/null
php -d phar.readonly=0 -l Frameworks/Deployment/DeploymentPaths.php >/dev/null
php -d phar.readonly=0 -l Frameworks/Deployment/DeploymentEvidence.php >/dev/null
php -d phar.readonly=0 -l Core/Registry.php >/dev/null
php -d phar.readonly=0 -l Core/Lifecycle.php >/dev/null

for file in FrameworkCore/Core.php FrameworkCore/Kernel.php FrameworkCore/Extension.php FrameworkCore/Database.php FrameworkCore/Logger.php FrameworkCore/Config.php FrameworkCore/Middleware.php FrameworkCore/Support.php Frameworks/Frontend/Index.php Frameworks/Frontend/Dashboard.php Frameworks/Frontend/DashboardSecurity.php Frameworks/Frontend/DashboardData.php Frameworks/Frontend/DashboardView.php public_html/index.php public_html/dashboard.php tests/debug.php; do
    php -d phar.readonly=0 -l "$file" >/dev/null
done

php -d phar.readonly=0 tests/debug.php
sh scripts/xserver-profile-audit.sh

for dir in Core Frameworks/Deployment Frameworks/Backend Frameworks/Frontend Frameworks/CSS Frameworks/JavaScript; do
    count=$(find "$dir" -maxdepth 1 -type f | wc -l | tr -d ' ')
    if [ "$count" -ne 5 ]; then
        echo "Framework five-file principle failed: $dir has $count files"
        exit 1
    fi
done

cmp -s Frameworks/CSS/adlaire-ui.css public_html/assets/adlaire-ui.css

if grep -Ei 'mysql-compatible|ignored deployment-specific env file' docs/xserver-production-equivalent.md >/dev/null; then
    echo "Xserver documentation contains stale database or env-file guidance"
    exit 1
fi

echo "Release check OK"
