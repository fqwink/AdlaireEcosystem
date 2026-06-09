#!/bin/sh
set -eu

failures=0

check_file() {
    if [ ! -f "$1" ]; then
        echo "FAIL missing file: $1"
        failures=$((failures + 1))
        return
    fi
    echo "PASS file: $1"
}

check_dir_absent() {
    if [ -d "$1" ]; then
        echo "FAIL prohibited directory exists: $1"
        failures=$((failures + 1))
        return
    fi
    echo "PASS prohibited directory absent: $1"
}

check_file Dockerfile.xserver
check_file docker-compose.xserver.yml
check_file .env.xserver.example
check_file config/xserver/apache/000-default.conf
check_file config/xserver/apache/xserver-profile.conf
check_file config/xserver/php.ini
check_file public_html/.htaccess
check_file public_html/index.php
check_file storage/.gitkeep
check_file DeploymentCore.php
check_file FrameworkCore/Core.php
check_dir_absent DeploymentCore

if grep -q "DocumentRoot /var/www/html/public_html" config/xserver/apache/000-default.conf; then
    echo "PASS document root: public_html"
else
    echo "FAIL document root must be public_html"
    failures=$((failures + 1))
fi

if grep -q "AllowOverride All" config/xserver/apache/000-default.conf && grep -q "RewriteEngine On" public_html/.htaccess; then
    echo "PASS htaccess rewrite profile"
else
    echo "FAIL htaccess rewrite profile"
    failures=$((failures + 1))
fi

if [ -f composer.json ]; then
    echo "FAIL composer must not be required for Xserver profile"
    failures=$((failures + 1))
else
    echo "PASS composer not required"
fi

if command -v php >/dev/null 2>&1; then
    php -d phar.readonly=0 -l public_html/index.php >/dev/null
    php -d phar.readonly=0 tests/debug.php >/dev/null
    echo "PASS php lint and official debug test"
else
    echo "SKIP php runtime checks: php command not found"
fi

if [ "$failures" -ne 0 ]; then
    echo "Xserver profile audit failed: $failures"
    exit 1
fi

echo "Xserver profile audit OK"
