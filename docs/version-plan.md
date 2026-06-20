# Version Plan

- このファイルは、全てのバージョン計画承認を集約する単一のバージョン計画ファイルである。
- 記載順は最新バージョンを上にする。
- 記載形式はリスト形式に統一する。
- 記載内容は要点のみを簡潔に明記する。
- テスト関係はバージョン計画に含めない。
- バグ修正内容は、各バージョンごとに修正後まとめて簡潔に記載する。

## v0.008

- `version: v0.008`
- `status: version_plan_approved`
- `scope: realtime_database_only`
- `implementation_status: implemented`
- `implementation_approval: approved`
- `specification_confirmation: approved`
- `version_plan: approved`
- `implementation: approved`
- `primary_axis: realtime_database`
- `purpose: realtime_database_operational_resilience_hardening`
- `deployment_system: blank`
- `authentication: undefined`
- `authorization: undefined`
- `external_dependency: not_allowed`
- `remote_sync: not_adopted`
- `libsql_implementation: out_of_scope`
- `implementation_targets: Pre-Write Risk Evaluation, Critical Write Two-Step Guard, Backup Restore Compatibility Check, Snapshot Seal Verification, Operational Degradation Reason, Incident Severity Classification, Recovery Readiness Report, Operation Freeze Policy, Data Durability Report, Release Safety Evidence`
- `out_of_scope: automatic_repair, automatic_restore, remote_sync, websocket, sse, push_connection, sdk_implementation, api_gateway_implementation, authentication, authorization, libsql_implementation`
- `constraints: realtime_database_only, no_external_dependency, no_remote_sync, no_libsql_runtime, strict_confirmed_spec`
- `bugfix_summary: 実装後確認で追加バグ修正なし`

## v0.007

- `version: v0.007`
- `status: version_plan_approved`
- `scope: realtime_database_only`
- `implementation_status: implemented`
- `implementation_approval: approved`
- `specification_confirmation: approved`
- `version_plan: approved`
- `implementation: approved`
- `primary_axis: realtime_database`
- `purpose: realtime_database_operational_hardening`
- `deployment_system: blank`
- `authentication: undefined`
- `authorization: undefined`
- `external_dependency: not_allowed`
- `remote_sync: not_adopted`
- `libsql_implementation: out_of_scope`
- `implementation_targets: Write Intent Log, Write Commit Verification, Recovery Simulation, Restore Impact Report, Event Chain Integrity, Snapshot Integrity Seal, Operational Runbook Report, Degraded Mode, Critical Operation Guard, Operational Evidence Bundle`
- `out_of_scope: automatic_repair, automatic_restore, remote_sync, websocket, sse, push_connection, sdk_implementation, api_gateway_implementation, authentication, authorization, libsql_implementation`
- `constraints: realtime_database_only, no_external_dependency, no_remote_sync, no_libsql_runtime, strict_confirmed_spec`
- `bugfix_summary: 実装後確認で追加バグ修正なし`

## v0.006

- `version: v0.006`
- `status: version_plan_approved`
- `scope: realtime_database_only`
- `implementation_status: implemented`
- `implementation_approval: approved`
- `specification_confirmation: approved`
- `version_plan: approved`
- `implementation: approved`
- `primary_axis: realtime_database`
- `purpose: realtime_database_operational_resilience`
- `deployment_system: blank`
- `authentication: undefined`
- `authorization: undefined`
- `external_dependency: not_allowed`
- `remote_sync: not_adopted`
- `libsql_implementation: out_of_scope`
- `implementation_targets: Health Baseline, Drift Baseline Compare, Write Safety Preflight, Restore Safety Gate, Backup Consistency Report, Event Gap Report, Corruption Suspect Report, Operational Risk Score, Recovery Decision Report, Safe Mode, Readonly Runtime Report, Incident Timeline`
- `out_of_scope: automatic_repair, automatic_restore, automatic_backup_generation_management, automatic_deletion, remote_sync, websocket, sse, push_connection, sdk_implementation, api_gateway_implementation, authentication, authorization, libsql_implementation`
- `constraints: realtime_database_only, no_external_dependency, no_remote_sync, no_libsql_runtime, strict_confirmed_spec`
- `bugfix_summary: 承認プロセス文言とテスト期待値の旧表記を修正`

