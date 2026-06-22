# Adlaire Ecosystem Specification

Adlaire Ecosystemは`v0.021`としてBaaS Projectの実運用土台を強化する。

本ドキュメントは、Adlaire Ecosystemにおける仕様の最高準拠ドキュメントである。

仕様判断、Core仕様、機能仕様、禁止仕様は本ドキュメントを正とする。作業エージェントの承認プロセス、作業ルール、編集制約は`docs/AGENTS.md`を正とする。

## Project Definition

| 項目 | 内容 |
|------|------|
| Name | Adlaire Ecosystem |
| Version | v0.021 |
| Type | BaaS Project |
| Policy | Zero-base restart |
| Compatibility | 未定義 |

名称はAdlaire Ecosystemを継承する。実装は`v0.019`仕様を基礎に、Auth Coreを`v0.021`で強化する。Coreの`Project`境界は採用しない。Deployment Systemの現行仕様とソースコードは破棄し、名称、version、manifest、readiness、release summaryをDeployment Systemへ統合しない。

## Project Overview

Adlaire Ecosystemは、Adlaireグループの内部システム基盤としてスタートしたBaaS基盤プロジェクトである。Realtime Databaseを中核機能とし、SQLiteとEvent Logを軸に、外部依存を抑えた実運用向けのデータ管理基盤を構築する。

`v0.021`では、Realtime Database、Event Log、Authentication / AuthorizationをCoreの現行対象とする。Deployment Systemは白紙状態を維持し、Runtimeは廃止する。

## Approval Governance

本プロジェクトの最高準拠ルールは、全工程でユーザー承認を必須とすることである。この承認規則は、元々存在する最上位前提ルールである。

承認前の仕様確定、実装、リリース判定は禁止する。未承認の作業は実行してはならない。草案は仕様確定案として提示する。設計案と設計承認の工程は設けない。

必須順序は次の通り。

1. 仕様確定案
2. 仕様確定承認
3. バージョン計画承認
4. 実装承認
5. 実装
6. バグ修正
7. テスト
8. リリース判定承認
9. リリース判定

仕様確定承認、バージョン計画承認、実装承認は別工程であり、いずれかの承認を別工程の承認として扱ってはならない。実装は確定仕様に厳格に従い、仕様に明記されていない機能、挙動、境界、ファイル、依存関係は実装しない。実装中に仕様不足が判明した場合は実装を止め、承認順序へ戻す。

テストとバグ修正は承認工程に含めない。実装後とバグ修正後は、追加承認を待たずに公式テストを実行し、バグ修正ゼロまで修正する。

バージョン計画は`docs/version-plan.md`へ集約し、要点のみを簡潔に記載する。

テスト関係は`docs/testing.md`へ集約する。本ドキュメントにはテスト詳細を記載しない。

## External Dependency Principle

Adlaireに関わる全てのプロジェクトは、外部依存を認めないことを原則かつ最高準拠とする。

仕様で明示的に正選定された基盤を除き、外部サービス、外部同期、外部API、外部SDKへの依存を前提にしない。外部依存が必要に見える場合でも、まずAdlaire独自設計で代替する。

remote syncは採用しない。remote syncが担う差分追跡、状態再構築、競合検出、復旧はRealtime DatabaseのEvent Log、Cursor、Snapshot、Replay、Export/Restoreで扱う。

libSQLはSQLite互換の将来拡張として決定済みである。ただし、libSQLは外部依存を正当化する理由にはならず、実装対象にする場合は別途、仕様確定承認、バージョン計画承認、実装承認を必要とする。

## Mandatory Operation Requirements

`v0.019`の必須動作要件は次のみ。

- 必須動作要件はシステム動作要件の正本
- 必須動作要件に基づく範囲内はすべて必須要件
- 「必要だが必須ではない」という表現を禁止
- 仕様・実装・テスト・ドキュメントは承認済み文言に厳格準拠
- PHP: `8.3`推奨
- 必須拡張: `json`, `PDO`, `pdo_sqlite`
- CLI: Docker環境、デプロイメント限定
- 開発におけるCLIは必須
- SQLite使用
- 外部依存禁止

## Core Scope

`v0.019`で維持する中核機能は次のみ。

1. Realtime Database
2. Event Log
3. Authentication / Authorization

Deployment Systemは白紙化し、現行仕様とソースコードを破棄する。`Core/Deployment/`は将来再仕様化の境界フォルダとしてのみ残す。

Runtimeは廃止する。Runtime代替カテゴリは作らない。

Core機能の責務は次の通り。

| Core | 責務 | 禁止 |
|------|------|------|
| Realtime Database | record、collection、SQLite永続化、snapshot、cursor、query、operational evidenceを扱う | 外部同期、外部message broker、Deployment依存 |
| Event Log | Realtime Database、Authentication、Authorizationの変更履歴と証跡を追記型で保持する | 自動修復、自動圧縮、自動削除、remote sync化 |
| Authentication / Authorization | user、credential、session、role、permission、policy、access decisionを扱う | 外部OAuth、外部IAM、外部policy engine、plain password保存 |
| Deployment System | 現行仕様とソースコードを破棄済みとして境界のみ維持する | 代替実装、新仕様、manifest、release gate |

## Adlaire Method

Adlaire独自方式は、従来型のAPI契約、interface契約、SDK契約ではない。

Adlaire独自方式はRealtime DatabaseのEvent Log、Cursor、Snapshot、Replay、Export/Restoreを現行対象にする。Deployment Systemは基本方針からやり直すため、現行仕様を破棄済みである。

Adlaire独自方式で定義する項目は次の通り。

| 項目 | 意味 |
|------|------|
| Planned state | 対象機能が予定状態を表せること |
| Readiness | 対象機能が単独で準備状態を判定できること |
| Evidence | 判定根拠をfingerprint化できること |
| Rollback view | 変更前へ戻す見通しを出せること |
| Execution independence | 実行時APIやSDKを前提にしないこと |

