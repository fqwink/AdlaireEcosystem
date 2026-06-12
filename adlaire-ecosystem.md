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

v0.284は安全版リリースバージョンである。安全版は既知バグ0件、release-check成功サマリー、Deployment Control Matrixのrelease allowed判定、5ファイル原則維持を同時に満たす場合のみ成立する。安全版指定はDistribution Manifestにも含め、配布対象から安全版条件を追跡できるようにする。Distribution Manifestは配布対象ファイルのsha256とManifest fingerprintを持つ。

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
| INI | 全面的に解禁 |
| 設定ファイル | `.ini`以外はフレームワーク全体で禁止 |
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

JavaScript Frameworkはダッシュボード表示補助に限定する。外部API、Public API、`.ini`以外の設定ファイル、JSON request/response helperには依存しない。DOM状態の読取、折りたたみ制御、タイムライン状態、リリースゲート表示、ダッシュボード状態通知のみを扱う。

Runtime FrameworkはHTTP実行入口、Index表示、Dashboard表示、Dashboard認証、Dashboardデータ収集を扱う。旧Frontend FrameworkはRuntime Frameworkへ集約済みであり、`Frameworks/Frontend/`は復活させない。Backend Frameworkに残るDatabase、Logger、Config、Middleware、Supportは次期整理でStorage/Support系へ再分類する。

Deployment CoreはGitHub Releases配信に伴い、Release Artifact Manifestを検証対象にする。Release Artifact Manifestは設定ファイルではなく、tag、artifact名、artifact実体パス、sha256、release-check結果、許可ファイル、除外ファイル、artifact内ファイル一覧、rollback対象、artifact取得方式を含むリリース証跡JSONである。Manifest検証、artifact取得plan、展開前preview、integrityは単一のevidence builderで組み立て、preflight、control snapshot、release evidence、最終planの間で判定材料を分岐させない。

Artifact取得方式は`push_artifact`、`pull_artifact`、`manual_upload`の3方式を許可する。既定はXserver等の制約に合わせて`push_artifact`とし、サーバ側ネットワーク取得を必須にしない。`pull_artifact`を使う場合はサーバ側ネットワーク利用を明示する。

Deployment Coreはartifact展開前にファイル一覧をpreviewし、相対パス安全性、allowlist適合、excluded files不一致を検証する。展開前previewは読み取り専用のリリース証跡であり、書き込みやコマンド実行を行わない。

artifact実体パスが提示された場合は、展開前にsha256を照合する。artifact実体が未提示の場合でもsha256宣言は必須とし、実体照合は取得後または転送後のリリース証跡として扱う。

Deployment Coreはrelease判定前に最終Deployment planを固定する。最終planはplan preview、Release Artifact Manifest、artifact取得方式、展開前preview、sha256照合、配置対象ファイルの内容hashを統合し、fingerprint付きの読み取り専用リリース証跡として扱う。最終plan固定ではファイル書き込み、コマンド実行、設定ファイル生成を行わない。

v0.285ではDeployment Execution Foundationを追加する。Execution Gateはstable release candidate gate、最終Deployment plan、最終plan fingerprint、安全スコアを統合し、fingerprint一致時のみapply可能判定を返す。Deployment Dry-runはExecution Gateの結果と最終plan fingerprintを返すが、applyは実行しない。Deployment Audit Ledgerはappend-only JSONL証跡として記録し、設定ファイルとして扱わない。Dashboard実行はこの時点では既定OFFであり、後続のsafety-gated方針へ接続する。

v0.286ではDashboard Deployment Controlを追加する。Runtime DashboardはExecution Gate View、Dry-run Panel、Audit Ledger Viewer、Decision Timelineを読み取り専用で表示する。deploy実行ボタン、Public API、JSON response helper、設定ファイルは追加しない。

自動デプロイメントはv0.290までに全面解禁する。v0.287ではCore auto deployment engineを追加し、Execution Gate、Dry-run fingerprint一致、Audit Ledger開始記録、apply、health check、history記録、Audit Ledger完了記録、失敗時rollbackを単一フローにする。v0.288ではDashboard実行tokenと明示確認を追加する。v0.289では自動rollbackとdeployment queue状態を強化する。v0.290では全面解禁をRelease Gateへ統合する。Public API、JSON response helper、設定ファイルは使わない。

