# Adlaire Ecosystem

Adlaire Ecosystemは、デプロイメント制御を中核にしたPHP 8.3+向け軽量フレームワークです。

仕様の正本は`adlaire-ecosystem.md`です。

## 現行バージョン

`v0.278`: Stable Improvement Release

v0.278は安定版改善リリースです。5ファイル原則、Deployment SystemのCore統合、Public API廃止、設定ファイル禁止、既知バグ0件をリリース条件にします。

## 主要方針

- Deployment Core互換性は保証しない
- Deployment Core正式入口は`Core/Deployment.php`
- 各フレームワークは5ファイル原則を維持する
- Public APIは廃止
- 設定ファイルはフレームワーク設定として禁止
- JSONは監査、履歴、証跡、ログ、内部libSQL transport payload用途のみ許可
- SQLiteと内部libSQL transportを軸とする
- MySQL対応予定なし
- Xserverは本番同等検証プロファイルであり、フレームワーク前提ではない

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

## 検証

```sh
sh scripts/release-check.sh
```

Dockerで検証する場合:

```sh
docker run --rm -v "$PWD:/app" -w /app php:8.3-cli sh scripts/release-check.sh
```
