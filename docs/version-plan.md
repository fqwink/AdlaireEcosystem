# Version Plan

- このファイルは、バージョン計画承認とバグ修正要約を集約する正本である。
- 全てのバージョン計画承認は、この単一ファイルへ集約する。
- 記載順は最新バージョンを上にする。
- 記載形式はリスト形式に統一する。
- 記載内容は要点のみを簡潔に明記する。
- テスト関係はバージョン計画に含めない。
- バグ修正内容は、各バージョンごとに修正後まとめて簡潔に記載する。

## v0.014

- `version: v0.014`
- `status: version_plan_approved`
- `scope: mandatory_runtime_requirement_strictness`
- `implementation_status: implemented`
- `implementation_approval: approved`
- `specification_confirmation: approved`
- `version_plan: approved`
- `implementation: approved`
- `primary_axis: strict_mandatory_runtime_requirement_wording`
- `purpose: prohibit_ambiguous_required_but_not_mandatory_expression`
- `absolute_principle: 必要だが必須ではないという表現を禁止`
- `mandatory_runtime_scope: 必須動作要件に基づく範囲内はすべて必須要件`
- `system_runtime_source: 必須動作要件はシステム動作要件の正本`
- `strict_compliance: 仕様・実装・テスト・ドキュメントは承認済み文言に厳格準拠`
- `runtime_requirements: PHP 8.3 recommended, json, PDO, pdo_sqlite, Docker/deployment CLI only, development CLI required, SQLite, no external dependency`
- `external_dependency_addition: prohibited`
- `database_exception: separate approval_process_required`
- `deployment_system: discarded`
- `deployment_folder: boundary_only`
- `testing_decisions: preserved`
- `implementation_targets: document strict mandatory runtime wording, align docs/tests with approved wording`
- `constraints: no_ambiguous_required_wording, no_extra_runtime_requirement, no_external_dependency, strict_confirmed_spec`
- `bugfix_summary: 実装後確認で追加バグ修正なし`

## v0.013

- `version: v0.013`
- `status: version_plan_approved`
- `scope: mandatory_runtime_requirements`
- `implementation_status: implemented`
- `implementation_approval: approved`
- `specification_confirmation: approved`
- `version_plan: approved`
- `implementation: approved`
- `primary_axis: minimum_system_runtime_requirements`
- `purpose: define_required_runtime_conditions`
- `php: 8.3_recommended`
- `required_extensions: json, PDO, pdo_sqlite`
- `cli: docker_environment_and_deployment_only`
- `development_cli: required`
- `database: sqlite`
- `external_dependency: prohibited`
- `implementation_targets: document mandatory runtime requirements`
- `constraints: minimum_requirements_only, no_extra_runtime_requirement, no_external_dependency, strict_confirmed_spec`
- `bugfix_summary: 実装後確認で追加バグ修正なし`

## v0.012

- `version: v0.012`
- `status: version_plan_approved`
- `scope: deployment_system_blank_reset`
- `implementation_status: implemented`
- `implementation_approval: approved`
- `specification_confirmation: approved`
- `version_plan: approved`
- `implementation: approved`
- `primary_axis: deployment_system_discard`
- `purpose: discard_existing_deployment_system_specification_and_source_code_for_zero_base_redesign`
- `deployment_system_specification: discarded`
- `deployment_system_source_code: discarded`
- `deployment_system_replacement: prohibited`
- `new_deployment_specification: prohibited`
- `deployment_folder: keep_as_boundary_only`
- `realtime_database: preserved`
- `realtime_database_deployment_dependency: removed`
- `external_dependency: not_allowed`
- `remote_sync: not_adopted`
- `libsql_implementation: out_of_scope`
- `implementation_targets: discard AdlaireDeployment class, remove Core/Deployment entrypoint and internal implementation, keep Core/Deployment boundary, remove deployment manifest/readiness/release/state/releaseGate assumptions, remove Realtime Database dependency on deployment version`
- `constraints: no_deployment_replacement, no_new_deployment_spec, no_realtime_database_regression, no_external_dependency, strict_confirmed_spec`
- `bugfix_summary: 実装後確認で追加バグ修正なし`

## v0.011