`v0.019`では、Adlaire独自方式の実装対象をRealtime Database、Event Log、Authentication / Authorizationに拡張する。Event LogはRealtime Database、Authentication、Authorizationに共通するCore横断履歴基盤として扱う。

## v0.019 Version Decision

`v0.019`はRuntimeを廃止し、Realtime Databaseを3ファイルへ分割した構成で維持し、Authentication / AuthorizationをBaaS Core機能として追加するバージョンである。Event Logは認証、認可、Realtime Databaseの証跡基盤として扱う。

Deployment Systemの代替実装、新しいDeployment仕様、manifest、readiness、release、state、release gateは作らない。

実装対象:

- Event Envelope
- Domain Source
- Event Metadata
- Event Type Registry
- Event Chain Hash
- Event Validation
- Event Replay Scope
- Event Evidence
- Event Snapshot Link
- Event Replay Verification
- Event Cursor Contract
- Event Import Validation
- Event Export Packet
- Event Retention View
- Event Risk Report
- Event Operation Journal
- Event Health Summary
- Event Recovery Evidence
- Event Operational Guard
- Event Trust Score
- Event Restore Readiness
- Event Audit Packet
- Event Incident Packet
- Event Degradation Report
- Event Write Safety Gate
- Event Replay Window
- Event Cursor Drift Report
- Event Export Integrity
- Event Restore Impact
- Event Retention Decision View
- Event Operational SLO
- Event Handoff Summary
- Event Preflight Report
- Event Chain Snapshot
- Event Continuity Proof
- Event Payload Integrity Report
- Event Domain Isolation Report
- Event Recovery Route
- Event Manual Review Queue
- Event Operational Timeline
- Event Evidence Seal
- Event Trust Ledger
- User Registry
- Credential Registry
- Session Registry
- Login Attempt Record
- Password Policy
- Credential Rotation
- Login Risk Report
- Session Evidence
- Session Boundary
- User Lifecycle Evidence
- Role Registry
- Permission Registry
- Policy Registry
- Access Decision Evidence
- Authorization Audit
- Permission Boundary
- Policy Evaluation Trace
- Permission Matrix
- Deny Reason Registry
- Authorization Scope Boundary
- Policy Conflict Report
- Least Privilege Report
- Auth Operational Dashboard
- Auth Control Tower
- Auth Incident Timeline
- Auth Incident Severity
- Auth Incident Evidence Digest
- Auth Incident Containment
- Credential Exposure Report
- Credential Trust Score
- Session Trust Score
- Session Anomaly Report
- Session Recovery Packet
- Policy Drift Report
- Policy Blast Radius
- Permission Saturation Report
- Access Denial Analysis
- Authorization Recovery Packet
- Auth Audit Packet
- Auth Evidence Seal
- Auth Trust Ledger
- Auth Recovery Evidence
- Auth Manual Review Queue
- Auth Production Readiness Gate
- Auth Write Safety Gate
- Auth Emergency Freeze View
- Auth Degraded Mode View

## Version History

過去バージョンの承認済み計画、対象範囲、バグ修正要約は`docs/version-plan.md`へ集約する。本ドキュメントでは現行`v0.021`仕様を正本として詳述する。

## Directory Policy

維持できるディレクトリは次のみ。

```text
Core/
Applications/
Docker/
docs/
tests/
```

現行構成は上記ディレクトリに集約する。

## Core Files

`Core/`直下は共通基盤機能とエントリポイントの2機能で扱う。エントリポイントは単一ファイル原則で扱う。Event Logも単一ファイル原則で扱う。

Core直下の内部実装フォルダ:

```text
Core/Database/
Core/Auth/
Core/Deployment/
```

| File | Role |
|------|------|
| `Core/Database.php` | realtime database BaaS feature, data model, event log, snapshot, readiness |
| `Core/EventLog.php` | Event Log common foundation for Realtime Database, Authentication, Authorization |
| `Core/Auth.php` | Authentication / Authorization BaaS Core feature |

内部実装ファイル:

| File | Role |
|------|------|
| `Core/Database/DatabaseCore.php` | realtime database public facade, planned state, readiness |
| `Core/Database/DatabaseStorage.php` | realtime database SQLite persistence and storage internals |
| `Core/Database/DatabaseOperations.php` | realtime database record, query, snapshot, event, operational internals |
| `Core/Auth/AuthCore.php` | authentication and authorization public facade, planned state, readiness |
| `Core/Auth/AuthStorage.php` | auth evidence storage internals |
| `Core/Auth/AuthOperations.php` | auth operation internals |

`Core/Database/`内のPHPファイルは`DatabaseCore.php`、`DatabaseStorage.php`、`DatabaseOperations.php`の3ファイルのみとする。

`Core/Auth/`内のPHPファイルは`AuthCore.php`、`AuthStorage.php`、`AuthOperations.php`の3ファイルのみとする。

`Core/EventLog.php`はエントリポイントではなく、Core横断の共通基盤機能である。Event Log用フォルダは作成しない。

`Core/Deployment/`にはPHPファイルを置かず、`.gitkeep`のみを残す。内部フォルダにはエントリポイントを置かない。

必要な機能は本仕様からゼロベースで実装する。

Core配下の機能境界はDatabase、Auth、Deploymentの3内部フォルダを維持する。Deploymentは境界のみで、内部実装は置かない。Runtime境界は廃止する。Coreの`Project`境界は不要とし、作成しない。

Project統合方針:

```text
Project boundary: none
Name/version/manifest: not_deployment_system
Readiness/release summary: deployment_system_discarded
Runtime: removed
Runtime replacement category: prohibited
```

## Applications

`Applications/`はApplication Modulesの境界として維持する。

- Application ModulesはCore外のアプリケーション層として扱う。
- 初期状態では`Applications/.gitkeep`のみを置く。

## Docker

`Docker/`はDocker関連ファイルの境界として維持する。

- Dockerfile、compose、Docker用スクリプト、Docker用設定は`Docker/`へ格納する。
- Docker関連ファイルをCore、Applications、docs、tests直下へ分散させない。
- 初期状態では`Docker/.gitkeep`のみを置く。

## Deployment System

