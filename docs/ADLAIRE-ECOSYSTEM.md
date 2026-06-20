# Adlaire Ecosystem Specification

Adlaire Ecosystemは`v0.007`としてBaaS Projectの実運用土台を固める。

## Deployment-Integrated Definition

| 項目 | 内容 |
|------|------|
| Name | Adlaire Ecosystem |
| Version | v0.007 |
| Type | BaaS Project |
| Policy | Zero-base restart |
| Compatibility | 未定義 |

名称はAdlaire Ecosystemを継承する。実装は`v0.007`仕様から組み立てる。Coreの`Project`境界は採用せず、名称、version、manifest、readiness、release summaryはDeployment Systemへ統合する。

## Approval Governance

本プロジェクトの最高準拠ルールは、全工程でユーザー承認を必須とすることである。この承認規則は、今後追加する運用方針ではなく、元々存在する最上位前提ルールである。

承認前の仕様確定、実装、リリース判定は禁止する。未承認の作業は実行してはならない。

テスト関連は承認工程に含めない。実装後とバグ修正後は、追加承認を待たずに必ず公式テストを実行する。

バグ修正は承認工程に含めない。実装後にバグがある場合は、追加承認を待たずにバグ修正ゼロになるまで必ず修正する。

草案は仕様確定案として提示する。草案は、承認前の仕様確定案であり、仕様確定承認の対象である。提案案と仕様案を分ける無駄な承認プロセスは設けない。

仕様確定案は、明示的な仕様確定承認を得るまで仕様確定として扱わない。仕様確定承認後、実装承認の前に、明示的なバージョン計画承認を必ず得る。バージョン計画承認は、確定仕様から作成したバージョン計画に基づいて承認を得る工程である。バージョン計画承認後は、承認状態を簡潔に`docs/version-plan.md`へ明記する。全てのバージョン計画承認は`docs/version-plan.md`へ集約する。バージョン計画ファイルの記載は、要点のみを簡潔に明記する。テスト関係はバージョン計画に含めない。バグ修正はバグ修正後にまとめて記載する。設計案と設計承認の工程は設けない。実装は、仕様確定承認とバージョン計画承認を得た後、さらに明示的な実装承認を得るまで行ってはならない。仕様確定承認、バージョン計画承認、実装承認は別工程であり、いずれかの承認を別工程の承認として扱ってはならない。

必須順序:

1. 仕様確定案
2. 仕様確定承認
3. バージョン計画承認
4. 実装承認
5. 実装
6. バグ修正
7. テスト
8. リリース判定承認
9. リリース判定

承認範囲外の追加実装、先行実装、ついで実装は禁止する。過去に作成した未承認案や未承認実装は、元々のルール違反として扱い、明示承認がない限り正本仕様または正規実装として扱わない。

実装は確定仕様に厳格に従う。仕様に明記されていない機能、挙動、境界、ファイル、依存関係は実装しない。

実装中に仕様不足が判明した場合は実装を止め、仕様確定案、仕様確定承認、バージョン計画承認、実装承認の順に戻す。仕様外実装、仕様未記載の拡張、確定仕様から逸脱した実装は禁止する。

## External Dependency Principle

Adlaireに関わる全てのプロジェクトは、外部依存を認めないことを原則かつ最高準拠とする。

仕様で明示的に正選定された基盤を除き、外部サービス、外部同期、外部API、外部SDKへの依存を前提にしない。外部依存が必要に見える場合でも、まずAdlaire独自設計で代替する。

remote syncは採用しない。remote syncが担う差分追跡、状態再構築、競合検出、復旧はRealtime DatabaseのEvent Log、Cursor、Snapshot、Replay、Export/Restoreで扱う。

libSQLはSQLite互換の将来拡張として決定済みである。ただし、libSQLは外部依存を正当化する理由にはならず、実装対象にする場合は別途、仕様確定承認、バージョン計画承認、実装承認を必要とする。

## Core Scope

`v0.007`で計画する中核機能は次のみ。

1. Deployment System
2. Realtime Database

Authentication、Authorization、その他BaaS機能は未定義とする。Adlaire独自方式の扱いを確定するまで、これらの仕様確定案、仕様確定承認、バージョン計画承認、実装承認、実装、テストは行わない。

## Adlaire Method

Adlaire独自方式は、従来型のAPI契約、interface契約、SDK契約ではない。

Adlaire独自方式はDeployment Systemを軸にする予定だが、Deployment Systemは基本方針からやり直すため現時点では白紙状態である。

Adlaire独自方式で定義する項目は次の通り。

| 項目 | 意味 |
|------|------|
| Deployment axis | Deployment Systemが最終判定軸であること |
| Deployable unit | Deployment Systemが判定する機能単位 |
| Planned state | 対象機能が予定状態を表せること |
| Readiness | Deployment Systemが配置可能性を判定できること |
| Evidence | 判定根拠をfingerprint化できること |
| Rollback view | 変更前へ戻す見通しを出せること |
| Runtime independence | 実行時APIやSDKを前提にしないこと |

