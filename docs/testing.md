# Testing

このファイルは、Adlaire Ecosystemにおけるテスト関係の集約先です。

公式テスト入口、テスト方針、テスト範囲、ドキュメント修正後の確認項目は本ファイルに集約します。

Adlaire Ecosystemの公式テスト入口は、Docker環境下のCLI検証として次のみです。

```sh
php tests/debug.php
```

現行テストはPHPソースコードベースで行います。

## Test Policy

```text
current: php_source_code_based
entrypoint: docker_environment_cli_php_tests_debug
future: docker_production_like_environment
docker_assets: Docker/
approval_required: false
required_after_implementation: true
required_after_bugfix: true
bugfix_approval_required: false
bugfix_until_zero: true
```

テストは承認工程に含めません。実装後とバグ修正後は、追加承認を待たずに必ず公式テストを実行します。

バグ修正は承認工程に含めません。実装後にバグがある場合は、追加承認を待たずにバグ修正ゼロになるまで必ず修正します。

## Documentation Test Policy

ドキュメント修正後は、公式テストで次を確認します。

- 仕様正本が`docs/ADLAIRE-ECOSYSTEM.md`へ集約されていること
- 作業ルール正本が`docs/AGENTS.md`へ集約されていること
- テスト関連が`docs/testing.md`へ集約されていること
- READMEが外部向けの簡潔なプロジェクト説明に留まること
- 現行仕様と異なる古い記載が残っていないこと
- Runtime廃止、Deployment System白紙、Auth Core追加が現行仕様として矛盾しないこと

## v0.019 Test Scope

カテゴリ:

- Directory
- Core
- Realtime Database
- Event Log
- Authentication / Authorization
- SQLite persistence
- Documents

- 許可ディレクトリのみ存在すること
- 必須動作要件、承認済み文言、外部依存禁止が仕様へ明記されていること
- Core直下が共通基盤機能とエントリポイントの2機能であること
- `Core/EventLog.php`がEvent Log単一ファイルであること
- `Core/Auth.php`がAuthentication / Authorizationの単一エントリポイントであること
- Event Log用フォルダが存在しないこと
- `Core/Database/`が3 PHPファイルで構成されること
- `Core/Database/DatabaseCore.php`、`Core/Database/DatabaseStorage.php`、`Core/Database/DatabaseOperations.php`が存在すること
- `Core/Auth/`が3 PHPファイルで構成されること
- `Core/Auth/AuthCore.php`、`Core/Auth/AuthStorage.php`、`Core/Auth/AuthOperations.php`が存在すること
- `Core/Runtime.php`と`Core/Runtime/`が存在しないこと
- Core直下の内部フォルダにエントリポイントを置かないこと
- 内部フォルダ内PHPファイルが内部実装のみであること
- Project境界を作成しないこと
- Docker関連境界として`Docker/`が存在すること
- 現行テストがPHPソースコードベースであること
- Docker本番相当環境テストが将来計画として整理されていること
- Deployment Systemの現行仕様とソースコードが破棄されていること
- Realtime Database readinessが成功すること
- Realtime DatabaseのBaaS Core Feature機能が動作すること
- Realtime DatabaseのSQLite永続化が動作すること
- Realtime Databaseの実運用耐性機能が動作すること
- Authentication / Authorization readinessが成功すること
- User Registry、Credential Registry、Session Registryが動作すること
- Role Registry、Permission Registry、Policy Registryが動作すること
- Access Decision Evidence、Authorization Audit、Deny Reason Registryが動作すること
- Auth Operational Dashboard、Auth Control Tower、Auth Trust Ledgerが動作すること
- Auth Production Readiness Gate、Auth Write Safety Gate、Auth Emergency Freeze Viewが動作すること
- v0.021のAuth実運用、実運用耐性、監査証跡、運用判断機能が動作すること
- Auth Change Impact Report、Policy Simulation、Authorization Regression Guardが動作すること
- Auth Evidence Export、Auth Evidence Import Validation、Auth State Compare、Auth Operations Ledger、Auth Control Summaryが動作すること
- Realtime Databaseのv0.004新機能が動作すること
- Realtime Databaseのv0.005新機能が動作すること
- Realtime Databaseのv0.006実運用耐性機能が動作すること
- Realtime Databaseのv0.007実運用強化機能が動作すること
- Realtime Databaseのv0.008実運用耐性強化機能が動作すること
- Event LogのEnvelope、Domain Source、Metadata、Type Registryが動作すること
- Event LogのChain Hash、Validation、Cursor Contract、Replay Scope、Evidenceが動作すること
- Event LogのSnapshot Link、Replay Verification、Import Validation、Export Packet、Retention View、Risk Report、Operation Journalが動作すること
- Event LogのHealth Summary、Recovery Evidence、Operational Guard、Trust Score、Restore Readiness、Audit Packetが動作すること
- Event LogのIncident Packet、Degradation Report、Write Safety Gate、Replay Window、Cursor Drift Report、Export Integrityが動作すること
- Event LogのRestore Impact、Retention Decision View、Operational SLO、Handoff Summary、Preflight Report、Chain Snapshotが動作すること
- Event LogのContinuity Proof、Payload Integrity Report、Domain Isolation Report、Recovery Route、Manual Review Queue、Operational Timeline、Evidence Seal、Trust Ledgerが動作すること
- SQLite有効化時にdefault collectionがSQLite対象として扱われること
- 失敗したtransactionがrecord、event、SQLite書き込みを残さないこと
- database export fingerprintが環境依存値に引っ張られないこと
- 不正なdatabase restore payloadが既存状態を破壊しないこと
- SQLite上のsoft deleteが再ロード後も非表示として扱われること
- integrity auditがrecord、event、schema、payload hashを検査できること
- diagnosticsがstorage、schema、query、event、backup、auditをまとめて返すこと
- write policyが書き込み上限と許可schema typeを返すこと
- query explainがindex利用とfull scan警告を返すこと
- import validationが外部record投入前にdry-run検証できること
- Applications境界とdocs境界が維持されること
