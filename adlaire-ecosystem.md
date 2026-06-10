# Adlaire Ecosystem 仕様

本ドキュメントはAdlaire Ecosystemの仕様上の唯一の根拠である。実装、テスト、修正、リリース判断はこの仕様に従う。

## 現行バージョン

| 項目 | 内容 |
|------|------|
| Current | v0.272 |
| Release | Framework Five File Highest Principle |
| Stable Target | v0.275 reorganized framework stable release |
| 中核軸 | デプロイメントシステム制御 |
| 再編状態 | Core / Deployment / Backend / Frontend / CSS / JavaScriptの5ファイル構成を最高絶対原則として固定。DeploymentCore互換入口は維持 |

## 最高絶対原則

1. 仕様に基づく実装
2. 各フレームワーク5ファイル原則

すべての変更は次の順序で進める。

| 順序 | 工程 | 必須内容 |
|------|------|----------|
| 1 | 仕様策定 | 変更対象、制約、禁止事項、受入条件を明記 |
| 2 | 実装計画 | 変更ファイル、検証方法、リリースゲート接続を明記 |
| 3 | 実装 | 仕様と計画に従い、コード、テスト、ドキュメントを変更 |

仕様が未定義の領域は実装しない。仕様と実装が異なる場合は仕様を正とする。Core / Deployment / Backend / Frontend / CSS / JavaScriptは各5ファイル構成を維持し、増減が必要な場合は最高絶対原則の変更として扱う。

## 基本方針

| 項目 | 方針 |
|------|------|
| Deployment Core | `DeploymentCore.php`を中核互換領域として維持 |
| Deployment Core破壊的変更 | 禁止 |
| Deployment Core互換性 | 維持 |
| 非デプロイ領域 | 互換性保証なし |
| 公開API | 廃止。復活させない |
| JSONレスポンス / JSONリクエスト補助 / CORS | 提供しない |
| 設定ファイル | フレームワーク全体で禁止 |
| JSON | 設定ファイルではなく、監査、履歴、証跡、ログ、内部DB transport payload用途のみ許可 |
| DB | SQLite / 内部libSQL transportを軸とする |
| MySQL | 対応予定なし |
| Xserver | 本番同等検証プロファイルとして維持。フレームワーク前提ではない |
| Composer | 不要 |
| PHP | 8.3以上 |

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

## 現行構成

```text
AdlaireEcosystem/
├── DeploymentCore.php
├── FrameworkCore/
│   ├── Core.php
│   ├── Kernel.php
│   ├── Extension.php
│   ├── Database.php
│   ├── Logger.php
│   ├── Config.php
│   ├── Middleware.php
│   └── Support.php
├── Core/
├── Frameworks/
│   ├── Deployment/
│   ├── Backend/
│   ├── Frontend/
│   └── CSS/
├── public_html/
│   ├── index.php
│   ├── dashboard.php
│   └── assets/adlaire-ui.css
├── modules/
├── storage/
├── scripts/
└── tests/debug.php
```

## v0.240 再編設計

v0.240は承認済みの再編設計仕様を固定するリリースである。物理ディレクトリ移動、DeploymentCore契約変更、ダッシュボード実行有効化、公開API復活、設定ファイル追加は行わない。

| Framework | 責務 | 将来ディレクトリ | 境界 |
|-----------|------|------------------|------|
| Core | 分類別フレームワークの内部契約連携 | `Core` | 公開API依存なし |
| Deployment Framework | manifest、readiness、rollback、安全証跡、デプロイ制御 | `Frameworks/Deployment` | 互換領域。契約破壊禁止 |
| Backend Framework | request、routing、middleware、validation、database、logging、support | `Frameworks/Backend` | 互換保証なし |
| Frontend Framework | dashboard view、deployment control presentation | `Frameworks/Frontend` | 互換保証なし |
| CSS Framework | Adlaire UI styling primitives | `Frameworks/CSS` | 設定ファイルなし |
| JavaScript Framework | 将来のprogressive dashboard interactions | `Frameworks/JavaScript` | 公開API依存なし。計画段階 |

## v0.240 で禁止する変更

| 禁止事項 | 理由 |
|----------|------|
| 物理ファイル移動 | v0.240は設計仕様のみ |
| DeploymentCore契約変更 | 中核互換領域を維持するため |
| ダッシュボード実行有効化 | Safety Gate、Adapter、Audit Trail、UI段階の仕様固定後に扱う |
| 公開API復活 | API Removal方針を維持するため |
| フレームワーク設定ファイル追加 | Configuration File Prohibition方針を維持するため |

## v0.275 までの大枠