v0.291からv0.295ではProvider API Deploymentを追加する。Provider APIはフレームワークのPublic APIではなく、Deployment Core内部のProvider Adapterが外部サーバ事業者APIまたはSSH/SFTP相当操作を呼ぶための内部機能である。v0.291でProvider Adapter仕様とCapability Matrix、v0.292でXserverレンタルサーバProfile、v0.293でXserver VPS Profile、v0.294でautoDeploy証跡統合、v0.295で他社サーバAPI追加用Generic Provider Registryを導入する。API tokenやcredentialは設定ファイルへ保存せず、Provider API結果は監査JSONL証跡として扱う。

v0.296からv0.305ではProvider Orchestrated Deploymentへ改変する。Provider Orchestrator、Remote Operation Plan、Provider Credential Policy、Provider API Transport Evidence、Xserver Profile execution標準化、Multi Provider Deployment Plan、Provider Health Probe、Provider Rollback Orchestrator、Dashboard Provider Control、Provider Orchestrated Deployment Gateを追加する。Provider API呼び出しは内部Adapterのみが扱い、request/responseはfingerprint化し、secret値は証跡に残さない。

v0.306からv0.311ではProvider Runtime Foundationを追加する。Provider Runtime Interface、Remote State Snapshot、Provider Transaction Plan、Provider Retry Backoff Policy、Provider Rate Limit Guard、Provider Secret Redaction Engineを導入し、サーバ事業者API、SSH、SFTP、VPS操作を同一Runtime契約へ集約する。credentialは実行時注入のみとし、設定ファイルへ保存しない。

v0.312からv0.320ではProvider Runtime Executionを追加する。Xserver Rental Runtime Adapter、Xserver VPS Runtime Adapter、Provider Runtime Execution Plan、Remote Artifact Lifecycle、Remote Release Switch Strategy、Provider Runtime Failure Classifier、Provider Runtime Recovery Plan、Dashboard Runtime Execution Control、Provider Runtime Execution Gateを導入する。レンタルサーバはpublic_html配布とmanual-requiredを明示し、VPSはSSH command、service restart、snapshot、rollback、health probeを扱う。

v0.321からv0.330ではProvider Runtime Operationsを追加する。Provider Runtime Operation Journal、Provider Runtime Credential Envelope、Provider Runtime Preflight、Provider Runtime Apply Plan、Provider Runtime Rollback Drill、Provider Runtime Health SLA、Provider Runtime Provider Registry、Provider Runtime Audit Bundle、Provider Runtime Operations Dashboard、Provider Runtime Operations Gateを導入する。サーバ事業者API、SSH、SFTP、VPS操作はPublic API化せず、Deployment Core内部のRuntime Operationsとして実行前検証、apply plan、rollback drill、health SLA、監査bundle、dashboard制御へ統合する。credentialは実行時注入のみで保存しない。

v0.331からv0.340ではServer API Executionを追加する。Server API Driver Contract、Server API Capability Probe、Server API Auth Session、Remote Command Sandbox、Server API Transaction Engine、Provider Drift Detection、Server API Governance、Multi Provider Failover Plan、Dashboard Server API Console、Server API Execution Gateを導入する。サーバ系APIはDeployment Core内部Driverとして扱い、任意コマンド実行、credential保存、Public API、MySQL対応を追加しない。XserverレンタルサーバとXserver VPSを初期標準Providerとし、他社ProviderはDriver ContractとCapability Probeで段階追加する。

v0.341からv0.350ではServer Automation Controlを追加し、サーバ操作の安全な自動実行を完全実装する。Server API Operation Catalog、Provider Execution Policy、Remote File Sync Plan、Server State Reconciliation、Safe Restart Orchestrator、Snapshot Backup Control、Server API Audit Trail、Deployment Recovery Engine、Dashboard Automation Console、Server Automation Release Gate、safe executionを導入する。自動実行は`Deployer::executeServerAutomation()`を正式入口とし、Release Gate通過、Operation Catalog可用性、Provider Execution Policy許可、Audit Trail生成を必須にする。任意shell、Public API、credential保存、MySQL対応は引き続き禁止する。

v0.351ではBug Zero Stabilizationを追加する。既知バグ0件、release-check成功、仕様と実装の整合、READMEと補助文書の詳細仕様重複なし、任意shellなし、Public APIなし、credential保存なし、MySQLなしを同時に満たすことを安定化条件にする。