Deployment Systemは基本方針からやり直す。

現時点のDeployment Systemは白紙化し、現行仕様とソースコードを破棄済みである。過去のDeployment案、manifest、readiness、release、state、release gate、preview、evidence、rollback view、release decisionを正本仕様として採用しない。

`v0.019`では、Deployment Systemについて次のみを定義する。

- Deployment Systemの現行仕様は破棄済みである
- Deployment Systemのソースコードは破棄済みである
- `Core/Deployment/`は境界フォルダのみである
- Deployment Systemの代替実装は行わない
- 新しいDeployment仕様は作らない
- Deployment SystemはRealtime Databaseの仕様整理を妨げない
- Deployment Systemの新方針は別途仕様確定案から開始する

Deployment System boundary:

```text
Core/Deployment/.gitkeep
php_files: none
source_code: discarded
```

## Realtime Database

Realtime DatabaseはSQLiteを正選定したBaaS Core Featureである。

### Database Selection

`v0.019`のRealtime DatabaseはSQLiteを正選定する。libSQLはSQLite互換の将来拡張として決定済みであるが、`v0.019`では実装しない。

選定結果:

| 項目 | 内容 |
|------|------|
| Selected database | SQLite |
| Role | local embedded database |
| Reason | BaaS Core Featureの初期実装を小さく保ち、Event Log型のデータモデルと相性がよい |
| Persistence mode | SQLite persistent |
| Fallback storage | in-memory |
| Persistence in v0.019 | 実装する |
| Compatibility target | libSQL |
| libSQL role | SQLite互換の将来拡張 |
| libSQL connection | v0.019では実装しない |

`v0.019`ではSQLiteファイル永続化を維持する。libSQL接続、remote syncは実装しない。in-memoryはテストと互換用途のfallbackとして残す。

Realtime Database planned state:

```text
feature: realtime_database
version: v0.019
state: planned
kind: baas_core_feature
deployable_unit: realtime_database
deployment_axis: undefined
storage: sqlite_libsql
selected_database: sqlite
compatibility_target: libsql
storage_policy: sqlite_primary_libsql_compatible
mode: event_log
collections: system, application
channels: system, application
event_stream: internal
cursor: event_id
collection_stream: true
record_lookup: true
record_listing: true
schema: true
record_metadata: true
query: true
index_plan: true
migration_plan: true
event_payload_summary: true
subscription_model: true
transaction_boundary: true
snapshot_export: true
database_export: true
snapshot_restore: true
conflict_detection: true
event_replay: true
read_model_rebuild: true
access_rules: undefined
realtime_adapter: none
stream_mode: pull_cursor
snapshot: collection_state
rollback_required: true
sqlite_persistence: true
backup_restore: true
restore_validation: true
operational_health: true
integrity_audit: true
diagnostics: true
write_policy: true
write_policy_enforcement: true
query_explain: true
import_validation: true
operational_guard: true
maintenance_mode: true
startup_self_check: true
backup_verification: true
restore_dry_run: true
recovery_check: true
event_log_consistency_check: true
cursor_safety: true
read_model_drift_detection: true
operational_metrics: true
operational_report: true
collection_lifecycle: true
schema_versioning: true
bulk_import_dry_run: true
bulk_write: true
record_restore: true
snapshot_compare: true
event_replay_range: true
query_cursor_pagination: true
collection_export_filter: true
data_redaction_export: true
change_feed_filter: true
record_version_history: true
record_diff: true
snapshot_retention_plan: true
backup_manifest: true
restore_preview: true
collection_lock: true
write_quota_guard: true
event_checkpoint: true
operational_incident_report: true
query_cursor_enhancement: true
import_validation_enhancement: true
audit_integrity_enhancement: true
operational_report_enhancement: true
data_redaction_export_enhancement: true
schema_versioning_enhancement: true
health_baseline: true
drift_baseline_compare: true
write_safety_preflight: true
restore_safety_gate: true
backup_consistency_report: true
event_gap_report: true
corruption_suspect_report: true
operational_risk_score: true
recovery_decision_report: true
safe_mode: true
readonly_state_report: true
incident_timeline: true
write_intent_log: true
write_commit_verification: true
recovery_simulation: true
restore_impact_report: true
event_chain_integrity: true
snapshot_integrity_seal: true
operational_runbook_report: true
degraded_mode: true
critical_operation_guard: true
operational_evidence_bundle: true
pre_write_risk_evaluation: true
critical_write_two_step_guard: true
backup_restore_compatibility_check: true
snapshot_seal_verification: true
operational_degradation_reason: true
incident_severity_classification: true
recovery_readiness_report: true
operation_freeze_policy: true
data_durability_report: true
release_safety_evidence: true
operational_slo_report: true
write_failure_classification: true
backup_freshness_report: true
restore_candidate_ranking: true
read_model_confidence_report: true
operational_window_policy: true
recovery_drill_report: true
incident_evidence_digest: true
data_lifecycle_guard: true
operational_handoff_report: true
```

Realtime Database readinessは、BaaS Core Featureとしてplanned state、collection model、event log、snapshot、cursor、SQLite persistence、in-memory fallbackを判定できる状態を意味する。Deployment Systemの新方針が確定するまでは、Realtime DatabaseはDeployment Systemに依存しない。

### Realtime Database BaaS Contract

`v0.019`のRealtime Databaseは外部公開APIではないが、BaaS機能として次のCore契約を持つ。

