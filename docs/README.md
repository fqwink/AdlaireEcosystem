# Adlaire Ecosystem

Adlaire Ecosystemは`v0.002`としてBaaS Projectの実運用土台を固めます。

`v0.002`で計画する中核機能はDeployment SystemとRealtime Databaseです。

Realtime DatabaseのDatabaseはSQLiteを正選定し、SQLiteファイル永続化を実装します。

## 主要ディレクトリ

```text
Core/
Applications/
Docker/
docs/
tests/
```

Docker関連ファイルは`Docker/`へ集約します。

## テスト

```sh
php tests/debug.php
```

テスト関連の補助ドキュメントは`docs/testing.md`へ集約します。

詳細仕様と設計判断は`docs/ADLAIRE-ECOSYSTEM.md`へ集約します。