| 範囲 | 目的 |
|------|------|
| v0.240 | 承認済み再編設計の固定 |
| v0.241-v0.250 | Directory Map、Namespace Plan、Dependency Boundary、内部契約、Dashboard境界、Pre-Migration Readiness Gateの固定 |
| v0.251-v0.260 | 非デプロイ領域の移行単位、互換シム、内部契約検証、Dashboard連携境界、Pre-Integration Core Wiring Gateの固定 |
| v0.261-v0.263 | Core / Backendの物理移動とFrameworkCore互換shimの配置 |
| v0.264 | Frontend PHP実体の移動とpublic_html互換shimの配置 |
| v0.265 | CSS Framework正本とpublic_html配信コピーの同期 |
| v0.266 | Dashboard Frontendの認証、データ収集、描画クラス分離 |
| v0.267 | Index FrontendのApplication/Viewクラス分離 |
| v0.268 | 旧設定フォルダ削除とv0.275安定版ターゲット変更 |
| v0.269 | DeploymentCore実体のFrameworks/Deployment移動 |
| v0.270 | Deployment FrameworkのDeployConfig / Deployer分割 |
| v0.271 | 各フレームワークの5ファイル原則適用 |
| v0.272 | 5ファイル原則を最高絶対原則へ格上げ |
| v0.273-v0.275 | Integration Core配線と安定版リリース判定 |

v0.240以降の物理再編、DeploymentCore契約変更、実行有効化は、変更事項を提示し、承認を得てから行う。

## v0.241-v0.250 再編準備

v0.241-v0.250は、物理移動前の準備フェーズである。現行ファイルと将来ディレクトリの対応、将来namespace、依存方向、内部契約、Dashboard制御境界、v0.251以降へ進めるための判定条件を固定する。

| Version | 内容 |
|---------|------|
| v0.241 | Directory Map |
| v0.242 | Namespace Plan |
| v0.243 | Dependency Boundary |
| v0.244 | Compatibility Gate |
| v0.245 | Migration Step Plan |
| v0.246 | Internal Contract Map |
| v0.247 | Dashboard Control Boundary |
| v0.248 | CSS / Frontend Split Plan |
| v0.249 | JavaScript Framework Plan |
| v0.250 | Pre-Migration Readiness Gate |

この範囲でも、物理ファイル移動、DeploymentCore契約変更、公開API復活、設定ファイル追加、ダッシュボード実行有効化は行わない。

## v0.251-v0.260 非デプロイ移行準備

v0.251-v0.260は、v0.261以降のIntegration Core配線に進むための準備フェーズである。Backend、Frontend、CSS、JavaScriptの移行単位、互換シム、内部契約検証、Dashboard連携境界、リスクゲートを固定する。

| Version | 内容 |
|---------|------|
| v0.251 | Backend Migration Unit Plan |
| v0.252 | Frontend Migration Unit Plan |
| v0.253 | CSS Framework Migration Unit Plan |
| v0.254 | JavaScript Framework Bootstrap Plan |
| v0.255 | Compatibility Shim Plan |
| v0.256 | Internal Contract Validation Matrix |
| v0.257 | Release Evidence Rewire Plan |
| v0.258 | Dashboard Control Integration Plan |
| v0.259 | Non-Deployment Migration Risk Gate |
| v0.260 | Pre-Integration Core Wiring Gate |

この範囲でも、物理ファイル移動、DeploymentCore契約変更、公開API復活、設定ファイル追加、ダッシュボード実行有効化は行わない。

## v0.261-v0.263 物理再編フェーズ1

v0.261-v0.263は、CoreとBackendの実体を分類別フレームワーク階層へ移動するフェーズである。`DeploymentCore.php`はルートに維持し、旧`FrameworkCore/*`は互換shimとして残す。

| 対象 | 方針 |
|------|------|
| Core | `Core/`へ実体を移動 |
| Backend | `Frameworks/Backend/`へ実体を移動 |
| FrameworkCore | 旧パス互換shimとして維持 |
| DeploymentCore | ルート配置と契約を維持 |
| Dashboard実行 | 引き続き未有効化 |

## v0.264 Frontend Reorganization Shim

v0.264は、Frontend PHPの実体を`Frameworks/Frontend/`へ移動し、Xserver互換の`public_html`入口をshimとして残すフェーズである。

| 対象 | 方針 |
|------|------|
| `Frameworks/Frontend/Index.php` | index本体 |
| `Frameworks/Frontend/Dashboard.php` | dashboard本体 |
| `public_html/index.php` | 互換shim |
| `public_html/dashboard.php` | 互換shim |
| `public_html/assets/adlaire-ui.css` | 公開CSSとして維持 |

## v0.265 CSS Framework Source Sync

v0.265は、CSS Frameworkの正本を`Frameworks/CSS/adlaire-ui.css`へ置き、`public_html/assets/adlaire-ui.css`を配信用コピーとして維持するフェーズである。

| 対象 | 方針 |
|------|------|
| `Frameworks/CSS/adlaire-ui.css` | CSS Framework正本 |
| `public_html/assets/adlaire-ui.css` | DocumentRoot配信コピー |
| release-check | 正本と配信コピーの同期を検証 |

## v0.266 Dashboard Frontend Class Extraction

v0.266は、Dashboardの認証、データ収集、HTML描画を専用クラスへ分離し、Dashboard entrypointを薄くするフェーズである。

