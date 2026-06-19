# Adlaire Ecosystem Specification

Adlaire Ecosystemは`v0.002`としてBaaS Projectの実運用土台を固める。

## Deployment-Integrated Definition

| 項目 | 内容 |
|------|------|
| Name | Adlaire Ecosystem |
| Version | v0.002 |
| Type | BaaS Project |
| Policy | Zero-base restart |
| Compatibility | 未定義 |

名称はAdlaire Ecosystemを継承する。実装は`v0.002`仕様から組み立てる。Coreの`Project`境界は採用せず、名称、version、manifest、readiness、release summaryはDeployment Systemへ統合する。

## Core Scope

`v0.002`で計画する中核機能は次のみ。

1. Deployment System
2. Realtime Database

Authentication、Authorization、その他BaaS機能は未定義とする。Adlaire独自方式の扱いを確定するまで、これらの仕様、設計、実装、テストは行わない。

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

`v0.002`では、Adlaire独自方式をDeployment SystemとRealtime Databaseにのみ適用する。

## v0.002 Design Decision

`v0.002`はRealtime Databaseを実運用へ進めるための土台バージョンである。Deployment Systemは白紙状態として維持し、Realtime DatabaseはSQLite永続化を中核に据える。

| 項目 | 方針 |
|------|------|
| Deployment System | 白紙化、基本方針から再定義予定 |
| Deployment execution | 実行なし |
| Deployment role | 未定義 |
| Realtime Database | BaaS Core Feature |
| Realtime Database model | Collection + Schema + Record + Event Log + Snapshot + Cursor |
| Data persistence | SQLiteファイル永続化 |
| Selected database | SQLite |
| Compatibility target | libSQL |
| Storage policy | SQLite primary, libSQL compatible |
| Runtime fallback | in-memory互換 |
| Fingerprint | 時刻情報を含めない安定fingerprint |
| Authentication | 未定義 |
| Authorization | 未定義 |

Realtime DatabaseとDatabase選定の詳細は本ドキュメントのRealtime Database章を正とする。Deployment Systemの詳細仕様、release gate、evidence、rollback view、release decisionは再定義まで確定しない。

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

現時点のDeployment Systemは白紙状態である。過去のDeployment設計、release gate、preview、evidence、rollback view、release decisionを正本仕様として採用しない。

`v0.002`では、Deployment Systemについて次のみを定義する。

- Deployment Systemは存在するが、方式は未定義である
- Deployment Systemは実行しない
- Deployment Systemはrelease readyを出さない
- Deployment SystemはRealtime Databaseの仕様整理を妨げない
- Deployment Systemの新方針は別途仕様取りまとめから開始する

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

`v0.002`のRealtime DatabaseはSQLiteを正選定する。libSQLは正選定しない。

選定結果:

| 項目 | 内容 |
|------|------|
| Selected database | SQLite |
| Role | local embedded database |
| Reason | BaaS Core Featureの初期実装を小さく保ち、Event Log型のデータモデルと相性がよい |
| Runtime in v0.002 | SQLite persistent |
| Fallback runtime | in-memory |
| Persistence in v0.002 | 実装する |
| Compatibility target | libSQL |
| libSQL role | SQLite互換の将来remote/sync/edge拡張候補 |
| libSQL selection | 正選定しない |

`v0.002`ではSQLiteファイル永続化を実装する。libSQL接続、remote syncは実装しない。in-memoryはテストと互換用途のfallbackとして残す。

必須要件:

- BaaS Core Featureとしてplanned stateを持つ
- Deployment Systemには依存しない独立したCore機能として先に仕様を確定する
- Event Log型として計画する
- SQLiteファイル永続化を扱う
- in-memory互換fallbackを扱う
- WAL modeを有効化する
- SQLite integrity checkを返す
- default collectionをSQLite永続化時にも永続化対象として扱う
- transaction失敗時はrecord、event、sequence、SQLite書き込みをrollbackする
- export fingerprintはpath、file sizeなど環境依存値を除外して安定化する
- restore前にdatabase export payloadを検証し、不正payloadでは既存状態を破壊しない
- collection定義を持つ
- collection schemaを持つ
- channel定義を持つ
- record metadataを持つ
- queryを返す
- index planned stateを持つ
- migration planned stateを持つ
- event stream方針を持つ
- event payload summaryを持つ
- subscription modelを持つ
- transaction boundaryを持つ
- snapshot exportを返す
- database exportとsnapshot restoreを返す
- conflict detectionを持つ
- event replayとread model rebuildを返す
- access rule placeholderを持つ
- realtime adapter boundaryを持つ
- operational healthを返す
- recordの取得とcollection単位のrecord一覧を返す
- cursorによりevent logを追跡できる
- collection単位でevent streamを追跡できる
- snapshotによりcollectionの現在状態を返す
- readinessとfingerprintを返す

