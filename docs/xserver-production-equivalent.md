# Xserver Production-Equivalent Environment

This profile reproduces the documented Xserver rental server production shape for local verification.

## Profile

- Runtime: PHP 8.3 Apache (`php:8.3-apache`)
- Document root: `public_html`
- Rewrite layer: `.htaccess`
- Composer: not required
- App environment: `APP_ENV=production`
- Local database: SQLite-compatible file URL under `storage`
- Production database: replace `ADLAIRE_DATABASE_URL` with the Xserver MySQL-compatible connection used by the application layer

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

The audit checks the Xserver profile files, `public_html` document root, `.htaccess` rewrite support, Composer-free operation, absence of a `DeploymentCore` directory, PHP lint, and the official debug test.

## Xserver Deployment Notes

Upload `public_html`, `DeploymentCore.php`, `FrameworkCore`, `modules`, and the writable `storage` directory according to the deployment allowlist. Keep real credentials out of source control; use server environment variables or an ignored deployment-specific env file.
