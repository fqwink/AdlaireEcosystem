# Adlaire Ecosystem Specification

Adlaire Ecosystemは`v0.001`からBaaS Projectとしてゼロベースで再スタートする。

## Project Definition

| 項目 | 内容 |
|------|------|
| Project | Adlaire Ecosystem |
| Version | v0.001 |
| Type | BaaS Project |
| Policy | Zero-base restart |
| Compatibility | 未定義 |

プロジェクト名はAdlaire Ecosystemを継承する。実装は`v0.001`仕様から組み立てる。

## Core Scope

`v0.001`で計画する中核機能は次のみ。

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

`v0.001`では、Adlaire独自方式をDeployment SystemとRealtime Databaseにのみ適用する。

## v0.001 Design Decision

`v0.001`は実行機能ではなく、Deployment Systemが判定できる状態を定義する初期バージョンである。

| 項目 | 方針 |
|------|------|
| Deployment System | 白紙化、基本方針から再定義予定 |
| Deployment execution | 実行なし |
| Deployment role | 未定義 |
| Realtime Database | BaaS Core Feature |
| Realtime Database model | Collection + Record + Event Log + Snapshot + Cursor |
| Data persistence | in-memory実データ |
| Selected database | SQLite |
| Compatibility target | libSQL |
| Storage policy | SQLite primary, libSQL compatible |
| Fingerprint | 時刻情報を含めない安定fingerprint |
| Authentication | 未定義 |
| Authorization | 未定義 |

Realtime DatabaseはWebSocket型ではなく、Event Log型として計画する。DatabaseはSQLiteを正選定する。libSQLはSQLite互換の将来拡張候補として保持する。`v0.001`ではin-memory実データを扱い、永続化は行わない。

Deployment Systemは基本方針からやり直すため、現時点では白紙状態として扱う。Deployment Systemの詳細仕様、release gate、evidence、rollback view、release decisionは再定義まで確定しない。

## Directory Policy

維持できるディレクトリは次のみ。

```text
Core/
Applications/
docs/
tests/
```

現行構成は上記ディレクトリに集約する。

## Core Files

`Core/`は次の3ファイルで構成する。

| File | Role |
|------|------|
| `Project.php` | project identity, readiness, release summary |
| `Deployment.php` | deployment system blank state, reset marker, undefined release gate |
| `Database.php` | realtime database BaaS feature, data model, event log, snapshot, readiness |

必要な機能は本仕様からゼロベースで実装する。

## Applications

`Applications/`はApplication Modulesの境界として維持する。

- Application ModulesはCore外のアプリケーション層として扱う。
- 初期状態では`Applications/.gitkeep`のみを置く。

## Deployment System

Deployment Systemは基本方針からやり直す。

現時点のDeployment Systemは白紙状態である。過去のDeployment設計、release gate、preview、evidence、rollback view、release decisionを正本仕様として採用しない。

`v0.001`では、Deployment Systemについて次のみを定義する。

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

Realtime DatabaseはSQLite / libSQL方針を前提にしたBaaS Core Featureである。

### Database Selection

`v0.001`のRealtime DatabaseはSQLiteを選定する。

選定結果:

| 項目 | 内容 |
|------|------|
| Selected database | SQLite |
| Role | local embedded database |
| Reason | BaaS Core Featureの初期実装を小さく保ち、Event Log型のデータモデルと相性がよい |
| Runtime in v0.001 | in-memory |
| Persistence in v0.001 | 実装しない |
| Compatibility target | libSQL |
| libSQL role | 将来のremote/sync/edge拡張候補 |

SQLiteを正選定とし、libSQLは互換性を意識した設計対象に留める。`v0.001`ではSQLiteファイル永続化、libSQL接続、remote syncは実装しない。

必須要件:

- BaaS Core Featureとしてplanned stateを持つ
- Deployment Systemには依存しない独立したCore機能として先に仕様を確定する
- Event Log型として計画する
- in-memory実データを扱う
- collection定義を持つ
- channel定義を持つ
- event stream方針を持つ
- recordの取得とcollection単位のrecord一覧を返す
- cursorによりevent logを追跡できる
- collection単位でevent streamを追跡できる
- snapshotによりcollectionの現在状態を返す
- readinessとfingerprintを返す

Realtime Database planned state:

```text
feature: realtime_database
version: v0.001
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
snapshot: collection_state
rollback_required: true
runtime_execution: in_memory
```

Realtime Database readinessは、BaaS Core Featureとしてplanned state、collection model、event log、snapshot、cursor、in-memory data modelを判定できる状態を意味する。Deployment Systemの新方針が確定するまでは、Realtime DatabaseはDeployment Systemに依存しない。

### Realtime Database BaaS Contract

`v0.001`のRealtime Databaseは外部公開APIではないが、BaaS機能として次のCore契約を持つ。

| 契約 | 内容 |
|------|------|
| Collection registry | collection名、channel、storageを管理する |
| Record store | collection内の現在recordを管理する |
| Event log | create、update、deleteを追記型eventとして保持する |
| Event stream | cursor以降のeventを返す |
| Collection stream | collection単位でcursor以降のeventを返す |
| Snapshot | collectionの現在状態とfingerprintを返す |
| Cursor | 最後に観測したevent idを表す |

Realtime Databaseは`v0.001`ではpush型接続を持たない。realtime性はevent logとcursorによる追跡可能性として扱う。

## Realtime Database Data Model

`v0.001`のRealtime Databaseは、次の実データ構造を扱う。

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
storage: in_memory
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
data: map
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

`v0.001`の実データはin-memoryで保持する。SQLite / libSQL永続化はplanned stateの方針として保持し、永続化実装は行わない。

## Realtime Database Operations

`Database.php`は次のCore内部メソッドを持つ。

```text
defineCollection(collection, channel)
collections()
create(collection, data)
get(collection, id)
records(collection)
update(collection, id, data)
delete(collection, id)
snapshot(collection)
events(after)
stream(collection, after)
cursor()
```

これらはPublic APIではなく、Core内部の実データ操作である。`v0.001`ではテストとDeployment System判定のためPHP上はpublic staticとして実装する。

## Undefined Scope

Adlaire独自方式の問題を解消するまで、次は未定義とする。

- Authentication
- Authorization
- その他BaaS機能

未定義の領域は、仕様・設計・実装・テストへ進めない。

## Documents

READMEは簡易説明のみ。詳細仕様と設計判断は本ドキュメントへ集約する。

`docs/`は補助説明のみを扱う。

## Tests

公式テスト入口は次のみ。

```sh
php tests/debug.php
```

テストは次を検証する。

- 許可ディレクトリのみ存在する
- Coreが3ファイルである
- Deployment Systemが白紙状態である
- Deployment Systemがrelease readyを出さない
- Realtime Database readinessが成功する
- Realtime DatabaseがBaaS Core Featureとして判定可能である
- Realtime Databaseのcollection、event log、snapshot、cursorが機能する
- Realtime Databaseのrecord取得、record一覧、collection streamが機能する
- Applications境界が維持される
- docs境界が維持される

## Release Conditions

`v0.001`のリリース条件は次の通り。

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
