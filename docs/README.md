# Adlaire Ecosystem

Adlaire Ecosystemは、Adlaireグループの内部システム基盤としてスタートしたBaaS基盤プロジェクトです。

Realtime Databaseを中核に、SQLiteとEvent Logを軸とした永続化と変更履歴、Authentication / Authorizationによる利用者・権限管理を組み合わせ、外部依存を抑えた実運用向けのデータ管理基盤を構築します。

本プロジェクトは、外部同期、外部message broker、外部IAMに依存せず、Adlaire独自のCore基盤として、record、collection、snapshot、cursor、replay、export/restore、access decisionを扱います。

Event Logは、Realtime Databaseの変更履歴を追記型で保持する内部履歴基盤です。状態変化を証跡として保持し、変更履歴の追跡、整合性確認、復旧判断、監査の根拠を支えます。

Snapshot、Cursor、Replay、Export/Restoreと組み合わせることで、現在状態の再構築と復旧判断を支える基盤とします。

Deployment Systemは現行対象に含めません。
