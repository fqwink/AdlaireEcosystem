# Version Plan

- このファイルは、バージョン計画承認とバグ修正要約を集約する正本である。
- 全てのバージョン計画承認は、この単一ファイルへ集約する。
- 記載順は最新バージョンを上にする。
- 記載形式はリスト形式に統一する。
- 記載内容は要点のみを簡潔に明記する。
- テスト関係はバージョン計画に含めない。
- バグ修正内容は、各バージョンごとに修正後まとめて簡潔に記載する。

## v0.028

- `version: v0.028`
- `status: version_plan_approved`
- `implementation_status: implemented`
- `approvals: specification_confirmation, version_plan, implementation`
- `implementation: approved`
- `scope: docker_production_operation_report_periodic_update`
- `purpose: Docker実運用想定検証レポートを定期的に更新し、継続状態、最新ログ、経過時間、バグ、デバッグ、追加検証結果を集約する`
- `targets: Docker/verification/README.md, Docker/verification/production-operation-report.md, docs/AGENTS.md, docs/testing.md, docs/ADLAIRE-ECOSYSTEM.md, docs/version-plan.md`
- `bugfix_summary: 未記載`

## v0.027

- `version: v0.027`
- `status: version_plan_approved`
- `implementation_status: implemented`
- `approvals: specification_confirmation, version_plan, implementation`
- `implementation: approved`
- `scope: docker_production_operation_verification_report_repository`
- `purpose: Docker実運用想定検証のログ、バグ、デバッグ情報をリポジトリ内のレポート方式で集約する`
- `targets: Docker/verification/README.md, Docker/verification/production-operation-report.md, docs/AGENTS.md, docs/testing.md, docs/ADLAIRE-ECOSYSTEM.md, docs/version-plan.md`
- `bugfix_summary: 実装後確認で追加バグ修正なし`

## v0.026

- `version: v0.026`
- `status: version_plan_approved`
- `implementation_status: implemented`
- `approvals: specification_confirmation, version_plan, implementation`
- `implementation: approved`
- `scope: docker_verification_result_labeling`
- `purpose: Docker検証実行時に、Docker開発検証かDocker実運用想定検証かを必ず明記し、実運用想定検証未実施の場合も明記する`
- `targets: docs/AGENTS.md, docs/testing.md, docs/ADLAIRE-ECOSYSTEM.md, docs/version-plan.md`
- `bugfix_summary: 未記載`

## v0.025

- `version: v0.025`
- `status: version_plan_approved`
- `implementation_status: implemented`
- `approvals: specification_confirmation, version_plan, implementation`
- `implementation: approved`
- `scope: docker_verification_role_split`
- `purpose: Docker開発検証とDocker実運用想定検証を明確に分け、実運用想定検証は72時間以上の長期間検証として定義する`
- `targets: docs/AGENTS.md, docs/testing.md, docs/ADLAIRE-ECOSYSTEM.md, docs/version-plan.md`
- `bugfix_summary: 未記載`

## v0.024

- `version: v0.024`
- `status: version_plan_approved`
- `implementation_status: implemented`
- `approvals: specification_confirmation, version_plan, implementation`
- `implementation: approved`
- `scope: approval_process_strict_individual_instruction_rule`
- `purpose: 個別指示であっても承認プロセス対象の変更は即時実行せず、未承認なら実行プロセス停止とするルールを明記する`
- `targets: docs/AGENTS.md, docs/ADLAIRE-ECOSYSTEM.md, docs/version-plan.md`
- `bugfix_summary: 未記載`

## v0.023

- `version: v0.023`
- `status: version_plan_approved`
- `implementation_status: implemented`
- `approvals: specification_confirmation, version_plan, implementation`
- `implementation: approved`
- `scope: docker_verification_policy_update`
- `purpose: CLI公式テストとPHP 8.3 CLI公式テストを完全廃止し、Docker検証を実運用想定のリポジトリ全体検証へ統一する`
- `targets: docs/AGENTS.md, docs/ADLAIRE-ECOSYSTEM.md, docs/testing.md`
- `bugfix_summary: Docker検証実行後に残存した旧テスト表現、CLI公式テストソース、testsフォルダを削除`

## v0.022

- `version: v0.022`
- `status: version_plan_approved`
- `implementation_status: implemented`
- `approvals: specification_confirmation, version_plan, implementation`
- `implementation: approved`
- `scope: docker_production_equivalent_environment`
- `purpose: Docker本番環境同等の検証基盤を作る`
- `targets: Docker/Dockerfile, Docker/compose.yml, Docker/php.ini, Docker/entrypoint.sh, Docker/run-production-check.sh, docs/testing.md, docs/version-plan.md`
- `environment: web + php + sqlite_volume`
- `verification: HTTP疎通, PHP拡張確認, SQLite永続化確認, Core読み込み確認`
- `constraints: no_external_dependency, no_external_db, no_remote_sync, no_message_broker, no_deployment_system, no_readme_change, docker_files_only, testing_doc_update_only`
- `bugfix_summary: HTTP health判定、SQLite volume権限、Docker追加後の確認内容を修正`

