# Adlaire Ecosystem 仕様

本ドキュメントはAdlaire Ecosystemの仕様上の唯一の根拠である。実装、テスト、修正、リリース判断はこの仕様に従う。

## 現行リリース

| 項目 | 内容 |
|------|------|
| Current | v0.284 |
| Release | Safe Stable Improvement Release |
| 方針 | 最新版仕様への再構築 |
| 互換性 | 保証なし |
| 中核軸 | デプロイメントシステム制御 |

v0.284は安全版リリースバージョンである。安全版は既知バグ0件、release-check成功サマリー、Deployment Control Matrixのrelease allowed判定、5ファイル原則維持を同時に満たす場合のみ成立する。

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
| リポジトリ衛生 | `.DS_Store`などのOSメタデータ、空の重複エージェント文書、README/docsへの詳細仕様重複は禁止 |

## 現行構成

```text
AdlaireEcosystem/
├── Core/
├── Frameworks/
│   ├── Backend/
│   ├── Runtime/
│   ├── CSS/
│   └── JavaScript/
├── public_html/
├── Applications/
├── Docker/
├── storage/
├── scripts/
└── tests/
```

各フレームワークは5ファイル構成を維持する。

| Framework | 5ファイル |
|-----------|-----------|
| Core | `Core.php`, `Kernel.php`, `Deployment.php`, `DeployConfig.php`, `Deployer.php` |
| Backend | `Config.php`, `Database.php`, `Logger.php`, `Middleware.php`, `Support.php` |
| Runtime | `Index.php`, `Dashboard.php`, `DashboardSecurity.php`, `DashboardData.php`, `DashboardView.php` |
| CSS | `adlaire-ui.css`, `reset.css`, `layout.css`, `controls.css`, `dashboard.css` |
| JavaScript | `adlaire.js`, `controls.js`, `timeline.js`, `release-gate.js`, `dashboard-state.js` |

JavaScript Frameworkはダッシュボード表示補助に限定する。外部API、Public API、設定ファイル、JSON request/response helperには依存しない。DOM状態の読取、折りたたみ制御、タイムライン状態、リリースゲート表示、ダッシュボード状態通知のみを扱う。

Runtime FrameworkはHTTP実行入口、Index表示、Dashboard表示、Dashboard認証、Dashboardデータ収集を扱う。旧Frontend FrameworkはRuntime Frameworkへ集約済みであり、`Frameworks/Frontend/`は復活させない。Backend Frameworkに残るDatabase、Logger、Config、Middleware、Supportは次期整理でStorage/Support系へ再分類する。

Deployment CoreはGitHub Releases配信に伴い、Release Artifact Manifestを検証対象にする。Release Artifact Manifestは設定ファイルではなく、tag、artifact名、artifact実体パス、sha256、release-check結果、許可ファイル、除外ファイル、artifact内ファイル一覧、rollback対象、artifact取得方式を含むリリース証跡JSONである。Manifest検証、artifact取得plan、展開前preview、integrityは単一のevidence builderで組み立て、preflight、control snapshot、release evidence、最終planの間で判定材料を分岐させない。

Artifact取得方式は`push_artifact`、`pull_artifact`、`manual_upload`の3方式を許可する。既定はXserver等の制約に合わせて`push_artifact`とし、サーバ側ネットワーク取得を必須にしない。`pull_artifact`を使う場合はサーバ側ネットワーク利用を明示する。

Deployment Coreはartifact展開前にファイル一覧をpreviewし、相対パス安全性、allowlist適合、excluded files不一致を検証する。展開前previewは読み取り専用のリリース証跡であり、書き込みやコマンド実行を行わない。

artifact実体パスが提示された場合は、展開前にsha256を照合する。artifact実体が未提示の場合でもsha256宣言は必須とし、実体照合は取得後または転送後のリリース証跡として扱う。

Deployment Coreはrelease判定前に最終Deployment planを固定する。最終planはplan preview、Release Artifact Manifest、artifact取得方式、展開前preview、sha256照合、配置対象ファイルの内容hashを統合し、fingerprint付きの読み取り専用リリース証跡として扱う。最終plan固定ではファイル書き込み、コマンド実行、設定ファイル生成を行わない。

