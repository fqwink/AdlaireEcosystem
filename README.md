# Adlaire Ecosystem

Adlaire Ecosystemは、デプロイメント制御を中核にしたPHP 8.3+向け軽量フレームワークです。

仕様の正本は`adlaire-ecosystem.md`です。`adlaire-ecosystem.md` is the source of truth.

## 現行バージョン

`v0.263`: Pre-Integration Core Wiring Gate

v0.251-v0.260で、非デプロイ領域の移行単位、互換シム、内部契約検証、Dashboard連携境界、Pre-Integration Core Wiring Gateを固定しています。物理ファイル移動とDeploymentCore契約変更はまだ行いません。

## 主要方針

- Deployment Core互換性を維持する
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
