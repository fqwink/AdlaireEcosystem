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

check_file Docker/Dockerfile.xserver
check_file Docker/docker-compose.xserver.yml
check_file public_html/.htaccess
check_file public_html/index.php
check_file public_html/dashboard.php
check_file public_html/assets/adlaire-ui.css
check_file Applications/.gitkeep
check_file storage/.gitkeep
check_file Core/Deployment.php
check_file Core/DeployConfig.php
check_file Core/Deployer.php
check_file Core/Kernel.php
check_file Core/Core.php
check_dir_absent DeploymentCore
check_dir_absent Frameworks/Deployment
check_dir_absent modules
check_file_absent DeploymentCore.php
check_file_absent Dockerfile.xserver
check_file_absent docker-compose.xserver.yml

if grep -q "DocumentRoot /var/www/html/public_html" Docker/Dockerfile.xserver; then
    echo "PASS document root: public_html"
else
    echo "FAIL document root must be public_html"
    failures=$((failures + 1))
fi

if grep -q "AllowOverride All" Docker/Dockerfile.xserver && grep -q "RewriteEngine On" public_html/.htaccess; then
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
        ! -path './Docker/docker-compose.xserver.yml' \
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