Realtime Database planned state:

```text
feature: realtime_database
version: v0.002
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
```

Realtime Database readinessは、BaaS Core Featureとしてplanned state、collection model、event log、snapshot、cursor、SQLite persistence、in-memory fallbackを判定できる状態を意味する。Deployment Systemの新方針が確定するまでは、Realtime DatabaseはDeployment Systemに依存しない。

### Realtime Database BaaS Contract

`v0.002`のRealtime Databaseは外部公開APIではないが、BaaS機能として次のCore契約を持つ。

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
| Conflict detection | expected versionと現在versionの不一致を検出する |
| Event replay | event payloadからcollection状態を再生する |
| Read model rebuild | event logからsnapshot viewを再構築する |
| Cursor | 最後に観測したevent idを表す |
| Access rules | Authentication/Authorization未定義のため`undefined`を返す |
| Realtime adapter boundary | `none`を返し、pull cursor方式を明示する |

Realtime Databaseは`v0.002`ではpush型接続を持たない。realtime性はevent logとcursorによる追跡可能性として扱う。

### Realtime Database Feature Set

`v0.002`で実装するBaaSデータベース機能は次の通り。

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
```

## Realtime Database Data Model

`v0.002`のRealtime Databaseは、次の実データ構造を扱う。

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

`v0.002`の実データはSQLiteファイルへ永続化できる。in-memoryはfallbackとして維持する。libSQLはSQLite互換の将来拡張候補であり、現時点では接続しない。

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
```

これらはPublic APIではなく、Core内部の実データ操作である。`v0.002`ではテストとCore判定のためPHP上はpublic staticとして実装する。

## Undefined Scope

Adlaire独自方式の問題を解消するまで、次は未定義とする。

- Authentication
- Authorization
- その他BaaS機能

未定義の領域は、仕様・設計・実装・テストへ進めない。

## Documents

`docs/README.md`は簡易説明のみ。詳細仕様と設計判断は本ドキュメントへ集約する。

すべてのドキュメントは`docs/`へ集約する。テスト関連の補助ドキュメントは`docs/testing.md`へ集約する。

## Tests

公式テスト入口は次のみ。

```sh
php tests/debug.php
```

テスト関連ドキュメント:

```text
docs/testing.md
```

テストは次を検証する。

- 許可ディレクトリのみ存在する
- Coreが3フォルダ、3〜5 PHPファイル原則を満たす
- Docker関連境界として`Docker/`が存在する
- Deployment Systemが白紙状態である
- Deployment Systemがrelease readyを出さない
- Realtime Database readinessが成功する
- Realtime DatabaseがBaaS Core Featureとして判定可能である
- Realtime Databaseのcollection、event log、snapshot、cursorが機能する
- Realtime Databaseのrecord取得、record一覧、collection streamが機能する
- Realtime Databaseのschema、metadata、query、subscription、transaction、snapshot export、index planned stateが機能する
- Realtime Databaseのpatch、stats、migration plan、database export、snapshot restore、conflict detection、event replay、read model rebuild、access rule placeholder、realtime adapter boundaryが機能する
- Realtime DatabaseのSQLite永続化、default collection永続化、transaction rollback、restore validation、operational healthが機能する
- Applications境界が維持される
- docs境界が維持され、テスト関連ドキュメントが`docs/testing.md`へ集約される

## Release Conditions

`v0.002`のリリース条件は次の通り。

- `php tests/debug.php`が成功する
- Core readinessが成功する
- Deployment Systemが白紙状態として明示される
- Realtime Database readinessが成功する
- 現行構成のreadinessが成功する

## Development Order

開発は常に次の順序で進める。

1. 仕様取りまとめ
2. 設計
3. 実装
4. バグ修正
5. テスト
6. リリース判定