## v0.021

- `version: v0.021`
- `status: version_plan_approved`
- `implementation_status: implemented`
- `approvals: specification_confirmation, version_plan, implementation`
- `implementation: approved`
- `scope: auth_operations_resilience_hardening`
- `purpose: Authentication / Authorizationの実運用、監査、権限事故予防、手動復旧判断を強化`
- `targets: change impact, policy simulation, revocation impact, permission coverage, dormant/stale reports, failed login trend, access baseline/drift, evidence export/import validation, state compare, regression guard, operations ledger, control summary`
- `event_log_targets: auth_change_impact_report, policy_simulation, authorization_regression_guard, auth_operations_ledger, auth_control_summary`
- `constraints: no_external_dependency, no_oauth, no_iam, no_policy_engine, no_mail_sms, no_remote_sync, no_message_broker, no_auto_repair, no_auto_recovery, no_auto_delete, no_auto_rotation, no_auto_privilege_escalation, undefined_policy_deny, no_plain_password, preserve_AdlaireAuth_API, auth_file_count_3, strict_confirmed_spec`
- `bugfix_summary: 仕様書の旧現行バージョン表現をv0.021へ修正`

## v0.020

- `version: v0.020`
- `status: version_plan_approved`
- `implementation_status: implemented`
- `approvals: specification_confirmation, version_plan, implementation`
- `implementation: approved`
- `scope: documentation_governance_cleanup`
- `purpose: ドキュメント全体の役割、最高準拠、承認プロセス、テスト集約、バージョン計画簡潔化を整理`
- `targets: docs/AGENTS.md, docs/ADLAIRE-ECOSYSTEM.md, docs/testing.md, docs/version-plan.md`
- `read_only_scope: docs/README.md, Core, Applications, Docker`
- `constraints: no_source_code_change, no_readme_change, no_project_doc_restore, no_split_policy, no_feature_change, no_test_scope_expansion`
- `bugfix_summary: 実装後確認で追加バグ修正なし`

## v0.019

- `version: v0.019`
- `status: version_plan_approved`
- `implementation_status: implemented`
- `approvals: specification_confirmation, version_plan, implementation`
- `implementation: approved`
- `scope: runtime_removal_auth_authorization_core_feature`
- `purpose: Runtime廃止、Auth Core追加、Event Log証跡基盤強化`
- `core_entrypoints: Core/Database.php, Core/EventLog.php, Core/Auth.php`
- `core_folders: Core/Database, Core/Auth, Core/Deployment`
- `database_file_count: 3`
- `auth_file_count: 3`
- `event_log_file: Core/EventLog.php`
- `deployment_system: completely_blank`
- `database: sqlite`
- `constraints: no_external_dependency, no_remote_sync, no_message_broker, no_runtime, no_runtime_replacement_category, no_plain_password, no_auto_repair, no_auto_recovery, no_auto_delete, no_auto_rotation, undefined_policy_deny, preserve_AdlaireDatabase_API, strict_confirmed_spec`
- `bugfix_summary: 仕様書のv0.019 Realtime Database 3ファイル分割表現を確認内容に合わせて修正`

## v0.018

- `version: v0.018`
- `status: version_plan_approved`
- `implementation_status: implemented`
- `approvals: specification_confirmation, version_plan, implementation`
- `implementation: approved`
- `scope: database_three_file_split_deployment_blank_event_log_operations_resilience`
- `purpose: Realtime Database 3ファイル化、Deployment System完全白紙、Event Log運用耐性強化`
- `database_file_count: 3`
- `event_log_file: Core/EventLog.php`
- `deployment_system: completely_blank`
- `constraints: no_external_dependency, no_remote_sync, no_message_broker, no_auto_repair, no_auto_compaction, no_auto_delete, preserve_AdlaireDatabase_API, strict_confirmed_spec`
- `bugfix_summary: 実装後確認で追加バグ修正なし`

## v0.017

- `version: v0.017`
- `status: version_plan_approved`
- `implementation_status: implemented`
- `approvals: specification_confirmation, version_plan, implementation`
- `implementation: approved`
- `scope: event_log_core_trust_foundation`
- `purpose: Event LogをCore横断の信頼履歴基盤として強化`
- `targets: Event Envelope, Chain Hash, Replay, Evidence, Cursor Contract, Import/Export`
- `remote_sync: not_adopted`
- `constraints: no_external_dependency, no_remote_sync, no_message_broker, no_auto_repair, no_auto_compaction, no_auto_delete, preserve_AdlaireDatabase_API, strict_confirmed_spec`
- `bugfix_summary: 実装後確認で追加バグ修正なし`

