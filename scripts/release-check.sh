#!/bin/sh
set -eu

php -d phar.readonly=0 -l DeploymentCore.php >/dev/null

for file in FrameworkCore/Core.php FrameworkCore/Kernel.php FrameworkCore/Extension.php FrameworkCore/Database.php FrameworkCore/Logger.php FrameworkCore/Config.php FrameworkCore/Middleware.php FrameworkCore/Support.php public_html/index.php public_html/dashboard.php tests/debug.php; do
    php -d phar.readonly=0 -l "$file" >/dev/null
done

php -d phar.readonly=0 tests/debug.php
sh scripts/xserver-profile-audit.sh

if grep -Ei 'mysql-compatible|ignored deployment-specific env file' docs/xserver-production-equivalent.md >/dev/null; then
    echo "Xserver documentation contains stale database or env-file guidance"
    exit 1
fi

echo "Release check OK"
