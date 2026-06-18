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

1. Deployment
2. Realtime Database

Authentication、Authorization、その他BaaS機能は未定義とする。Adlaire独自方式の扱いを確定するまで、これらの仕様、設計、実装、テストは行わない。

## Adlaire Method

Adlaire独自方式は、従来型のAPI契約、interface契約、SDK契約ではない。

Deployment Systemを軸に、対象機能が配置、更新、検証、rollbackできる状態かを判定する方式である。

Adlaire独自方式で定義する項目は次の通り。

| 項目 | 意味 |
|------|------|
| Deployment axis | Deployment Systemが最終判定軸であること |
| Planned state | 対象機能が予定状態を表せること |
| Readiness | Deploymentが配置可能性を判定できること |
| Evidence | 判定根拠をfingerprint化できること |
| Rollback view | 変更前へ戻す見通しを出せること |
| Runtime independence | 実行時APIやSDKを前提にしないこと |

`v0.001`では、Adlaire独自方式をDeploymentとRealtime Databaseにのみ適用する。

## v0.001 Design Decision

`v0.001`は実行機能ではなく、Deployment Systemが判定できる状態を定義する初期バージョンである。

| 項目 | 方針 |
|------|------|
| Realtime Database | Event Log型 |
| Data persistence | in-memory実データ |
| Deployment execution | 実行なし |
| Deployment role | 判定、preview、evidence、rollback view |
| Fingerprint | 時刻情報を含めない安定fingerprint |
| Authentication | 未定義 |
| Authorization | 未定義 |

Realtime DatabaseはWebSocket型ではなく、Event Log型として計画する。`v0.001`ではin-memory実データを扱い、永続化は行わない。

Deploymentは`v0.001`では実ファイル配置、自動デプロイ、サーバ操作を行わない。Deploymentはplanned stateを評価し、release gate、evidence、rollback viewを生成する。

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
| `Deployment.php` | deployment axis, preview, release gate, rollback view |
| `Database.php` | realtime database data model, event log, snapshot, readiness |

必要な機能は本仕様からゼロベースで実装する。

## Applications

`Applications/`はApplication Modulesの境界として維持する。

- Application ModulesはCore外のアプリケーション層として扱う。
- 初期状態では`Applications/.gitkeep`のみを置く。

## Deployment

DeploymentはBaaS Projectの配置、検証、rollbackを扱う。

必須要件:

- previewは読み取り専用
- release gateはProjectとRealtime Databaseのreadinessを統合する
- rollback planは読み取り専用
- Core readinessに接続する
- Realtime Databaseのplanned stateをrelease gateで判定する
- evidenceはplanned state、readiness、rollback view、release gate resultから生成する
- evidence fingerprintには時刻情報を含めない

Deployment output:

```text
preview
release_gate
evidence
rollback_view
readiness
```

## Realtime Database

Realtime DatabaseはSQLite / libSQL方針を前提にしたCore機能である。

必須要件:

- Event Log型として計画する
- in-memory実データを扱う
- channel定義を持つ
- event stream方針を持つ
- readinessとfingerprintを返す
- Deploymentが判定できるplanned stateを返す

Realtime Database planned state:

```text
feature: realtime_database
version: v0.001
state: planned
storage: sqlite_libsql
mode: event_log
channels: system, application
event_stream: internal
rollback_required: true
runtime_execution: in_memory
```

Realtime Database readinessは、Deploymentがplanned stateとin-memory data modelを判定できる状態を意味する。

## Realtime Database Data Model

`v0.001`のRealtime Databaseは、次の実データ構造を扱う。

| Model | 意味 |
|-------|------|
| Collection | recordのまとまり |
| Record | collection内の1件のデータ |
| Event | record変更の履歴 |
| Snapshot | collectionの現在状態 |
| Cursor | event logの追跡位置 |

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
collection: string
record_id: string
type: create | update | delete
version: integer
payload_hash: sha256
```

Snapshot:

```text
collection: string
records: list
version: integer
fingerprint: sha256
```

Cursor:

```text
after: event id | null
```

`v0.001`の実データはin-memoryで保持する。SQLite / libSQL永続化はplanned stateの方針として保持し、永続化実装は行わない。

## Realtime Database Operations

`Database.php`は次の内部メソッドを持つ。

```text
create(collection, data)
update(collection, id, data)
delete(collection, id)
snapshot(collection)
events(after)
```

これらはPublic APIではなく、Core内部の実データ操作である。

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
- Deployment readinessが成功する
- Realtime Database readinessが成功する
- Applications境界が維持される
- docs境界が維持される

## Release Conditions

`v0.001`のリリース条件は次の通り。

- `php tests/debug.php`が成功する
- Core readinessが成功する
- 現行構成のreadinessが成功する

## Development Order

開発は常に次の順序で進める。

1. 仕様取りまとめ
2. 設計
3. 実装
4. バグ修正
5. テスト
6. リリース判定
