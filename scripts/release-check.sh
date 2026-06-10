#!/bin/sh
set -eu

php -d phar.readonly=0 -l Frameworks/Deployment/DeploymentCore.php >/dev/null
php -d phar.readonly=0 -l Frameworks/Deployment/DeployConfig.php >/dev/null
php -d phar.readonly=0 -l Frameworks/Deployment/Deployer.php >/dev/null
php -d phar.readonly=0 -l Frameworks/Deployment/DeploymentPaths.php >/dev/null
php -d phar.readonly=0 -l Frameworks/Deployment/DeploymentEvidence.php >/dev/null
php -d phar.readonly=0 -l Core/Registry.php >/dev/null
php -d phar.readonly=0 -l Core/Lifecycle.php >/dev/null

for file in Core/Core.php Core/Kernel.php Core/Extension.php Frameworks/Backend/Database.php Frameworks/Backend/Logger.php Frameworks/Backend/Config.php Frameworks/Backend/Middleware.php Frameworks/Backend/Support.php Frameworks/Frontend/Index.php Frameworks/Frontend/Dashboard.php Frameworks/Frontend/DashboardSecurity.php Frameworks/Frontend/DashboardData.php Frameworks/Frontend/DashboardView.php public_html/index.php public_html/dashboard.php tests/debug.php; do
    php -d phar.readonly=0 -l "$file" >/dev/null
done

php -d phar.readonly=0 tests/debug.php
sh scripts/xserver-profile-audit.sh

if [ -d FrameworkCore ]; then
    echo "Legacy FrameworkCore shim directory must be absent"
    exit 1
fi

if [ -d modules ]; then
    echo "Legacy modules directory must be absent"
    exit 1
fi

if [ -f DeploymentCore.php ]; then
    echo "Root DeploymentCore.php compatibility entrypoint must be absent"
    exit 1
fi

for dir in Core Frameworks/Deployment Frameworks/Backend Frameworks/Frontend Frameworks/CSS Frameworks/JavaScript; do
    count=$(find "$dir" -maxdepth 1 -type f | wc -l | tr -d ' ')
    if [ "$count" -ne 5 ]; then
        echo "Framework five-file principle failed: $dir has $count files"
        exit 1
    fi
done

for file in Frameworks/CSS/adlaire-ui.css Frameworks/CSS/reset.css Frameworks/CSS/layout.css Frameworks/CSS/controls.css Frameworks/CSS/dashboard.css Frameworks/JavaScript/adlaire.js Frameworks/JavaScript/controls.js Frameworks/JavaScript/timeline.js Frameworks/JavaScript/release-gate.js Frameworks/JavaScript/dashboard-state.js Applications/.gitkeep; do
    if [ ! -f "$file" ]; then
        echo "Required framework boundary file missing: $file"
        exit 1
    fi
done

cmp -s Frameworks/CSS/adlaire-ui.css public_html/assets/adlaire-ui.css

application_files=$(find Applications -type f | wc -l | tr -d ' ')
if [ "$application_files" -ne 1 ]; then
    echo "Applications boundary must only contain the placeholder until an application module is specified"
    exit 1
fi

if grep -Ei 'mysql-compatible|ignored deployment-specific env file' docs/xserver-production-equivalent.md >/dev/null; then
    echo "Xserver documentation contains stale database or env-file guidance"
    exit 1
fi

echo "Release check OK"
