# Xserver Production-Equivalent Environment

This profile reproduces the documented Xserver rental server production shape for local verification.

## Profile

- Runtime: PHP 8.3 Apache (`php:8.3-apache`)
- Document root: `public_html`
- Rewrite layer: `.htaccess`
- Composer: not required
- App environment: `APP_ENV=production`
- Local database: SQLite-compatible file URL under `storage`
- Production database: use SQLite-compatible file URLs or internal libSQL API transport URLs through server environment variables; MySQL support is not planned

## Run

```sh
docker compose -f docker-compose.xserver.yml up -d --build
```

Open:

```text
http://localhost:8080/
http://localhost:8080/health
```

## Verify

```sh
docker run --rm -v "$PWD:/app" -w /app php:8.3-cli sh scripts/xserver-profile-audit.sh
```

The audit checks the Xserver profile files, `public_html` document root, `.htaccess` rewrite support, Composer-free operation, absence of the root `DeploymentCore.php` compatibility entrypoint and `DeploymentCore` directory, PHP lint, and the official debug test.

## Xserver Deployment Notes

Upload `public_html`, `Core`, `Frameworks`, `modules`, and the writable `storage` directory according to the deployment allowlist. The Deployment Framework entrypoint is `Frameworks/Deployment/DeploymentCore.php`; the root `DeploymentCore.php` compatibility entrypoint is intentionally absent. Keep real credentials out of source control and use server environment variables. Framework configuration files such as `.env`, `.ini`, `.conf`, `.yaml`, `.yml`, `config.php`, and `*.config.php` are prohibited; JSON is retained only for metadata, history, audit, release evidence, logs, and internal libSQL API transport payloads.
