# Adlaire Ecosystem

Adlaire Ecosystemは、Adlaireグループの内部システム基盤としてスタートしたBaaS基盤プロジェクトです。Realtime Databaseを中核機能とし、SQLiteとEvent Logを軸に、外部依存を抑えた実運用向けのデータ管理基盤を構築します。

## Event Log

Event Logは、Realtime Databaseの変更履歴を追記型で保持する内部履歴基盤です。recordの作成、更新、削除、復元などの変更をeventとして記録し、sequenceとcursorによって変更順序を管理します。

Snapshot、Cursor、Replay、Export/Restoreと組み合わせることで、現在状態の再構築、変更履歴の追跡、整合性確認、復旧判断を支える基盤とします。