`v0.007`では、Adlaire独自方式をDeployment SystemとRealtime Databaseにのみ適用する。

## v0.007 Version Decision

`v0.007`はRealtime Databaseの実運用強化を進めるバージョンである。v0.006の異常検知、安全停止、復旧判断を土台にし、書き込み前後の検査、重要操作の抑止、復旧前の影響確認、運用証跡の集約を強化する。

Deployment Systemは白紙状態を維持する。Authentication、Authorization、SDK、API Gateway、WebSocket、SSE、push connection、remote sync、libSQL実装、自動修復、自動restoreは対象外とする。

実装対象:

- Write Intent Log
- Write Commit Verification
- Recovery Simulation
- Restore Impact Report
- Event Chain Integrity
- Snapshot Integrity Seal
- Operational Runbook Report
- Degraded Mode
- Critical Operation Guard
- Operational Evidence Bundle

## Version History

過去バージョンの承認済み計画、対象範囲、バグ修正要約は`docs/version-plan.md`へ集約する。本ドキュメントでは現行`v0.007`仕様のみを正本として詳述する。

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

`Core/`は3フォルダ、3〜5 PHPファイル原則で構成する。

Core直下の境界フォルダ:

```text
Core/Runtime/
Core/Deployment/
Core/Database/
```

| File | Role |
|------|------|
| `Core/Runtime/Runtime.php` | shared runtime helpers |
| `Core/Deployment/Deployment.php` | deployment system blank state, reset marker, undefined release gate |
| `Core/Database/Database.php` | realtime database BaaS feature, data model, event log, snapshot, readiness |

必要な機能は本仕様からゼロベースで実装する。

Core PHPファイルは3〜5ファイルに収める。Core配下の機能境界はRuntime、Deployment、Databaseの3フォルダを正とする。Coreの`Project`境界は不要とし、作成しない。

Project統合方針:

```text
Project boundary: none
Name/version/manifest: Deployment System
Readiness/release summary: Deployment System
Shared helpers: Runtime
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

現時点のDeployment Systemは白紙状態である。過去のDeployment案、release gate、preview、evidence、rollback view、release decisionを正本仕様として採用しない。

`v0.007`では、Deployment Systemについて次のみを定義する。

- Deployment Systemは存在するが、方式は未定義である
- Deployment Systemは実行しない
- Deployment Systemはrelease readyを出さない
- Deployment SystemはRealtime Databaseの仕様整理を妨げない
- Deployment Systemの新方針は別途仕様確定案から開始する

Deployment System output:

```text
state: blank
execution: none
release_ready: false
reason: deployment_system_policy_reset
```

## Realtime Database

Realtime DatabaseはSQLiteを正選定したBaaS Core Featureである。

### Database Selection

`v0.007`のRealtime DatabaseはSQLiteを正選定する。libSQLはSQLite互換の将来拡張として決定済みであるが、`v0.007`では実装しない。

選定結果:

| 項目 | 内容 |
|------|------|
| Selected database | SQLite |
| Role | local embedded database |
| Reason | BaaS Core Featureの初期実装を小さく保ち、Event Log型のデータモデルと相性がよい |
| Runtime in v0.007 | SQLite persistent |
| Fallback runtime | in-memory |
| Persistence in v0.007 | 実装する |
| Compatibility target | libSQL |
| libSQL role | SQLite互換の将来拡張 |
| libSQL runtime | v0.007では実装しない |

`v0.007`ではSQLiteファイル永続化を維持する。libSQL接続、remote syncは実装しない。in-memoryはテストと互換用途のfallbackとして残す。

Realtime Database planned state:

```text
feature: realtime_database
version: v0.007
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
runtime_execution: sqlite_persistent
fallback_runtime: in_memory
sqlite_persistence: true
wal_mode: true
integrity_check: true
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
readonly_runtime_report: true
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
```

Realtime Database readinessは、BaaS Core Featureとしてplanned state、collection model、event log、snapshot、cursor、SQLite persistence、in-memory fallbackを判定できる状態を意味する。Deployment Systemの新方針が確定するまでは、Realtime DatabaseはDeployment Systemに依存しない。

### Realtime Database BaaS Contract

`v0.007`のRealtime Databaseは外部公開APIではないが、BaaS機能として次のCore契約を持つ。

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
| WAL mode | SQLiteのjournal modeをWALとして扱う |
| Operational health | storage status、integrity check、record/event count、latest cursorを返す |
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
| Write safety preflight | 書き込み前にpolicy、schema、runtime状態を検査する |
| Restore safety gate | restore前にpayload、drift、riskを検査する |
| Backup consistency report | backup payloadの整合性、manifest、gapを返す |
| Event gap report | event sequenceとcursorの欠落を検査する |
| Corruption suspect report | audit、event gap、storage結果から破損疑いを返す |
| Operational risk score | 運用状態のrisk scoreとlevelを返す |
| Recovery decision report | restore、observe、manual reviewの判断材料を返す |
| Safe mode | 実行時に全書き込みを停止する |
| Readonly runtime report | safe mode、maintenance、lockを含む書き込み可否を返す |
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
| Cursor | 最後に観測したevent idを表す |
| Access rules | Authentication/Authorization未定義のため`undefined`を返す |
| Realtime adapter boundary | `none`を返し、pull cursor方式を明示する |

Realtime Databaseは`v0.007`ではpush型接続を持たない。realtime性はevent logとcursorによる追跡可能性として扱う。

### Realtime Database Feature Set

`v0.007`で実装するBaaSデータベース機能は次の通り。

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
| Storage Status | SQLite path、WAL、integrity check、file sizeを返す |
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
| Readonly Runtime Report | 読み取り専用状態と書き込み可否を返す |
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
runtime_execution: sqlite_persistent | in_memory
dry_run: true
rollback_plan: true
history: list
```