| 契約 | 内容 |
|------|------|
| Collection registry | collection名、channel、storageを管理する |
| Collection schema | field定義、index予定、delete modeを管理する |
| Record store | collection内の現在recordを管理する |
| Record metadata | logical sequenceによるcreated、updated、deleted、revisionを管理する |
| Patch | set、unset、increment、appendでrecord差分更新を行う |
| Query | collection内recordを条件、並び順、件数、選択fieldで取得する |
| Index planned state | SQLite移行を前提にindex予定を保持する |
| Migration planned state | SQLite永続化へ移行するtable、index、schema version予定を保持する |
| Event log | create、update、deleteを追記型eventとして保持する |
| Event payload summary | before hash、after hash、changed fields、payload dataをeventに保持する |
| Event stream | cursor以降のeventを返す |
| Collection stream | collection単位でcursor以降のeventを返す |
| Subscription | collection streamを購読モデルとして表す |
| Transaction boundary | 複数操作を1つの論理境界で実行する |
| Snapshot | collectionの現在状態とfingerprintを返す |
| Snapshot export | snapshotを移行可能なpayloadとして返す |
| Database export | collection定義、snapshot、event logをまとめて返す |
| Snapshot restore | export payloadからsnapshotを復元する |
| SQLite persistence | collections、records、events、schema_versions、database_metaをSQLiteに保持する |
| Operational health | storage status、record/event count、latest cursorを返す |
| Atomic rollback | transaction失敗時に途中のrecord、event、sequence、SQLite書き込みを残さない |
| Stable export fingerprint | database exportのfingerprintから環境依存値を除外する |
| Restore validation | database restore前にpayload構造、SQLite選定、fingerprintを検証する |
| Integrity audit | record、event、schema、payload hashの整合性を検査する |
| Diagnostics | storage、schema、query、event、backup、auditをまとめて診断する |
| Write policy | record size、collection name、schema type、patch/transaction上限を示す |
| Write policy enforcement | record size、patch、transaction上限を実書き込み前に検査する |
| Query explain | queryのindex候補、full scan警告、最適化hintを返す |
| Import validation | 外部record投入前にschema、重複id、型をdry-run検証する |
| Operational guard | maintenance mode、write policy、audit状態をまとめて返す |
| Startup self check | storage、collection、event log、auditの起動時確認を返す |
| Backup verification | database export payloadを検証する |
| Restore dry run | database restore前に破壊なしで復元可能性を検証する |
| Recovery check | current exportとbackup payloadの復旧可能性を返す |
| Event log consistency check | event id、sequence、cursorの整合性を検査する |
| Cursor safety | cursorが既知eventかを検査する |
| Read model drift detection | snapshotとevent replay結果の実データ差分を検出する |
| Operational metrics | collection、record、event、cursor、storageの運用値を返す |
| Operational report | guard、self check、metrics、event log、plan only項目をまとめて返す |
| Conflict detection | expected versionと現在versionの不一致を検出する |
| Event replay | event payloadからcollection状態を再生する |
| Read model rebuild | event logからsnapshot viewを再構築する |
| Collection lifecycle | collectionのactive状態、record、event、cursorを返す |
| Schema versioning | schema fingerprintとschema versionを返す |
| Bulk import dry run | bulk import前に書き込みなしで検証する |
| Bulk write | 複数書き込みをtransaction境界で実行する |
| Record restore | 指定recordを復元しrestore eventを記録する |
| Snapshot compare | 2つのsnapshot差分を比較する |
| Event replay range | 指定event範囲でread modelを再生する |
| Query cursor pagination | record queryをcursor方式でページングする |
| Collection export filter | 条件付きcollection exportを返す |
| Data redaction export | 指定fieldをredactしたexportを返す |
| Change feed filter | event logをcollection、type、record、範囲で絞り込む |
| Record version history | record単位の変更履歴をevent logから返す |
| Record diff | version間のfield差分を返す |
| Snapshot retention plan | snapshot保持方針を返す |
| Backup manifest | database exportの概要と検証結果を返す |
| Restore preview | restore前に差分概要をdry-runで返す |
| Collection lock | collection単位で書き込み停止状態を返す |
| Write quota guard | 書き込み量と操作数を事前検査する |
| Event checkpoint | cursor時点のevent集計とfingerprintを返す |
| Operational incident report | 障害調査向けにguard、audit、metrics、checkpointをまとめる |
| Health baseline | record、event、cursor、fingerprintの基準値を返す |
| Drift baseline compare | baselineと現在状態の差分を返す |
| Write safety preflight | 書き込み前にpolicy、schema、永続化状態を検査する |
| Restore safety gate | restore前にpayload、drift、riskを検査する |
| Backup consistency report | backup payloadの整合性、manifest、gapを返す |
| Event gap report | event sequenceとcursorの欠落を検査する |
| Corruption suspect report | audit、event gap、storage結果から破損疑いを返す |
| Operational risk score | 運用状態のrisk scoreとlevelを返す |
| Recovery decision report | restore、observe、manual reviewの判断材料を返す |
| Safe mode | 実行時に全書き込みを停止する |
| Readonly state report | safe mode、maintenance、lockを含む書き込み可否を返す |
| Incident timeline | 運用調査用の時系列summaryを返す |
| Write intent log | 書き込み前に操作意図、対象、cursor、fingerprintを記録する |
| Write commit verification | 書き込み後にaudit、event chain、cursor、intent commitを検査する |
| Recovery simulation | restore前に復旧後の想定collection、record、event、fingerprintをdry-runで返す |
| Restore impact report | restoreによる追加、更新、削除数をcollection別と合計で返す |
| Event chain integrity | event logのsequenceと連鎖fingerprintを検査する |
| Snapshot integrity seal | snapshot、cursor、schema fingerprintをまとめたsealを返す |
| Operational runbook report | riskとcorruption状態から次の運用手順を返す |
| Degraded mode | safe modeとは別にcritical operationだけを停止する |
| Critical operation guard | restore、bulk write、delete、record restoreなどの実行可否を返す |
| Operational evidence bundle | baseline、risk、event、backup、impact、timeline、runbookを集約する |
| Pre-write risk evaluation | 書き込み前にrisk、mode、lock、event chain、schema状態を評価する |
| Critical write two-step guard | critical operationにintent確認とguard判定を組み合わせる |
| Backup restore compatibility check | backup payloadのdatabase、collection、cursor、seal互換性を返す |
| Snapshot seal verification | snapshot sealの改変、schema不一致、cursor不一致を検査する |
| Operational degradation reason | safe/degraded modeやrisk状態の理由を標準化して返す |
| Incident severity classification | incident状態をlow、medium、high、criticalで分類する |
| Recovery readiness report | restore前の復旧準備状態をready、blocked、manual_review_requiredで返す |
| Operation freeze policy | read、normal write、critical write、restoreの停止方針を返す |
| Data durability report | SQLite永続化、event、seal、backup fingerprintを返す |
| Release safety evidence | リリース前安全証跡をRealtime Database単体で集約する |
| Operational SLO report | readiness、event chain、write safety、durability、backup consistencyをSLOとして返す |
| Write failure classification | 書き込み失敗理由をpolicy、schema、lock、safe mode、critical guardへ分類する |
| Backup freshness report | backup cursor、event count、fingerprintを現在状態と比較する |
| Restore candidate ranking | backup候補をfreshness、compatibility、impactで順位付けする |
| Read model confidence report | snapshot、replay、drift、sealからread model信頼度を返す |
| Operational window policy | 通常書き込み、critical operation、restore、backup verificationの運用windowを返す |
| Recovery drill report | restore safety、simulation、impact、compatibility、readinessを復旧訓練結果として返す |
| Incident evidence digest | severity、risk、cursor、event chain tip、durability、freeze policyを軽量要約する |
| Data lifecycle guard | delete、restore、bulk writeなどのデータライフサイクル操作を判定する |
| Operational handoff report | 運用引き継ぎ用にstatus、severity、freeze、runbook、recovery、next actionを返す |
| Cursor | 最後に観測したevent idを表す |
| Access rules | Realtime Database単体では`undefined`を返し、認証・認可はAuth Coreで扱う |
| Realtime adapter boundary | `none`を返し、pull cursor方式を明示する |

