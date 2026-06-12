# Adlaire Ecosystem

Adlaire Ecosystemは、配布と運用の制御を中核にしたPHP 8.3+向け軽量フレームワークです。

仕様の正本は`adlaire-ecosystem.md`です。詳細仕様と設計判断は正本へ集約します。

## 現行バージョン

`v0.284`: Safe Stable Improvement Release

## 主要ファイル

```text
Core/
Frameworks/
public_html/
Applications/
Docker/
storage/
scripts/release-check.sh
tests/debug.php
adlaire-ecosystem.md
```

補助文書は`docs/`配下に置きます。

## 検証

```sh
sh scripts/release-check.sh
```

Dockerで検証する場合:

```sh
docker run --rm -v "$PWD:/app" -w /app php:8.3-cli sh scripts/release-check.sh
```
