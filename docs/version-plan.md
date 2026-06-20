# Version Plan

このファイルは、全てのバージョン計画承認を集約する単一のバージョン計画ファイルである。

バージョン計画承認後の承認状態は、このファイルへ簡易的に明記する。

## Bug Fix Summary

バグ修正内容は、各バージョンごとに修正後まとめて簡潔に記載する。

### v0.003

- Query Explainのfull scan判定確認条件を、indexed fieldではなくnon-indexed field基準に修正。

### v0.004

- 実装未開始のため、現時点のバグ修正なし。

## v0.004

### Status

```text
version: v0.004
status: version_plan_approved
scope: realtime_database_only
implementation_status: not_started
```

### Approval

`v0.004`は次の承認状態とする。

```text
specification_confirmation: approved
version_plan: approved
implementation: not_approved
```

実装は、別途、明示的な実装承認を得るまで開始しない。

### Axis

`v0.004`はRealtime Databaseを軸にする。

```text
primary_axis: realtime_database
purpose: operational_resilience_and_realtime_database_features
deployment_system: blank
authentication: undefined
authorization: undefined
external_dependency: not_allowed
remote_sync: not_adopted
```

### Database Position

```text
selected_database: sqlite
sqlite_role: primary_storage
libsql_role: decided_sqlite_compatible_future_extension
libsql_runtime: not_implemented_in_v0.004
```

SQLiteは正選定を維持する。libSQLはSQLite互換の将来拡張として決定済みであるが、`v0.004`では実装しない。

### Implementation Targets

`v0.004`の実装対象はRealtime Databaseに限定する。

#### Operational Resilience

- Operational Guard
- Maintenance Mode
- Write Policy Enforcement
- Startup Self Check
- Backup Verification
- Restore Dry Run
- Recovery Check
- Event Log Consistency Check
- Cursor Safety
- Read Model Drift Detection
- Operational Metrics
- Operational Report

#### Realtime Database Features

- Collection Lifecycle
- Schema Versioning
- Bulk Import Dry Run
- Bulk Write
- Record Restore
- Snapshot Compare
- Event Replay Range
- Query Cursor Pagination
- Collection Export Filter
- Data Redaction Export

#### Plan Only

- Record TTL Plan
- Subscriber Checkpoint Plan

### Out Of Scope

`v0.004`では次を実装しない。

```text
deployment_system_redesign
authentication
authorization
non_realtime_database_baas_features
libsql_implementation
remote_sync
sdk_implementation
api_gateway_implementation
websocket_implementation
automatic_repair
automatic_backup_generation_management
automatic_event_deletion
```

### Constraints

- 外部依存は追加しない。
- remote syncは採用しない。
- 実装は確定仕様に厳格に従う。
- 仕様外実装、先行実装、ついで実装は禁止する。
- Coreは3フォルダ、3〜5 PHPファイル原則を維持する。
- バグ修正はバグ修正後にまとめて記載する。
- テスト関係はバージョン計画に含めない。

### Completion Conditions

- `v0.004` planned stateが確定仕様と一致する。
- Realtime Databaseの実運用耐性が仕様通りに実装される。
- Realtime Database新機能が仕様通りに実装される。
- 外部依存が追加されていない。
- remote syncが実装されていない。
- libSQLが実装されていない。
- docsが`v0.004`仕様と一致する。
