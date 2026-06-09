# Adlaire Ecosystem

Adlaire Ecosystem is a lightweight PHP 8.3+ backend framework centered on a deployment control system.

The source of truth is [`adlaire-ecosystem.md`](adlaire-ecosystem.md). This README is a short operational entry point for repository structure, constraints, and verification.

## Current Direction

- Core axis: deployment system control.
- Deployment compatibility: maintained for `DeploymentCore.php` and deployment contracts.
- Non-deployment compatibility: not guaranteed unless the current specification, official debug tests, release requirements, and distribution manifest agree.
- Public API: removed. JSON response helpers, JSON request helpers, CORS helpers, and public HTTP/JSON API surfaces are not provided.
- Database axis: SQLite-compatible file URLs and internal libSQL API transport. MySQL support is not planned.
- Configuration files: framework configuration files are prohibited. `.env`, `.ini`, `.conf`, `.yaml`, `.yml`, `config.php`, and `*.config.php` must not be used as framework configuration files.
- JSON: retained only for metadata, history, audit artifacts, release evidence, logs, and internal libSQL API transport payloads.
- Xserver: supported as a production-equivalent local verification profile, but the framework does not require Xserver as a premise.

## Repository Layout

```text
DeploymentCore.php              Deployment control core
FrameworkCore/                  Framework core files
public_html/                    Public document root
public_html/assets/             Dashboard UI asset
modules/                        Module root
storage/                        Writable runtime storage
scripts/release-check.sh        Release verification entry point
scripts/xserver-profile-audit.sh Xserver profile audit
tests/debug.php                 Official debug test suite
docs/                           Operational documentation
adlaire-ecosystem.md            Specification source of truth
```

## Documentation Map

- `README.md`: short entry point and operational summary.
- `adlaire-ecosystem.md`: source of truth for specification and release history.
- `docs/xserver-production-equivalent.md`: Xserver production-equivalent verification notes.
- `tests/debug.php`: executable specification checks.

## Core Components

- `DeploymentCore.php`: deployment validation, dry run, backup, rollback, preflight, plan preview, compatibility snapshot, safety score, control report, evidence bundle, and release candidate gate.
- `FrameworkCore/Core.php`: request, response, router, validator, policies, audit metadata, release readiness, and specification drift checks.
- `FrameworkCore/Database.php`: SQLite and internal libSQL API transport, query builder, transaction handling, migrations, and query logging.
- `FrameworkCore/Kernel.php`: microkernel service container, extension lifecycle, event bus, and module messaging.
- `public_html/dashboard.php`: authenticated, read-only HTML dashboard for deployment control visibility.

## Development Rule

All repository changes follow the documented order:

1. Specification
2. Implementation plan
3. Implementation

This applies to code, tests, debugging, documentation, release work, and maintenance. There are no exempt paths.

## Verification

Use the release check as the main verification command:

```sh
sh scripts/release-check.sh
```

When local PHP is unavailable, run the same check through PHP 8.3 Docker:

```sh
docker run --rm -v "$PWD:/app" -w /app php:8.3-cli sh scripts/release-check.sh
```

The release check runs PHP lint, the official debug test suite, the Xserver profile audit, and documentation consistency checks.

## Xserver Production-Equivalent Profile

The Xserver profile is documented in [`docs/xserver-production-equivalent.md`](docs/xserver-production-equivalent.md).

Local profile:

```sh
docker compose -f docker-compose.xserver.yml up -d --build
```

Stop the profile:

```sh
docker compose -f docker-compose.xserver.yml down
```

The public routes are:

```text
/
/health
```

The dashboard is HTML-only and requires `ADLAIRE_DASHBOARD_ENABLED` and `ADLAIRE_DASHBOARD_TOKEN`.

## Release Notes

The current line is `v0.234`, focused on Integration Core as the coordination layer for the v0.270 reorganization target. Integration Core connects classified framework families through registry, lifecycle, dependency, audit, release readiness, and deployment-control responsibilities.

Before treating a build as stable, confirm:

- `tests/debug.php` passes.
- `scripts/release-check.sh` passes.
- `git diff --check` passes.
- No framework configuration files are introduced.
- No public API or JSON response surface is reintroduced.
- Deployment Core compatibility is preserved.