## v0.005

- `version: v0.005`
- `status: version_plan_approved`
- `scope: realtime_database_only`
- `implementation_status: implemented`
- `implementation_approval: approved`
- `specification_confirmation: approved`
- `version_plan: approved`
- `implementation: approved`
- `primary_axis: realtime_database`
- `purpose: realtime_database_feature_enhancement`
- `deployment_system: blank`
- `authentication: undefined`
- `authorization: undefined`
- `external_dependency: not_allowed`
- `remote_sync: not_adopted`
- `libsql_implementation: out_of_scope`
- `implementation_targets: Change Feed Filter, Record Version History, Record Diff, Snapshot Retention Plan, Backup Manifest, Restore Preview, Collection Lock, Write Quota Guard, Event Checkpoint, Operational Incident Report, Query Cursor Enhancement, Import Validation Enhancement, Audit Integrity Enhancement, Operational Report Enhancement, Data Redaction Export Enhancement, Schema Versioning Enhancement`
- `out_of_scope: remote_sync, websocket, sse, sdk_implementation, api_gateway_implementation, authentication, authorization, libsql_implementation, automatic_repair, automatic_backup_generation_management, automatic_deletion`
- `constraints: realtime_database_only, no_external_dependency, no_remote_sync, no_libsql_runtime, strict_confirmed_spec`
- `bugfix_summary: 実装後確認で追加バグ修正なし`

## v0.004

- `version: v0.004`
- `status: version_plan_approved`
- `scope: realtime_database_only`
- `implementation_status: implemented`
- `implementation_approval: approved`
- `specification_confirmation: approved`
- `version_plan: approved`
- `implementation: approved`
- `primary_axis: realtime_database`
- `purpose: operational_resilience_and_realtime_database_features`
- `deployment_system: blank`
- `authentication: undefined`
- `authorization: undefined`
- `external_dependency: not_allowed`
- `remote_sync: not_adopted`
- `selected_database: sqlite`
- `sqlite_role: primary_storage`
- `libsql_role: decided_sqlite_compatible_future_extension`
- `libsql_runtime: not_implemented_in_v0.004`
- `operational_resilience: Operational Guard, Maintenance Mode, Write Policy Enforcement, Startup Self Check, Backup Verification, Restore Dry Run, Recovery Check, Event Log Consistency Check, Cursor Safety, Read Model Drift Detection, Operational Metrics, Operational Report`
- `realtime_database_features: Collection Lifecycle, Schema Versioning, Bulk Import Dry Run, Bulk Write, Record Restore, Snapshot Compare, Event Replay Range, Query Cursor Pagination, Collection Export Filter, Data Redaction Export`
- `plan_only: Record TTL Plan, Subscriber Checkpoint Plan`
- `out_of_scope: deployment_system_redesign, authentication, authorization, non_realtime_database_baas_features, libsql_implementation, remote_sync, sdk_implementation, api_gateway_implementation, websocket_implementation, automatic_repair, automatic_backup_generation_management, automatic_event_deletion`
- `constraints: no_external_dependency, no_remote_sync, no_libsql_runtime, strict_confirmed_spec, core_3_folders_3_to_5_php_files`
- `bugfix_summary: Read Model Drift判定のrestore後の偽陽性と、複数record状態のsnapshot restore/rebuild件数不整合を修正`

## v0.003

- `version: v0.003`
- `bugfix_summary: Query Explainのfull scan判定確認条件を、indexed fieldではなくnon-indexed field基準に修正`