Runtime DashboardはDeployment Control Matrixを表示する。Control Matrixはrelease readiness、stable release gate、Release Artifact Manifest、artifact取得、展開前preview、integrity、最終plan、execution gate view、dry-run panel、audit ledger viewer、release-check証跡を読み取り専用で集約する。Control Matrixはready/blockedの状態、ready件数サマリー、release allowed判定、blocked時の理由一覧、判定fingerprint、重要度、次アクションを持ち、ダッシュボード全体の状態判定へ参加する。任意deploy実行は無効であり、v0.350以降の許可済みサーバ操作のみ`Deployer::executeServerAutomation()`を通じてsafety-gatedで扱う。

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
| 安全版配布 | Distribution Manifestに安全版条件、配布ファイルsha256、Manifest fingerprintを含める | `Adlaire::distributionManifest()` |
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
| v0.285実行基盤 | Execution Gate、Dry-run fingerprint、append-only Audit Ledgerを追加し、ダッシュボード実行は無効のまま維持 | `Deployer::executionGate()` |
| v0.286制御盤 | DashboardにExecution Gate、Dry-run、Audit Ledger、Decision Timelineを読み取り専用で表示 | `AdlaireDashboardData::collect()` |
| v0.287-v0.290自動化 | Core自動実行からDashboard実行制御、rollback、queue、全面解禁Release Gateまで段階導入 | `Deployer::autoDeploy()` |
| v0.291-v0.295 Provider API | Xserverレンタル/VPS Profile、Provider Capability Matrix、Provider Execution Plan、Generic Provider Registryを追加 | `Deployer::providerCapabilityMatrix()` |
| v0.296-v0.305 Provider Orchestration | Provider Orchestrator、Remote Operation、Credential、Transport、Health、Rollback、Dashboard、Release Gateを統合 | `Deployer::providerOrchestratedReleaseGate()` |
| v0.306-v0.311 Provider Runtime | Runtime Interface、Remote State、Transaction、Retry、Rate Limit、Secret Redactionを追加 | `Deployer::providerRuntimeInterface()` |
| v0.312-v0.320 Provider Runtime Execution | Xserver Rental/VPS Adapter、Artifact Lifecycle、Switch、Failure、Recovery、Dashboard、Execution Gateを統合 | `Deployer::providerRuntimeExecutionGate()` |
| v0.321-v0.330 Provider Runtime Operations | Journal、Credential Envelope、Preflight、Apply Plan、Rollback Drill、Health SLA、Audit Bundle、Operations Gateを統合 | `Deployer::providerRuntimeOperationsGate()` |
| v0.331-v0.340 Server API Execution | Driver Contract、Capability Probe、Auth Session、Command Sandbox、Transaction、Drift、Governance、Failover、Dashboard、Execution Gateを統合 | `Deployer::serverApiExecutionGate()` |
| v0.341-v0.350 Server Automation Control | Operation Catalog、Execution Policy、File Sync、State Reconciliation、Restart、Snapshot、Audit、Recovery、Dashboard、Release Gate、safe executionを統合 | `Deployer::executeServerAutomation()` |
| v0.351 Bug Zero Stabilization | 既知バグ0件、文書重複0件、release-check成功、任意shell/Public API/credential保存/MySQLなしを固定 | `Adlaire::v0351BugZeroStabilizationPolicy()` |

既知バグ0件、root `DeploymentCore.php`不存在、`FrameworkCore/`不存在、5ファイル原則維持をリリース条件にする。

## GitHub安定版リリース配信

安定版はGitHub Releasesで配信する。`main`は安定版、`next`は次期開発、tagは`v0.xxx`形式にする。リリース前に`sh scripts/release-check.sh`を必ず成功させ、Release notesには仕様変更、破壊的変更、検証結果、配信対象を記載する。配信対象はリポジトリソース一式とし、`.DS_Store`、旧shim、設定ファイル、Public API helperは含めない。

## 設定ファイル方針

`*.ini`は全面的に解禁する。次のファイルはフレームワーク設定ファイルとして追加しない。

```text
.env*
*.conf
*.yaml
*.yml
config.php
*.config.php
```

例外はテスト基盤としての`Docker/docker-compose.xserver.yml`のみ。

## ダッシュボード実行方針

ダッシュボードからの任意デプロイ実行は有効化しない。v0.350以降は、Operation CatalogとProvider Execution Policyで許可されたサーバ操作のみ、CSRF token、短命実行token、明示確認、preflight、plan preview、rollback preview、安全スコア70以上、append-only audit trail、Server Automation Release Gateを満たす場合にsafety-gatedで扱う。

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