## Realtime Database Data Model

`v0.007`のRealtime Databaseは、次の実データ構造を扱う。

| Model | 意味 |
|-------|------|
| Collection | recordのまとまり |
| Record | collection内の1件のデータ |
| Event | record変更の履歴 |
| Snapshot | collectionの現在状態 |
| Cursor | event logの追跡位置 |

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

`v0.007`の実データはSQLiteファイルへ永続化できる。in-memoryはfallbackとして維持する。libSQLはSQLite互換の将来拡張として決定済みであるが、`v0.007`では接続しない。

SQLite tables:

```text
collections
records
events
schema_versions
database_meta
```

SQLite runtime:

```text
journal_mode: WAL
foreign_keys: ON
integrity_check: ok
```

SQLite永続化の運用条件:

```text
default collections are persisted
failed transaction leaves no partial records
failed transaction leaves no partial events
export fingerprint excludes path and file_size
restore validates selected_database and fingerprint before mutation
json encode failure is an explicit runtime error
```

## Realtime Database Operations

`Core/Database/Database.php`は次のCore内部メソッドを持つ。

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
```

これらはPublic APIではなく、Core内部の実データ操作である。`v0.007`ではテストとCore判定のためPHP上はpublic staticとして実装する。

## Undefined Scope

Adlaire独自方式の問題を解消するまで、次は未定義とする。

- Authentication
- Authorization
- その他BaaS機能

未定義の領域は、仕様確定案・仕様確定承認・バージョン計画承認・実装承認・実装・テストへ進めない。

## Documents

`docs/README.md`は簡易説明のみ。詳細仕様と判断根拠は本ドキュメントへ集約する。

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
- Coreが3フォルダ、3〜5 PHPファイル原則を満たす
- Docker関連境界として`Docker/`が存在する
- 現行テストがPHPソースコードベースである
- Docker本番相当テストが将来計画として整理されている
- Deployment Systemが白紙状態である
- Deployment Systemがrelease readyを出さない
- Realtime Database readinessが成功する
- Realtime DatabaseがBaaS Core Featureとして判定可能である
- Realtime Databaseのcollection、event log、snapshot、cursorが機能する
- Realtime Databaseのrecord取得、record一覧、collection streamが機能する
- Realtime Databaseのschema、metadata、query、subscription、transaction、snapshot export、index planned stateが機能する
- Realtime Databaseのpatch、stats、migration plan、database export、snapshot restore、conflict detection、event replay、read model rebuild、access rule placeholder、realtime adapter boundaryが機能する
- Realtime DatabaseのSQLite永続化、default collection永続化、transaction rollback、restore validation、operational healthが機能する
- Realtime Databaseのintegrity audit、diagnostics、write policy、query explain、import validationが機能する
- Realtime Databaseの実運用耐性機能が機能する
- Realtime Databaseのv0.004新機能が機能する
- Realtime Databaseのv0.005新機能が機能する
- Realtime Databaseのv0.006実運用耐性機能が機能する
- Realtime Databaseのv0.007実運用強化機能が機能する
- Applications境界が維持される
- docs境界が維持され、テスト関連ドキュメントが`docs/testing.md`へ集約される

## Release Conditions

`v0.007`のリリース条件は次の通り。

- `php tests/debug.php`が成功する
- Core readinessが成功する
- Deployment Systemが白紙状態として明示される
- Realtime Database readinessが成功する
- 現行構成のreadinessが成功する

## Development Order

開発は常に次の順序で進める。

1. 仕様確定案
2. 仕様確定承認
3. バージョン計画承認
4. 実装承認
5. 実装
6. バグ修正
7. テスト
8. リリース判定承認
9. リリース判定
