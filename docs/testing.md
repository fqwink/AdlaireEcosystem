# Testing

このファイルは、Adlaire Ecosystemにおけるテスト関係の集約先です。

Docker検証方針、検証範囲、ドキュメント修正後の確認項目、補助確認の扱いは本ファイルに集約します。

CLI公式テストは完全廃止します。PHP 8.3 CLI公式テストも完全廃止します。

今後の検証はDocker検証で行います。Docker検証は、実運用を想定したリポジトリ全体の検証です。

## Test Policy

```text
current: docker_verification
scope: repository_wide_operational_verification
environment: docker_production_equivalent
docker_assets: Docker/
approval_required: false
required_after_implementation: true
required_after_bugfix: true
bugfix_approval_required: false
bugfix_until_zero: true
```

テストは承認工程に含めません。実装後とバグ修正後は、追加承認を待たずに必ずDocker検証を実行します。

バグ修正は承認工程に含めません。実装後にバグがある場合は、追加承認を待たずにバグ修正ゼロになるまで必ず修正します。

Docker検証は、`Docker/`配下のweb + php + SQLite永続化環境で行います。Core機能、Docker構成、docs整合性、禁止構成、外部依存禁止、仕様と実装の一致を確認します。

Docker検証は環境起動確認のみではありません。実運用前に問題が出ないかを確認するための全体検証であり、バグ確認、デバッグ、修正後確認、運用前確認を含みます。

Docker検証は、Docker開発検証とDocker実運用想定検証を明確に分けます。

Docker開発検証:

- 役割: 開発中の変更確認、バグ確認、デバッグ、修正後確認、ドキュメント修正後確認
- 時間: 短時間検証
- 扱い: 実装直後や修正直後に行い、変更が壊れていないかを素早く確認する

Docker実運用想定検証:

- 役割: 本番環境に近い条件で実運用に耐えられるかを確認し、リリース前、運用前、重要変更後の判断材料にする
- 時間: 72時間以上の長期間検証を基準とする
- 確認: 本番環境想定の構成、SQLite永続化、HTTP経由動作、Core機能、Event Log、Authentication / Authorization、docs整合性、禁止構成、外部依存禁止、長時間稼働による安定性

Docker開発検証の結果は、Docker実運用想定検証の判断材料として扱えます。短時間確認だけで長期間検証済みとは扱いません。

Docker検証を実行した場合は、検証結果に次を必ず明記します。

- 実行した検証種別: Docker開発検証 または Docker実運用想定検証
- Docker実運用想定検証を実施していない場合: 未実施
- Docker実運用想定検証を実施した場合: 72時間以上の長期間検証結果

Docker実運用想定検証のログ、バグ、デバッグ情報は、リポジトリ内の次へレポート方式で集約します。

```text
Docker/verification/production-operation-report.md
```

実行中の可変ログやSQLite確認用データはDocker volumeに残してよいものとします。

Docker実運用想定検証レポートは定期的に更新します。更新対象は、継続状態、最新ログ、経過時間、バグ記録、デバッグ記録、追加シナリオ結果、停止時の最終結果です。

更新タイミング:

- ユーザーから状況確認、ログ確認、レポート更新の指示があった時
- バグ、デバッグ、追加検証を行った時
- 停止指示を受けた時
- 重要な状態変化があった時

## Documentation Test Policy

ドキュメント修正後は、Docker検証で次を確認します。

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
- Docker検証が実運用想定のリポジトリ全体検証として定義されていること
- CLI公式テストが完全廃止されていること
- PHP 8.3 CLI公式テストが完全廃止されていること
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
