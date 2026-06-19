# Testing

Adlaire Ecosystemの公式テスト入口は次のみです。

```sh
php tests/debug.php
```

このファイルは、今後のテスト関連ドキュメントを集約する場所です。すべてのドキュメントは`docs/`配下に置きます。

## v0.002 Test Scope

- 許可ディレクトリのみ存在すること
- Coreが3フォルダ、3〜5 PHPファイル原則を満たすこと
- Project境界を作成せずDeployment Systemへ統合していること
- Docker関連境界として`Docker/`が存在すること
- Deployment Systemが白紙状態であること
- Realtime Database readinessが成功すること
- Realtime DatabaseのBaaS Core Feature機能が動作すること
- Realtime DatabaseのSQLite永続化、WAL、integrity checkが動作すること
- SQLite有効化時にdefault collectionがSQLite対象として扱われること
- 失敗したtransactionがrecord、event、SQLite書き込みを残さないこと
- database export fingerprintが環境依存値に引っ張られないこと
- 不正なdatabase restore payloadが既存状態を破壊しないこと
- SQLite上のsoft deleteが再ロード後も非表示として扱われること
- Applications境界とdocs境界が維持されること