## v0.016

- `version: v0.016`
- `status: version_plan_approved`
- `implementation_status: implemented`
- `approvals: specification_confirmation, version_plan, implementation`
- `implementation: approved`
- `scope: core_event_log_common_foundation`
- `purpose: Event LogをCore横断共通基盤へ昇格`
- `event_log_file: Core/EventLog.php`
- `event_log_folder: prohibited`
- `remote_sync: not_adopted`
- `constraints: no_eventlog_folder, no_multi_file_eventlog, no_database_api_regression, no_external_dependency, strict_confirmed_spec`
- `bugfix_summary: Event Replay Proofのevent_count補正、WAL/integrity checkの要件化記載削除`

## v0.015

- `version: v0.015`
- `status: version_plan_approved`
- `implementation_status: implemented`
- `approvals: specification_confirmation, version_plan, implementation`
- `implementation: approved`
- `scope: realtime_database_only`
- `purpose: Realtime Databaseの新機能、実運用強化、実運用耐性強化`
- `targets: state digest, write readiness, restore inspection, operational board, recovery evidence, production readiness ledger`
- `remote_sync: not_adopted`
- `constraints: realtime_database_only, no_external_dependency, no_remote_sync, no_deployment_system, no_libsql_runtime, no_sdk_api_gateway_websocket, no_authentication_authorization, no_auto_repair, strict_confirmed_spec`
- `bugfix_summary: 実装後確認で追加バグ修正なし`

## v0.014

- `version: v0.014`
- `status: version_plan_approved`
- `implementation_status: implemented`
- `approvals: specification_confirmation, version_plan, implementation`
- `implementation: approved`
- `scope: mandatory_runtime_requirement_strictness`
- `purpose: 必須動作要件の文言を厳格化`
- `absolute_principle: 必要だが必須ではないという表現を禁止`
- `mandatory_runtime_scope: 必須動作要件に基づく範囲内はすべて必須要件`
- `constraints: no_ambiguous_required_wording, no_extra_runtime_requirement, no_external_dependency, strict_confirmed_spec`
- `bugfix_summary: 実装後確認で追加バグ修正なし`

## v0.013

- `version: v0.013`
- `status: version_plan_approved`
- `implementation_status: implemented`
- `approvals: specification_confirmation, version_plan, implementation`
- `implementation: approved`
- `scope: mandatory_runtime_requirements`
- `purpose: 必須動作要件を定義`
- `php: 8.3_recommended`
- `required_extensions: json, PDO, pdo_sqlite`
- `development_cli: required`
- `database: sqlite`
- `external_dependency: prohibited`
- `constraints: minimum_requirements_only, no_extra_runtime_requirement, no_external_dependency, strict_confirmed_spec`
- `bugfix_summary: 実装後確認で追加バグ修正なし`

## v0.012

- `version: v0.012`
- `status: version_plan_approved`
- `implementation_status: implemented`
- `approvals: specification_confirmation, version_plan, implementation`
- `implementation: approved`
- `scope: deployment_system_blank_reset`
- `purpose: Deployment Systemの既存仕様とソースコードを白紙化`
- `deployment_system: discarded`
- `deployment_folder: boundary_only`
- `remote_sync: not_adopted`
- `constraints: no_deployment_replacement, no_new_deployment_spec, no_realtime_database_regression, no_external_dependency, strict_confirmed_spec`
- `bugfix_summary: 実装後確認で追加バグ修正なし`

## v0.011

- `version: v0.011`
- `status: version_plan_approved`
- `implementation_status: implemented`
- `approvals: specification_confirmation, version_plan, implementation`
- `implementation: approved`
- `scope: core_structure_refactor`
- `purpose: Core直下エントリポイントと内部フォルダ方針を整理`
- `core_root_php_files: entrypoints_only`
- `internal_php_file_limit: 3_to_5_per_internal_folder`
- `remote_sync: not_adopted`
- `constraints: no_feature_regression, no_external_dependency, entrypoint_only_core_root, no_entrypoint_inside_internal_folder, strict_confirmed_spec`
- `bugfix_summary: 実装後確認で追加バグ修正なし`

## v0.010

