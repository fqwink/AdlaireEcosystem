# Testing

このファイルは、テスト方針、公式テスト入口、テスト範囲の集約先です。

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

## v0.014 Test Scope

- 許可ディレクトリのみ存在すること
- 必須動作要件、承認済み文言、外部依存禁止が仕様へ明記されていること
- Core直下のPHPファイルがエントリポイントのみであること
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
- Realtime Databaseのv0.004新機能が動作すること
- Realtime Databaseのv0.005新機能が動作すること
- Realtime Databaseのv0.006実運用耐性機能が動作すること
- Realtime Databaseのv0.007実運用強化機能が動作すること
- Realtime Databaseのv0.008実運用耐性強化機能が動作すること
- Realtime Databaseのv0.014実運用耐性強化と実運用強化機能が動作すること
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