| 対象 | 方針 |
|------|------|
| `Frameworks/Frontend/DashboardSecurity.php` | Dashboard認証 |
| `Frameworks/Frontend/DashboardData.php` | Dashboard表示データ収集 |
| `Frameworks/Frontend/DashboardView.php` | Dashboard HTML描画 |
| `Frameworks/Frontend/Dashboard.php` | 制御フローのみ |

## v0.267 Frontend Index Application Extraction

v0.267は、Index entrypointのルーティングとHTML描画を専用クラスへ分離し、`Frameworks/Frontend/Index.php`を薄くするフェーズである。

| 対象 | 方針 |
|------|------|
| `Frameworks/Frontend/Index.php` | Index routing / HTML / bootstrapを5ファイル原則に合わせて統合 |

## v0.268 Repository Cleanup and Stable Target

v0.268は、旧`config/`ツリーを削除し、安定版リリース目標を`v0.275 reorganized framework stable release`へ変更するフェーズである。

| 対象 | 方針 |
|------|------|
| `config/` | 旧Xserver設定フォルダとして削除 |
| `DeploymentCore.php` | 中核互換領域として維持 |
| `FrameworkCore/` | 互換shimとして維持 |
| `public_html/` | DocumentRoot互換入口として維持 |
| `modules/Auris/.gitkeep` | 将来モジュール枠として維持 |
| `Frameworks/JavaScript/` | 5ファイル原則に合わせた将来interactionファイルを配置 |

## v0.269 Deployment Framework Implementation Extraction

v0.269は、DeploymentCoreの実装本体を`Frameworks/Deployment/DeploymentCore.php`へ移動し、ルート`DeploymentCore.php`を互換shimとして維持するフェーズである。

| 対象 | 方針 |
|------|------|
| `Frameworks/Deployment/DeploymentCore.php` | Deployment Framework実体 |
| `DeploymentCore.php` | 互換入口。既存require先として維持 |
| `Frameworks/Deployment/.gitkeep` | 実体ファイル配置により削除 |
| DeploymentCore契約 | 破壊的変更なし |

## v0.270 Deployment Framework Class Split

v0.270は、Deployment Frameworkのクラスを専用ファイルへ分割し、`Frameworks/Deployment/DeploymentCore.php`を薄いbootstrapへ整理するフェーズである。

| 対象 | 方針 |
|------|------|
| `Frameworks/Deployment/DeployConfig.php` | DeployConfig専用ファイル |
| `Frameworks/Deployment/Deployer.php` | Deployer専用ファイル |
| `Frameworks/Deployment/DeploymentCore.php` | PHP version guardとクラス読み込みのみ |
| `DeploymentCore.php` | 互換入口として維持 |

## v0.272 Framework Five File Highest Principle

v0.272は、各フレームワーク5ファイル原則を最高絶対原則へ格上げするフェーズである。v0.271で整理した物理構成を維持し、以後の再編、実装、テスト、リリース判定に必ず適用する。

| Framework | 5ファイル |
|-----------|-----------|
| Core | `Core.php`, `Kernel.php`, `Extension.php`, `Registry.php`, `Lifecycle.php` |
| Deployment | `DeploymentCore.php`, `DeployConfig.php`, `Deployer.php`, `DeploymentPaths.php`, `DeploymentEvidence.php` |
| Backend | `Config.php`, `Database.php`, `Logger.php`, `Middleware.php`, `Support.php` |
| Frontend | `Index.php`, `Dashboard.php`, `DashboardSecurity.php`, `DashboardData.php`, `DashboardView.php` |
| CSS | `adlaire-ui.css`, `reset.css`, `layout.css`, `controls.css`, `dashboard.css` |
| JavaScript | `adlaire.js`, `controls.js`, `timeline.js`, `release-gate.js`, `dashboard-state.js` |

リリースチェックは上記6分類のファイル数を検査する。5ファイル原則を満たさない状態は安定版候補にできない。

## ダッシュボード実行方針

ダッシュボードからの任意デプロイ実行は将来機能である。現時点では既定OFFであり、実行は有効化しない。

必須条件:

| 条件 | 内容 |
|------|------|
| Safety Gate | CSRF token、短命実行token、明示確認、承認済みprofile |
| Preview | preflight、plan preview、rollback preview |
| Safety Score | 最低70 |
| Audit Trail | append-only、secret/token非保存 |
| UI | `run_deploy`、`confirm_apply`、remote state writeは無効 |

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

## 検証

公式リリース判定は次のコマンドで行う。

```sh
sh scripts/release-check.sh
```

Dockerで実行する場合:

```sh
docker run --rm -v "$PWD:/app" -w /app php:8.3-cli sh scripts/release-check.sh
```

検証対象:

| 項目 | 内容 |
|------|------|
| PHP lint | 主要PHPファイルの構文検査 |
| Official debug | `tests/debug.php` |
| Xserver profile audit | 本番同等プロファイルの構造検査 |
| Documentation consistency | MySQL対応予定なし、設定ファイル禁止、公開API廃止の整合性 |
