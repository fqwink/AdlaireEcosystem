# Adlaire Ecosystem

Adlaire Ecosystemは`v0.012`としてBaaS Projectの実運用土台を固めます。

`v0.012`で維持する中核機能はRealtime Databaseです。Deployment Systemは白紙化し、現行仕様とソースコードを破棄しています。

Realtime DatabaseのDatabaseはSQLiteを正選定し、実運用耐性とRealtime Database機能を強化します。

詳細仕様、承認プロセス、判断根拠は`docs/ADLAIRE-ECOSYSTEM.md`を正とします。

## 主要ディレクトリ

```text
Core/
Applications/
Docker/
docs/
tests/
```

## テスト

```sh
php tests/debug.php
```

関連ドキュメント: `docs/testing.md`, `docs/version-plan.md`
