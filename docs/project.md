# Adlaire Ecosystem Boundary

Adlaire Ecosystemは`v0.003`としてBaaS Projectの実運用土台を固めます。

`v0.003`で計画する中核はDeployment SystemとRealtime Databaseです。

Project境界は作成せず、名称、version、manifest、readiness、release summaryはDeployment Systemへ統合します。

Realtime DatabaseはSQLiteを正選定し、SQLiteファイル永続化の詳細仕様は正本へ集約します。

Coreは3フォルダ、3〜5 PHPファイル原則で維持します。

Docker関連ファイルは`Docker/`へ集約します。

テスト関連の補助ドキュメントは`docs/testing.md`へ集約します。

詳細仕様と設計判断は`docs/ADLAIRE-ECOSYSTEM.md`に集約します。