Realtime Databaseは`v0.019`ではpush型接続を持たない。realtime性はevent logとcursorによる追跡可能性として扱う。

### Realtime Database Feature Set

`v0.019`で実装するBaaSデータベース機能は次の通り。

| 機能 | 方針 |
|------|------|
| Collection Schema | collectionごとにfields、indexes、delete modeを保持する |
| Schema Validation | required、default、nullable、enum、min、maxを扱う |
| Record Metadata | 実時刻ではなくlogical sequenceでcreated、updated、deleted、revisionを持つ |
| Record Patch | set、unset、increment、appendを扱う |
| Query | equals、not equals、range、contains、offset、select、count onlyを扱う |
| Index Planned State | SQLite移行を前提にprimary、collection、event、custom index予定を返す |
| Subscription Model | push接続ではなくcollection streamの別名として扱う |
| Transaction Boundary | 複数操作を順に実行し、eventsとcursorを返す |
| Transaction Rollback | 途中失敗時に実行前状態へ戻す |
| Snapshot Export | snapshotとcollection定義をexport payloadとして返す |
| Database Export | 全collection定義、snapshot、event logをexport payloadとして返す |
| Snapshot Restore | export payloadからcollectionの現在状態を復元する |
| Conflict Detection | expected version不一致をconflictとして返す |
| Event Replay | event payloadからrecords viewを再生する |
| Read Model Rebuild | event logからsnapshot viewを再構築する |
| Collection Stats | record count、event count、latest cursor、schema/snapshot fingerprintを返す |
| Migration Plan | SQLite table、index、schema version、persistence status予定を返す |
| Access Rule Placeholder | access rulesは`undefined`として返す |
| Realtime Adapter Boundary | adapterは`none`、streamは`pull_cursor`として返す |
| SQLite Persistence | SQLiteファイルへcollection、record、eventを永続化する |
| Default Collection Persistence | `system`、`application`もSQLite有効化時に永続化対象へ含める |
| Storage Status | SQLite path、file sizeを返す |
| Operational Health | 永続化状態、migration状態、record/event countを返す |
| Restore Validation | 不正なdatabase export payloadでは既存状態を破壊しない |
| Data Integrity Audit | record/event/schema/hash整合性を検査する |
| Database Diagnostics | storage/schema/query/event/backup/auditをまとめて返す |
| Write Policy | 書き込み制約と上限を返す |
| Query Explain | index候補、full scan警告、hintを返す |
| Import Validation | 外部データ投入前のdry-run検証を返す |
| Health Baseline | 運用基準となるrecord、event、cursor、fingerprintを返す |
| Drift Baseline Compare | baselineと現在状態の差分を返す |
| Write Safety Preflight | 書き込み前に安全性をdry-run検査する |
| Restore Safety Gate | restore前の許可判定を返す |
| Backup Consistency Report | backup payloadの整合性を返す |
| Event Gap Report | event sequence欠落を検査する |
| Corruption Suspect Report | 破損疑いの有無を返す |
| Operational Risk Score | 運用risk levelを返す |
| Recovery Decision Report | 復旧判断を返す |
| Safe Mode | 実行時書き込み停止を返す |
| Readonly State Report | 読み取り専用状態と書き込み可否を返す |
| Incident Timeline | 障害調査用summaryを返す |
| Write Intent Log | 書き込み前のintentを返す |
| Write Commit Verification | 書き込み後の整合確認を返す |
| Recovery Simulation | restore前の復旧想定を返す |
| Restore Impact Report | restore影響差分を返す |
| Event Chain Integrity | event chain検査を返す |
| Snapshot Integrity Seal | snapshot sealを返す |
| Operational Runbook Report | 運用手順summaryを返す |
| Degraded Mode | critical operation停止状態を返す |
| Critical Operation Guard | critical operationの実行可否を返す |
| Operational Evidence Bundle | 運用証跡bundleを返す |
| Pre-Write Risk Evaluation | 書き込み前の運用risk評価を返す |
| Critical Write Two-Step Guard | critical writeの二段階guardを返す |
| Backup Restore Compatibility Check | backup/restore互換性を返す |
| Snapshot Seal Verification | snapshot seal検証結果を返す |
| Operational Degradation Reason | 劣化運用理由を返す |
| Incident Severity Classification | incident severityを返す |
| Recovery Readiness Report | 復旧準備状態を返す |
| Operation Freeze Policy | 操作停止方針を返す |
| Data Durability Report | データ永続性状態を返す |
| Release Safety Evidence | リリース前安全証跡を返す |
| Operational SLO Report | 運用SLO状態を返す |
| Write Failure Classification | 書き込み失敗分類を返す |
| Backup Freshness Report | backup鮮度を返す |
| Restore Candidate Ranking | restore候補順位を返す |
| Read Model Confidence Report | read model信頼度を返す |
| Operational Window Policy | 運用window方針を返す |
| Recovery Drill Report | 復旧訓練結果を返す |
| Operational Baseline Snapshot | 運用基準snapshotを返す |
| Write Anomaly Detector | 書き込み異常傾向を返す |
| Recovery Priority Report | 復旧優先度を返す |
| Operational Risk Timeline | 運用risk timelineを返す |
| Data Consistency Score | 整合性scoreを返す |
| Backup Candidate Validation Matrix | backup候補比較表を返す |
| Write Safety Threshold Policy | 書き込み安全閾値を返す |
| Production Readiness Gate | 本番投入可否を返す |
| Operational Drift Budget | 運用drift許容量を返す |
| Recovery Path Comparison | 復旧経路比較を返す |
| Data Integrity Attestation | 整合性証跡を返す |
| Incident Containment Policy | 障害封じ込め方針を返す |
| Backup Trust Score | backup信頼度を返す |
| Data Recovery Confidence | 復旧成功見込みを返す |
| Production Safety Board | 本番安全状態を集約する |
| Operational Control Tower | 運用総合状態を返す |
| Production Operations Packet | 実運用packetを返す |
| Incident Evidence Digest | incident証跡要約を返す |
| Data Lifecycle Guard | data lifecycle操作guardを返す |
| Operational Handoff Report | 運用引き継ぎsummaryを返す |

