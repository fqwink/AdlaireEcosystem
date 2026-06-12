#!/bin/sh
set -eu

checks_passed=0
pass() {
    checks_passed=$((checks_passed + 1))
    echo "PASS release-check:$1"
}

php -d phar.readonly=0 -l Core/Deployment.php >/dev/null
php -d phar.readonly=0 -l Core/DeployConfig.php >/dev/null
php -d phar.readonly=0 -l Core/Deployer.php >/dev/null
php -d phar.readonly=0 -l Core/Core.php >/dev/null
php -d phar.readonly=0 -l Core/Kernel.php >/dev/null

for file in Frameworks/Backend/Database.php Frameworks/Backend/Logger.php Frameworks/Backend/Config.php Frameworks/Backend/Middleware.php Frameworks/Backend/Support.php Frameworks/Runtime/Index.php Frameworks/Runtime/Dashboard.php Frameworks/Runtime/DashboardSecurity.php Frameworks/Runtime/DashboardData.php Frameworks/Runtime/DashboardView.php public_html/index.php public_html/dashboard.php tests/debug.php; do
    php -d phar.readonly=0 -l "$file" >/dev/null
done
pass "php_lint"

php -d phar.readonly=0 tests/debug.php
pass "official_debug_test"

sh scripts/xserver-profile-audit.sh
pass "xserver_profile_audit"

if [ -d FrameworkCore ]; then
    echo "Legacy FrameworkCore shim directory must be absent"
    exit 1
fi

if [ -d Frameworks/Deployment ]; then
    echo "Deployment system must be integrated into Core"
    exit 1
fi

if [ -d Frameworks/Frontend ]; then
    echo "Frontend framework must be aggregated into Runtime"
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

if [ -f Dockerfile.xserver ] || [ -f docker-compose.xserver.yml ]; then
    echo "Docker files must be collected under Docker/"
    exit 1
fi

if find . \( -path './.git' -o -path './.git/*' \) -prune -o -name .DS_Store -type f -print | grep . >/dev/null; then
    echo "OS metadata files must not be committed or left in the repository"
    exit 1
fi

if [ -f CLAUDE.md ]; then
    echo "Empty duplicate agent documentation must be absent; use AGENTS.md"
    exit 1
fi
pass "legacy_paths_absent"

for dir in Core Frameworks/Backend Frameworks/Runtime Frameworks/CSS Frameworks/JavaScript; do
    count=$(find "$dir" -maxdepth 1 -type f | wc -l | tr -d ' ')
    if [ "$count" -ne 5 ]; then
        echo "Framework five-file principle failed: $dir has $count files"
        exit 1
    fi
done
pass "framework_five_file_principle"

for file in Frameworks/CSS/adlaire-ui.css Frameworks/CSS/reset.css Frameworks/CSS/layout.css Frameworks/CSS/controls.css Frameworks/CSS/dashboard.css Frameworks/JavaScript/adlaire.js Frameworks/JavaScript/controls.js Frameworks/JavaScript/timeline.js Frameworks/JavaScript/release-gate.js Frameworks/JavaScript/dashboard-state.js Applications/.gitkeep; do
    if [ ! -f "$file" ]; then
        echo "Required framework boundary file missing: $file"
        exit 1
    fi
done
pass "required_boundary_files"

if grep -Ri "placeholder" Frameworks/JavaScript >/dev/null; then
    echo "JavaScript framework files must contain implemented modules, not placeholder text"
    exit 1
fi
pass "javascript_implemented"

cmp -s Frameworks/CSS/adlaire-ui.css public_html/assets/adlaire-ui.css
pass "css_asset_sync"

application_files=$(find Applications -type f | wc -l | tr -d ' ')
if [ "$application_files" -ne 1 ]; then
    echo "Applications boundary must only contain the placeholder until an application module is specified"
    exit 1
fi
pass "application_boundary"

empty_dirs=$(find . \( -path './.git' -o -path './.git/*' \) -prune -o -type d -empty -print)
if [ -n "$empty_dirs" ]; then
    echo "Empty directories must be removed or represented by an approved marker file"
    echo "$empty_dirs"
    exit 1
fi
pass "empty_directories_absent"

if grep -Ei 'mysql-compatible|ignored deployment-specific env file' docs/xserver-production-equivalent.md >/dev/null; then
    echo "Xserver documentation contains stale database or env-file guidance"
    exit 1
fi

if grep -E 'Public API|MySQL|Xserver|GitHub Releases|設定ファイル|互換性|破壊的変更|SQLite|libSQL|5ファイル' README.md >/dev/null; then
    echo "README must stay short and must not duplicate detailed specification policy"
    exit 1
fi
if ! grep -q '詳細仕様と設計判断は正本へ集約します' README.md; then
    echo "README must delegate detailed specification and design decisions to the source of truth"
    exit 1
fi

if grep -E 'Deployment Core|Runtime|GitHub Releases|DB方針|Public API|MySQL|SQLite|libSQL|設定ファイル' docs/xserver-production-equivalent.md >/dev/null; then
    echo "Xserver verification documentation must not duplicate broad specification policy"
    exit 1
fi
if ! grep -q '## 文書構成' adlaire-ecosystem.md; then
    echo "Specification must document repository document roles"
    exit 1
fi
if ! grep -q '設計判断やリリース条件は`adlaire-ecosystem.md`に集約する' docs/xserver-production-equivalent.md; then
    echo "Xserver verification documentation must delegate design decisions to the source of truth"
    exit 1
fi
pass "documentation_deduplication"

echo "Release check OK: ${checks_passed} checks"
