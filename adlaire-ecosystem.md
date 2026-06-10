# Adlaire Ecosystem 仕様

本ドキュメントはAdlaire Ecosystemの仕様上の唯一の根拠である。実装、テスト、修正、リリース判断はこの仕様に従う。

## 現行リリース

| 項目 | 内容 |
|------|------|
| Current | v0.277 |
| Release | Consolidated Breaking Development Release |
| 方針 | 最新版仕様への再構築 |
| 互換性 | 保証なし |
| 中核軸 | デプロイメントシステム制御 |

## 最高絶対原則

1. 仕様に基づく実装
2. 各フレームワーク5ファイル原則

仕様が未定義の領域は実装しない。仕様と実装が異なる場合は仕様を正とする。

## 開発順序

1. 仕様策定
2. 実装計画
3. 実装
4. テスト
5. ドキュメント整合

## 最新版仕様への再構築

旧版互換のための移行、shim、alias、旧入口、旧APIラッパーは作らない。最新版仕様を正として、不要な履歴、分岐、説明、旧構成を削る。

| 項目 | 方針 |
|------|------|
| 互換性 | リポジトリ全体で保証しない |
| 破壊的変更 | 許可 |
| Public API | 廃止。復活させない |
| API内部依存 | 避ける |
| 設定ファイル | フレームワーク全体で禁止 |
| JSON | 監査、履歴、証跡、ログ、内部libSQL transport payload用途のみ許可 |
| DB | SQLite / 内部libSQL transportを軸にする |
| MySQL | 対応予定なし |
| Xserver | 本番同等検証プロファイル。フレームワーク前提ではない |

## 現行構成

```text
AdlaireEcosystem/
├── Core/
├── Frameworks/
│   ├── Deployment/
│   ├── Backend/
│   ├── Frontend/
│   ├── CSS/
│   └── JavaScript/
├── public_html/
├── Applications/
├── storage/
├── scripts/
└── tests/
```

各フレームワークは5ファイル構成を維持する。

| Framework | 5ファイル |
|-----------|-----------|
| Core | `Core.php`, `Kernel.php`, `Extension.php`, `Registry.php`, `Lifecycle.php` |
| Deployment | `DeploymentCore.php`, `DeployConfig.php`, `Deployer.php`, `DeploymentPaths.php`, `DeploymentEvidence.php` |
| Backend | `Config.php`, `Database.php`, `Logger.php`, `Middleware.php`, `Support.php` |
| Frontend | `Index.php`, `Dashboard.php`, `DashboardSecurity.php`, `DashboardData.php`, `DashboardView.php` |
| CSS | `adlaire-ui.css`, `reset.css`, `layout.css`, `controls.css`, `dashboard.css` |
| JavaScript | `adlaire.js`, `controls.js`, `timeline.js`, `release-gate.js`, `dashboard-state.js` |

## Deployment Framework

正式入口は`Frameworks/Deployment/DeploymentCore.php`。rootの`DeploymentCore.php`互換入口は廃止済みであり、復活させない。`DeploymentCore`ディレクトリも作成しない。

Deployment Frameworkも互換性なしの破壊的変更を前提にする。

## Application Modules

Application ModulesはCMS、EC、静的生成ジェネレーター、Wikiなどのアプリ機能層であり、Deployment Frameworkとは直接関係しない。

| 項目 | 方針 |
|------|------|
| 物理境界 | `Applications/` |
| 役割 | アプリケーション機能 |
| Deployment依存 | 禁止 |
| 例 | CMS, Commerce, StaticGenerator, Wiki |
| 標準ファイル原則 | 1 Application = 5 files |

Deployment Frameworkは配置・実行基盤であり、Application Modulesはアプリ機能を構成する。両者を混ぜない。

## v0.277 統合開発条件

| Phase | 条件 | 判定 |
|-------|------|------|
| 1 | 大規模ソースコード改善45 cycle | `Adlaire::consolidatedSourceImprovementPolicy()` |
| 2 | 物理移動整理、不要ファイル、不要フォルダ整理5 cycle | `Adlaire::physicalCleanupCyclePolicy()` |
| 3 | バグ修正ゼロまで無制限で継続 | `Adlaire::bugZeroRemediationPolicy()` |

既知バグ0件、root `DeploymentCore.php`不存在、`FrameworkCore/`不存在、5ファイル原則維持をリリース条件にする。

## 禁止設定ファイル

次のファイルはフレームワーク設定ファイルとして追加しない。

```text
.env*
*.ini
*.conf
*.yaml
*.yml
config.php
*.config.php
```

例外はテスト基盤としての`docker-compose.xserver.yml`のみ。

## ダッシュボード実行方針

ダッシュボードからの任意デプロイ実行は将来機能である。現時点では既定OFFであり、実行は有効化しない。

有効化する場合も、CSRF token、短命実行token、明示確認、preflight、plan preview、rollback preview、安全スコア70以上、append-only audit trailを必須にする。

## ライセンスと参加方針

| 項目 | 方針 |
|------|------|
| ソース公開 | オープンソースとして公開 |
| 通常利用 | オープンソースライセンス |
| 再配布 | 商用利用ライセンス |
| 改変 | 商用利用ライセンス |
| クラウド事業利用 | オープンソースライセンス、商用利用ライセンスを問わず禁止 |
| 開発参加 | オープンコントリビューションではない |
| 変更権限 | プロジェクトが認めた開発主体のみ |

## リリースゲート

公式リリース判定は次のコマンドで行う。

```sh
sh scripts/release-check.sh
```

Dockerで実行する場合:

```sh
docker run --rm -v "$PWD:/app" -w /app php:8.3-cli sh scripts/release-check.sh
```

リリースゲートは、PHP lint、`tests/debug.php`、Xserver profile audit、設定ファイル禁止、Public API廃止、5ファイル原則、root `DeploymentCore.php`不存在を検査する。