Record metadata:

```text
created_sequence: integer
updated_sequence: integer
deleted_sequence: integer | null
revision: integer
```

Query:

```text
collection: string
where: field operator value
order_by: field
direction: asc | desc
offset: integer
limit: integer | null
select: list | null
count_only: boolean
```

Index planned state:

```text
primary: id
collection: collection
events: sequence, collection, record_id
custom: collection indexes
```

Migration planned state:

```text
schema_version: integer
persistence_status: planned
tables: collections, records, events, schema_versions, database_meta
indexes: primary, collection, events, custom
selected_database: sqlite
compatibility_target: libsql
persistence_execution: sqlite_persistent | in_memory
dry_run: true
rollback_plan: true
history: list
```

## Realtime Database Data Model

`v0.019`のRealtime Databaseは、次の実データ構造を扱う。

| Model | 意味 |
|-------|------|
| Collection | recordのまとまり |
| Record | collection内の1件のデータ |
| Event | record変更の履歴 |
| Snapshot | collectionの現在状態 |
| Cursor | event logの追跡位置 |

### Event Log Overview

Event Logは、Realtime Databaseの変更履歴を追記型で保持する内部履歴基盤です。recordの作成、更新、削除、復元などの変更をeventとして記録し、sequenceとcursorによって変更順序を管理します。

Adlaire Ecosystemでは、Event LogをRealtime Databaseの中核構造として扱います。Snapshot、Cursor、Replay、Export/Restoreと組み合わせることで、現在状態の再構築、変更履歴の追跡、整合性確認、復旧判断を支える基盤とします。

Event Logは外部同期や外部message brokerの代替ではなく、Realtime Database内部で状態変化を信頼できる履歴として保持するための仕組みです。自動修復、自動圧縮、自動削除は行わず、運用判断に必要な根拠を提供します。

`v0.019`では、Event Logを`Core/EventLog.php`の単一ファイルとして独立させる。Event LogはRealtime Database、Authentication、Authorizationに共通するCore横断履歴基盤である。Realtime Databaseは`Core/EventLog.php`を利用し、既存の`AdlaireDatabase` APIを維持する。

`v0.019`では、Event Log eventをEnvelopeとして扱う。Envelopeは`id`, `sequence`, `source`, `domain`, `collection`, `record_id`, `type`, `version`, `payload`, `metadata`, `previous_hash`, `event_hash`を持つ。`domain`は`realtime_database`, `authentication`, `authorization`を扱う。

Event Log domainの責務は次の通り。

| Domain | 主なevent |
|--------|-----------|
| `realtime_database` | `create`, `update`, `delete`, `restore` |
| `authentication` | `user_create`, `user_update`, `credential_register`, `credential_rotate`, `credential_revoke`, `session_issue`, `session_validate`, `session_revoke`, `login_success`, `login_failure`, `password_policy_check` |
| `authorization` | `role_create`, `permission_create`, `policy_assign`, `policy_revoke`, `policy_evaluate`, `access_allow`, `access_deny` |

Event Logはevent type registry、event validation、event chain hash、cursor contract、replay scope、event evidence、snapshot link、replay verification、import validation、export packet、retention view、risk report、operation journalを提供する。

`v0.019`では、Event Logはhealth summary、recovery evidence、operational guard、trust score、restore readiness、audit packet、incident packet、degradation report、write safety gate、replay window、cursor drift report、export integrity、restore impact、retention decision view、operational SLO、handoff summary、preflight report、chain snapshot、continuity proof、payload integrity report、domain isolation report、recovery route、manual review queue、operational timeline、evidence seal、trust ledgerを提供する。

Event Logは自動修復、自動圧縮、自動削除を行わない。外部同期、外部message broker、remote syncとして扱わない。

Collection:

```text
name: string
channel: system | application
storage: sqlite | in_memory
schema: map
indexes: list
delete_mode: hard | soft
```

Collection nameは安定識別子とする。

```text
starts with lowercase alphabet
contains lowercase alphabet, number, underscore
```

Record:

```text
id: string
collection: string
channel: system | application
data: map
meta: map
version: integer
```

Event:

```text
id: string
sequence: integer
collection: string
channel: system | application
record_id: string
type: create | update | delete
version: integer
payload_hash: sha256
before_hash: sha256 | null
after_hash: sha256 | null
changed_fields: list
payload: map
```

