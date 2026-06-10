# Adlaire Ecosystem

Adlaire Ecosystemは、デプロイメント制御を中核にしたPHP 8.3+向け軽量フレームワークです。

仕様の正本は`adlaire-ecosystem.md`です。`adlaire-ecosystem.md` is the source of truth.

## 現行バージョン

`v0.272`: Framework Five File Highest Principle

v0.272で、各フレームワーク5ファイル原則を最高絶対原則へ格上げしました。Core / Deployment / Backend / Frontend / CSS / JavaScriptは各5ファイル構成を維持し、ルート`DeploymentCore.php`互換入口は維持します。

## 主要方針

- Deployment Core互換性を維持する
- 各フレームワークは5ファイル原則を維持する
- Public API: removed.
- Configuration files はフレームワーク設定として禁止
- JSONは監査、履歴、証跡、ログ、内部libSQL transport payload用途のみ許可
- SQLiteと内部libSQL transportを軸とする
- MySQL対応予定なし
- Xserverは本番同等検証プロファイルであり、フレームワーク前提ではない

## 主要ファイル

```text
DeploymentCore.php
FrameworkCore/
Core/
Frameworks/
public_html/
modules/
storage/
scripts/release-check.sh
tests/debug.php
adlaire-ecosystem.md
```

## 検証

```sh
sh scripts/release-check.sh
```

Docker equivalent:

```sh
docker run --rm -v "$PWD:/app" -w /app php:8.3-cli sh scripts/release-check.sh
```