- `version: v0.011`
- `status: version_plan_approved`
- `scope: core_structure_refactor`
- `implementation_status: implemented`
- `implementation_approval: approved`
- `specification_confirmation: approved`
- `version_plan: approved`
- `implementation: approved`
- `primary_axis: core_entrypoint_and_internal_file_policy`
- `purpose: source_code_growth_control_and_core_structure_stabilization`
- `core_root_php_files: entrypoints_only`
- `core_root_single_file_principle: true`
- `external_entrypoints: Core/Database.php, Core/Deployment.php, Core/Runtime.php`
- `internal_folders: Core/Database, Core/Deployment, Core/Runtime`
- `internal_folder_entrypoint: prohibited`
- `internal_php_files: internal_implementation_only`
- `internal_php_file_limit: 3_to_5_per_internal_folder`
- `public_api: preserved`
- `feature_removal: prohibited`
- `external_dependency: not_allowed`
- `remote_sync: not_adopted`
- `libsql_implementation: out_of_scope`
- `implementation_targets: Core root entrypoint files, internal implementation folders, direct internal file reference prohibition, existing public API preservation, source code growth control`
- `constraints: no_feature_regression, no_external_dependency, entrypoint_only_core_root, no_entrypoint_inside_internal_folder, strict_confirmed_spec`
- `bugfix_summary: 実装後確認で追加バグ修正なし`

## v0.010

- `version: v0.010`
- `status: version_plan_approved`
- `scope: realtime_database_only`
- `implementation_status: implemented`
- `implementation_approval: approved`
- `specification_confirmation: approved`
- `version_plan: approved`
- `implementation: approved`
- `primary_axis: realtime_database`
- `purpose: realtime_database_production_operations_and_resilience_hardening`
- `deployment_system: blank`
- `authentication: undefined`
- `authorization: undefined`
- `external_dependency: not_allowed`
- `remote_sync: not_adopted`
- `libsql_implementation: out_of_scope`
- `implementation_targets: operational baseline, anomaly detection, recovery prioritization, risk timeline, consistency scoring, backup validation, write safety threshold, incident summary, production readiness, operator checklist, drift budget, blast radius, recovery path comparison, integrity attestation, containment policy, regression guard, rotation policy, state transition audit, critical collection profile, production packets, health trend, quarantine recommendation, rebuild safety, backup trust, event gap detection, saturation report, maintenance window, recovery confidence, root cause hints, readiness ledger, admission control, watchlist, schema stability, replay feasibility, restore dry-run evidence, SQLite limits, communication summary, release regression, safety board, control tower, write pressure, recurrence detection, restore checklist, event trust, read consistency, evidence timeline, degraded exit criteria, backup exposure`
- `out_of_scope: automatic_repair, automatic_restore, remote_sync, websocket, sse, push_connection, sdk_implementation, api_gateway_implementation, authentication, authorization, libsql_implementation, record_ttl_runtime_enforcement, automatic_delete, automatic_scheduling, automatic_notification`
- `constraints: realtime_database_only, no_external_dependency, no_remote_sync, no_libsql_runtime, no_automatic_restore, no_automatic_repair, strict_confirmed_spec`
- `bugfix_summary: Write Anomaly Detectorの正常系critical write判定とRead Consistency Verificationの比較粒度を修正`

## v0.009

- `version: v0.009`
- `status: version_plan_approved`
- `scope: realtime_database_only`
- `implementation_status: implemented`
- `implementation_approval: approved`
- `specification_confirmation: approved`
- `version_plan: approved`
- `implementation: approved`
- `primary_axis: realtime_database`
- `purpose: realtime_database_operational_resilience_and_operations_hardening`
- `deployment_system: blank`
- `authentication: undefined`
- `authorization: undefined`
- `external_dependency: not_allowed`
- `remote_sync: not_adopted`
- `libsql_implementation: out_of_scope`
- `implementation_targets: Operational SLO Report, Write Failure Classification, Backup Freshness Report, Restore Candidate Ranking, Read Model Confidence Report, Operational Window Policy, Recovery Drill Report, Incident Evidence Digest, Data Lifecycle Guard, Operational Handoff Report`
- `out_of_scope: automatic_repair, automatic_restore, remote_sync, websocket, sse, push_connection, sdk_implementation, api_gateway_implementation, authentication, authorization, libsql_implementation, record_ttl_runtime_enforcement, automatic_delete`
- `constraints: realtime_database_only, no_external_dependency, no_remote_sync, no_libsql_runtime, no_automatic_restore, no_automatic_repair, strict_confirmed_spec`
- `bugfix_summary: backup鮮度テストのcurrent export参照とData Lifecycle Guardの未実装項目判定を修正`

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