delete eventのversionは削除前record versionの次の値とする。削除済みrecordはsnapshotから除外し、event logにのみ履歴として残す。

Snapshot:

```text
collection: string
records: list
version: integer
cursor: event id | null
fingerprint: sha256
```

Cursor:

```text
after: event id | null
latest: event id | null
```

`v0.019`の実データはSQLiteファイルへ永続化できる。in-memoryはfallbackとして維持する。libSQLはSQLite互換の将来拡張として決定済みであるが、`v0.019`では接続しない。

SQLite tables:

```text
collections
records
events
schema_versions
database_meta
```

SQLite persistence:

```text
foreign_keys: ON
```

SQLite永続化の運用条件:

```text
default collections are persisted
failed transaction leaves no partial records
failed transaction leaves no partial events
export fingerprint excludes path and file_size
restore validates selected_database and fingerprint before mutation
json encode failure is an explicit execution error
```

## Realtime Database Operations

`Core/Database.php`は次のCore内部メソッドを持つ。

```text
defineCollection(collection, channel, schema, indexes, deleteMode)
enableSQLite(path)
disableSQLite()
collections()
create(collection, data)
get(collection, id)
records(collection)
query(collection, options)
patch(collection, id, operations, expectedVersion)
update(collection, id, data)
delete(collection, id)
stats(collection)
snapshot(collection)
exportSnapshot(collection)
exportDatabase()
validateDatabaseExport(payload)
restoreDatabase(payload)
restoreSnapshot(collection, payload)
events(after)
replay(collection, events)
rebuildSnapshot(collection)
stream(collection, after)
subscribe(collection, after)
transaction(operations)
cursor()
indexPlan()
migrationPlan()
accessRules()
realtimeAdapter()
storageStatus()
operationalHealth()
auditIntegrity()
diagnostics()
writePolicy()
queryExplain(collection, options)
importValidation(collection, records)
healthBaseline()
driftBaselineCompare(baseline)
writeSafetyPreflight(collection, data, operations)
restoreSafetyGate(payload)
backupConsistencyReport(payload)
eventGapReport()
corruptionSuspectReport()
operationalRiskScore()
recoveryDecisionReport(payload)
setSafeMode(enabled)
readonlyRuntimeReport()
incidentTimeline()
writeIntentLog()
writeCommitVerification(intent)
recoverySimulation(payload)
restoreImpactReport(payload)
eventChainIntegrity()
snapshotIntegritySeal(collection)
operationalRunbookReport()
setDegradedMode(enabled)
degradedMode()
criticalOperationGuard(operation, collection)
operationalEvidenceBundle(payload)
preWriteRiskEvaluation(collection, data, operations)
criticalWriteTwoStepGuard(operation, collection, intent)
backupRestoreCompatibilityCheck(payload)
snapshotSealVerification(collection, seal)
operationalDegradationReason(payload)
incidentSeverityClassification()
recoveryReadinessReport(payload)
operationFreezePolicy()
dataDurabilityReport(payload)
releaseSafetyEvidence(payload)
operationalSloReport(payload)
writeFailureClassification(failure)
backupFreshnessReport(payload)
restoreCandidateRanking(candidates)
readModelConfidenceReport(collection)
operationalWindowPolicy(payload)
recoveryDrillReport(payload)
incidentEvidenceDigest(payload)
dataLifecycleGuard(operation, collection)
operationalHandoffReport(payload)
operationalBaselineSnapshot(payload)
writeAnomalyDetector()
recoveryPriorityReport(payload)
operationalRiskTimeline(payload)
dataConsistencyScore(payload)
backupCandidateValidationMatrix(candidates)
writeSafetyThresholdPolicy()
incidentReplaySummary()
productionReadinessGate(payload)
operatorActionChecklist(payload)
operationalDriftBudget(payload)
writeBlastRadiusReport(collection, recordId)
recoveryPathComparison(payload)
dataIntegrityAttestation(payload)
incidentContainmentPolicy(payload)
operationalRegressionGuard(baseline)
backupRotationPolicyReport(candidates)
stateTransitionAudit()
criticalCollectionProfile(collection)
productionIncidentPacket(payload)
operationalHealthTrend(payload)
writeQuarantineRecommendation()
readModelRebuildSafetyReport(collection)
backupTrustScore(payload)
eventGapDetection()
operationalSaturationReport()
safeMaintenanceWindowReport(payload)
dataRecoveryConfidence(payload)
incidentRootCauseHints()
productionOperationSummary(payload)
operationReadinessLedger(payload)
writeAdmissionControlReport(payload)
criticalRecordWatchlist()
schemaStabilityReport()
eventReplayFeasibilityReport()
restoreDryRunEvidence(payload)
sqliteOperationalLimitsReport()
incidentCommunicationSummary(payload)
releaseRegressionEvidence(baseline)
productionSafetyBoard(payload)
operationalControlTower(payload)
writePressureReport()
failureRecurrenceDetector()
restoreDecisionChecklist(payload)
eventChainTrustReport()
readConsistencyVerification()
operationalEvidenceTimeline(payload)
degradedModeExitCriteria(payload)
backupExposureReport(payload)
productionOperationsPacket(payload)
```

これらはPublic APIではなく、Core内部の実データ操作である。`v0.019`ではテストとCore判定のためPHP上はpublic staticとして実装する。

## Authentication / Authorization

Authentication / AuthorizationはBaaS Core機能として扱う。`v0.021`では実運用、実運用耐性、運用判断、監査証跡を強化する。

Core構成:

```text
Core/Auth.php
Core/Auth/AuthCore.php
Core/Auth/AuthStorage.php
Core/Auth/AuthOperations.php
```

Auth CoreはUser Registry、Credential Registry、Session Registry、Role Registry、Permission Registry、Policy Registryを扱う。認証はplain passwordを保持せず、credential hash、session evidence、login attempt recordをEvent Log証跡として保持する。

認可はundefined policyをdenyとして扱う。Access Decision Evidence、Authorization Audit、Permission Matrix、Deny Reason Registry、Authorization Scope Boundary、Policy Evaluation Traceを提供する。