Runtime DashboardはDeployment Control Matrixを表示する。Control Matrixはrelease readiness、stable release gate、Release Artifact Manifest、artifact取得、展開前preview、integrity、最終plan、release-check証跡を読み取り専用で集約する。Control Matrixはready/blockedの状態、ready件数サマリー、release allowed判定、blocked時の理由一覧、判定fingerprint、重要度、次アクションを持ち、ダッシュボード全体の状態判定へ参加する。ダッシュボードからのdeploy実行は引き続き無効であり、Control Matrixは実行ではなく制御判断の可視化に限定する。

## Deployment Core

Deployment Systemはこのフレームワークの中核であり、Coreとして扱う。

正式入口は`Core/Deployment.php`。rootの`DeploymentCore.php`互換入口と`Frameworks/Deployment/`は廃止済みであり、復活させない。`DeploymentCore`ディレクトリも作成しない。

## Application Modules

Application ModulesはCMS、EC、静的生成ジェネレーター、Wikiなどのアプリ機能層であり、Deployment Coreとは直接関係しない。

| 項目 | 方針 |
|------|------|
| 物理境界 | `Applications/` |
| 役割 | アプリケーション機能 |
| Deployment Core依存 | 禁止 |
| 旧`modules/` | 廃止。復活させない |
| 例 | CMS, Commerce, StaticGenerator, Wiki |
| 標準ファイル原則 | 1 Application = 5 files |

Deployment Coreは配置・実行基盤であり、Application Modulesはアプリ機能を構成する。両者を混ぜない。

## v0.284 安定版改善条件

| 項目 | 条件 | 判定 |
|------|------|------|
| ソース改善 | 大規模ソースコード改善45 cycleを維持 | `Adlaire::consolidatedSourceImprovementPolicy()` |
| 物理整理 | 物理移動整理、不要ファイル、不要フォルダ整理5 cycleを維持 | `Adlaire::physicalCleanupCyclePolicy()` |
| バグ修正 | 既知バグ0件 | `Adlaire::bugZeroRemediationPolicy()` |
| 安全版指定 | v0.284を安全版リリースバージョンとして扱う | `Adlaire::v0284StableReleasePolicy()` |
| JavaScript | 5ファイルを仮実装ではなく実体モジュールにする | `Adlaire::v0284StableReleasePolicy()` |
| リポジトリ衛生 | OSメタデータ、空の重複文書、旧入口を禁止 | `sh scripts/release-check.sh` |
| Runtime集約 | `Frameworks/Frontend/`を廃止し`Frameworks/Runtime/`を正式化 | `Adlaire::v0284StableReleasePolicy()` |
| GitHub配信 | tagとGitHub Releasesで安定版を配信 | `Adlaire::githubStableReleaseDistributionPolicy()` |
| Deployment改変 | Release Artifact Manifestをpreflight、control snapshot、release evidenceへ統合 | `Deployer::validateReleaseArtifactManifest()` |
| Artifact取得 | push/pull/manualの取得方式を証跡化し、安全判定へ統合 | `Deployer::artifactAcquisitionPlan()` |
| 展開前検証 | artifact内ファイル一覧を展開前にpreviewしallowlist/excluded filesと照合 | `Deployer::artifactPreExtractPreview()` |
| 実体照合 | artifact実体パスがある場合にsha256を照合 | `Deployer::artifactIntegrityCheck()` |
| 最終plan固定 | deploy対象と内容hashをfingerprint付きの読み取り専用planとして固定 | `Deployer::finalDeploymentPlan()` |
| Dashboard制御 | Deployment Control Matrixでrelease gateとartifact証跡を可視化 | `AdlaireDashboardData::collect()` |
| 文書整理 | READMEと検証手順書へ詳細仕様を重複記載しない | `sh scripts/release-check.sh` |
| Release証跡 | release-checkは名前付きPASSと成功サマリーを出力する | `sh scripts/release-check.sh` |

既知バグ0件、root `DeploymentCore.php`不存在、`FrameworkCore/`不存在、5ファイル原則維持をリリース条件にする。

## GitHub安定版リリース配信

安定版はGitHub Releasesで配信する。`main`は安定版、`next`は次期開発、tagは`v0.xxx`形式にする。リリース前に`sh scripts/release-check.sh`を必ず成功させ、Release notesには仕様変更、破壊的変更、検証結果、配信対象を記載する。配信対象はリポジトリソース一式とし、`.DS_Store`、旧shim、設定ファイル、Public API helperは含めない。

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

例外はテスト基盤としての`Docker/docker-compose.xserver.yml`のみ。

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
