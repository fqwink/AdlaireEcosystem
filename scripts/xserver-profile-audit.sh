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

check_file_absent() {
    if [ -f "$1" ]; then
        echo "FAIL prohibited file exists: $1"
        failures=$((failures + 1))
        return
    fi
    echo "PASS prohibited file absent: $1"
}

check_file Dockerfile.xserver
check_file docker-compose.xserver.yml
check_file public_html/.htaccess
check_file public_html/index.php
check_file public_html/dashboard.php
check_file public_html/assets/adlaire-ui.css
check_file storage/.gitkeep
check_file Frameworks/Deployment/DeploymentCore.php
check_file Frameworks/Deployment/DeployConfig.php
check_file Frameworks/Deployment/Deployer.php
check_file Frameworks/Deployment/DeploymentPaths.php
check_file Frameworks/Deployment/DeploymentEvidence.php
check_file Core/Core.php
check_dir_absent DeploymentCore
check_file_absent DeploymentCore.php

if grep -q "DocumentRoot /var/www/html/public_html" Dockerfile.xserver; then
    echo "PASS document root: public_html"
else
    echo "FAIL document root must be public_html"
    failures=$((failures + 1))
fi

if grep -q "AllowOverride All" Dockerfile.xserver && grep -q "RewriteEngine On" public_html/.htaccess; then
    echo "PASS htaccess rewrite profile"
else
    echo "FAIL htaccess rewrite profile"
    failures=$((failures + 1))
fi

prohibited_config=$(
    find . \
        \( -path './.git' -o -path './.git/*' \) -prune -o \
        -type f \
        \( -name '.env*' -o -name '*.ini' -o -name '*.conf' -o -name '*.yaml' -o -name '*.yml' -o -name 'config.php' -o -name '*.config.php' \) \
        ! -path './docker-compose.xserver.yml' \
        -print
)
if [ -n "$prohibited_config" ]; then
    echo "FAIL prohibited framework configuration file found"
    echo "$prohibited_config"
    failures=$((failures + 1))
else
    echo "PASS framework configuration files prohibited"
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