- `version: v0.010`
- `status: version_plan_approved`
- `implementation_status: implemented`
- `approvals: specification_confirmation, version_plan, implementation`
- `implementation: approved`
- `scope: realtime_database_only`
- `purpose: Realtime Databaseの実運用・耐性を強化`
- `targets: operational baseline, recovery priority, production readiness, safety board, control tower`
- `remote_sync: not_adopted`
- `constraints: realtime_database_only, no_external_dependency, no_remote_sync, no_libsql_runtime, no_auto_restore, no_auto_repair, strict_confirmed_spec`
- `bugfix_summary: Write Anomaly Detectorの正常系critical write判定とRead Consistency Verificationの比較粒度を修正`

## v0.009

- `version: v0.009`
- `status: version_plan_approved`
- `implementation_status: implemented`
- `approvals: specification_confirmation, version_plan, implementation`
- `implementation: approved`
- `scope: realtime_database_only`
- `purpose: Realtime Databaseの運用耐性と運用機能を強化`
- `targets: SLO, failure classification, backup freshness, restore ranking, recovery drill, handoff`
- `remote_sync: not_adopted`
- `constraints: realtime_database_only, no_external_dependency, no_remote_sync, no_libsql_runtime, no_auto_restore, no_auto_repair, strict_confirmed_spec`
- `bugfix_summary: backup鮮度テストのcurrent export参照とData Lifecycle Guardの未実装項目判定を修正`

## v0.008

- `version: v0.008`
- `status: version_plan_approved`
- `implementation_status: implemented`
- `approvals: specification_confirmation, version_plan, implementation`
- `implementation: approved`
- `scope: realtime_database_only`
- `purpose: Realtime Databaseの運用耐性を強化`
- `targets: pre-write risk, two-step guard, compatibility check, severity, freeze policy, durability`
- `remote_sync: not_adopted`
- `constraints: realtime_database_only, no_external_dependency, no_remote_sync, no_libsql_runtime, strict_confirmed_spec`
- `bugfix_summary: 実装後確認で追加バグ修正なし`

## v0.007

- `version: v0.007`
- `status: version_plan_approved`
- `implementation_status: implemented`
- `approvals: specification_confirmation, version_plan, implementation`
- `implementation: approved`
- `scope: realtime_database_only`
- `purpose: Realtime Databaseの実運用機能を強化`
- `targets: write intent, commit verification, recovery simulation, impact report, degradation, operation guard`
- `remote_sync: not_adopted`
- `constraints: realtime_database_only, no_external_dependency, no_remote_sync, no_libsql_runtime, strict_confirmed_spec`
- `bugfix_summary: 実装後確認で追加バグ修正なし`

## v0.006

- `version: v0.006`
- `status: version_plan_approved`
- `implementation_status: implemented`
- `approvals: specification_confirmation, version_plan, implementation`
- `implementation: approved`
- `scope: realtime_database_only`
- `purpose: Realtime Databaseの運用耐性を追加`
- `targets: health baseline, drift compare, write preflight, restore gate, risk score, safe mode`
- `remote_sync: not_adopted`
- `constraints: realtime_database_only, no_external_dependency, no_remote_sync, no_libsql_runtime, strict_confirmed_spec`
- `bugfix_summary: 承認プロセス文言とテスト期待値の旧表記を修正`

## v0.005

- `version: v0.005`
- `status: version_plan_approved`
- `implementation_status: implemented`
- `approvals: specification_confirmation, version_plan, implementation`
- `implementation: approved`
- `scope: realtime_database_only`
- `purpose: Realtime Databaseの機能強化`
- `targets: change feed, version history, diff, retention plan, backup manifest, restore preview, lock, quota`
- `remote_sync: not_adopted`
- `constraints: realtime_database_only, no_external_dependency, no_remote_sync, no_libsql_runtime, strict_confirmed_spec`
- `bugfix_summary: 実装後確認で追加バグ修正なし`

## v0.004

- `version: v0.004`
- `status: version_plan_approved`
- `implementation_status: implemented`
- `approvals: specification_confirmation, version_plan, implementation`
- `implementation: approved`
- `scope: realtime_database_only`
- `purpose: Realtime Databaseの実運用耐性と基本機能を強化`
- `selected_database: sqlite`
- `libsql_role: decided_sqlite_compatible_future_extension`
- `targets: operational guard, maintenance, write policy, startup check, restore dry-run, lifecycle, schema versioning, bulk write, record restore`
- `remote_sync: not_adopted`
- `constraints: no_external_dependency, no_remote_sync, no_libsql_runtime, strict_confirmed_spec, core_3_folders_3_to_5_php_files`
- `bugfix_summary: Read Model Drift判定のrestore後の偽陽性と、複数record状態のsnapshot restore/rebuild件数不整合を修正`

## v0.003

- `version: v0.003`
- `status: implemented`
- `scope: realtime_database_query_foundation`
- `bugfix_summary: Query Explainのfull scan判定確認条件を、indexed fieldではなくnon-indexed field基準に修正`
