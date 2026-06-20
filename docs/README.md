# Adlaire Ecosystem

Adlaire Ecosystemは`v0.003`としてBaaS Projectの実運用土台を固めます。

`v0.003`で計画する中核機能はDeployment SystemとRealtime Databaseです。

Realtime DatabaseのDatabaseはSQLiteを正選定し、SQLiteファイル永続化を実装します。

すべての仕様確定、設計、実装、リリース判定は`docs/ADLAIRE-ECOSYSTEM.md`の承認プロセスを正とします。提案案は草案であり、仕様確定承認を得るまで正本仕様ではありません。

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

承認済みバージョン計画は`docs/version-plan.md`へ集約します。

詳細仕様と設計判断は`docs/ADLAIRE-ECOSYSTEM.md`へ集約します。