運用・耐性機能として、Auth Operational Dashboard、Auth Control Tower、Auth Incident Timeline、Credential Trust Score、Session Trust Score、Policy Drift Report、Permission Saturation Report、Auth Audit Packet、Auth Evidence Seal、Auth Trust Ledger、Auth Production Readiness Gate、Auth Write Safety Gate、Auth Emergency Freeze View、Auth Degraded Mode Viewを提供する。

`v0.021`では、Auth Change Impact Report、Policy Simulation、Session Revocation Impact、Credential Revocation Impact、Permission Coverage Report、Unused Permission Report、Dormant User Report、Stale Session Report、Failed Login Trend、Access Pattern Baseline、Access Pattern Drift Report、Role Saturation Report、Policy Expiry Plan、Emergency Access Review、Auth Evidence Export、Auth Evidence Import Validation、Auth State Compare、Authorization Regression Guard、Auth Operations Ledger、Auth Control Summaryを提供する。

Auth Coreは外部OAuth、外部IAM、外部policy engine、外部mail/SMS、remote sync、message brokerに依存しない。自動修復、自動復旧、自動削除、自動rotation、自動権限昇格は行わない。

Auth Coreの境界は次の通り。

| 項目 | 方針 |
|------|------|
| User | `active`, `inactive`, `revoked`の状態を持つ |
| Credential | secret hashのみを保持し、応答にsecret hashを返さない |
| Session | user状態とsession状態から有効性を判定する |
| Role | policy評価時にactive状態を確認する |
| Permission | resourceとactionの組み合わせを保持する |
| Policy | subject、role、permission、effect、statusを保持する |
| Access decision | undefined policy、revoked session、inactive role、inactive permissionをdenyにする |
| Evidence | Authentication / Authorization eventをEvent Log envelopeとして保持する |

Auth Coreの運用判断は、証跡、integrity、manual review queue、trust score、readiness gate、impact、simulation、baseline、drift、ledgerを返す。自動で権限を変更しない。自動でcredentialをrotateしない。自動でsessionを復旧しない。

## Documents

ドキュメントの役割は次の通り。

| File | Role | 禁止 |
|------|------|------|
| `docs/AGENTS.md` | 作業エージェントの最高準拠、作業ルール、承認プロセス、構成制約 | 仕様詳細の重複 |
| `docs/ADLAIRE-ECOSYSTEM.md` | 仕様の最高準拠、判断根拠、リリース条件 | 作業手順の重複 |
| `docs/testing.md` | テスト方針、公式テスト入口、テスト範囲の集約 | バージョン計画の記載 |
| `docs/version-plan.md` | バージョン計画承認とバグ修正要約の正本 | テスト関係の記載 |
| `docs/README.md` | 外部向けの簡潔なプロジェクト説明 | 内部入口、詳細仕様、作業ルール |

すべてのドキュメントは`docs/`へ集約する。テスト関連の補助ドキュメントは`docs/testing.md`へ集約する。

## Tests

現行の公式テストはPHPソースコードベースで行う。

公式テスト入口は次のみ。

```sh
php tests/debug.php
```

現行方針:

```text
test_mode: php_source_code_based
test_entrypoint: php tests/debug.php
docker_test_mode: future_production_like_environment
```

Dockerを使う本番相当テストは将来計画とする。将来的に`Docker/`配下へDockerfile、compose、Docker用設定を集約し、本番相当環境を作成してテスト、デバッグ、本番さながらの検証を行う。

テスト関連ドキュメント:

```text
docs/testing.md
```

バージョン計画ドキュメント:

```text
docs/version-plan.md
```

テストは次を検証する。

- 許可ディレクトリのみ存在する
- Core直下が共通基盤機能とエントリポイントの2機能である
- `Core/EventLog.php`がEvent Log単一ファイルである
- Event Log用フォルダが存在しない
- `Core/Deployment/`が境界フォルダのみである
- Docker関連境界として`Docker/`が存在する
- 現行テストがPHPソースコードベースである
- Docker本番相当テストが将来計画として整理されている
- Deployment Systemの現行仕様とソースコードが破棄されている
- Realtime Database readinessが成功する
- Realtime DatabaseがBaaS Core Featureとして判定可能である
- Realtime Databaseのcollection、event log、snapshot、cursorが機能する
- Realtime Databaseのrecord取得、record一覧、collection streamが機能する
- Realtime Databaseのschema、metadata、query、subscription、transaction、snapshot export、index planned stateが機能する
- Realtime Databaseのpatch、stats、migration plan、database export、snapshot restore、conflict detection、event replay、read model rebuild、access rule placeholder、realtime adapter boundaryが機能する
- Realtime DatabaseのSQLite永続化、default collection永続化、transaction rollback、restore validation、operational healthが機能する
- Realtime Databaseのintegrity audit、diagnostics、write policy、query explain、import validationが機能する
- Realtime Databaseの実運用耐性機能が機能する
- Authentication / Authorization readinessが成功する
- Auth CoreのUser、Credential、Session、Role、Permission、Policyが機能する
- Auth CoreのAccess Decision EvidenceとAuthorization Auditが機能する
- Auth Coreの運用・耐性機能が機能する
- Realtime Databaseのv0.004新機能が機能する
- Realtime Databaseのv0.005新機能が機能する
- Realtime Databaseのv0.006実運用耐性機能が機能する
- Realtime Databaseのv0.007実運用強化機能が機能する
- Realtime Databaseのv0.008実運用耐性強化機能が機能する
- Realtime DatabaseがDeployment Systemに依存せず機能する
- Applications境界が維持される
- docs境界が維持され、テスト関連ドキュメントが`docs/testing.md`へ集約される

## Release Conditions

`v0.019`のリリース条件は次の通り。

- `php tests/debug.php`が成功する
- Deployment Systemの現行仕様とソースコードが破棄されている
- Realtime Database readinessが成功する
- Authentication / Authorization readinessが成功する
- Realtime DatabaseがDeployment Systemに依存せず成功する
