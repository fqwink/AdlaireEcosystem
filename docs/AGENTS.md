# AGENTS.md

このリポジトリで作業するエージェントは、変更前に必ず`docs/AGENTS.md`と`docs/ADLAIRE-ECOSYSTEM.md`を確認する。

## 正本

- 仕様の正本は`docs/ADLAIRE-ECOSYSTEM.md`。
- READMEは`docs/README.md`に置き、日本語の簡易説明のみとする。
- 詳細仕様、設計判断、リリース条件は`docs/ADLAIRE-ECOSYSTEM.md`へ集約する。
- テスト関連の補助ドキュメントは`docs/testing.md`へ集約する。
- 仕様と実装が異なる場合は、仕様を正として実装を修正する。

## 現行方針

- 現行バージョンは`v0.002`。
- 名称はAdlaire Ecosystemを継承する。
- Adlaire EcosystemはBaaS Projectとしてゼロベースで再スタートする。
- `v0.002`で計画する中核機能はDeployment System、Realtime Databaseのみ。
- Deployment Systemは基本方針からやり直すため、現時点では白紙状態として扱う。
- Authentication、Authorization、その他BaaS機能は未定義とし、Adlaire独自方式を確定するまで実装しない。
- Realtime DatabaseのDatabaseはSQLiteを正選定し、libSQLは正選定ではなくSQLite互換の将来拡張候補として扱う。

## 許可ディレクトリ

作成・維持できるディレクトリは次のみ。

- `Core/`
- `Applications/`
- `Docker/`
- `docs/`
- `tests/`

現行構成は上記ディレクトリに集約する。

## Core構成

`Core/`は3フォルダ、3〜5 PHPファイル原則で構成する。

- `Core/Runtime/Runtime.php`
- `Core/Deployment/Deployment.php`
- `Core/Database/Database.php`

Core直下に置く境界フォルダは次の3つとする。

- `Runtime/`
- `Deployment/`
- `Database/`

Project境界は作成しない。名称、version、manifest、readiness、release summaryはDeployment Systemへ統合する。

## Docker

- `Docker/`はDocker関連ファイルの境界として維持する。
- 今後のDockerfile、compose、Docker用スクリプト、Docker用設定は`Docker/`へ格納する。
- 初期状態では`Docker/.gitkeep`のみを置く。

## Applications

- `Applications/`はApplication Modulesの境界として維持する。
- Application ModulesはCore外のアプリケーション層として扱う。
- 初期状態では`Applications/.gitkeep`のみを置く。

## 開発順序

すべての変更は次の順序で進める。

1. 仕様取りまとめ
2. 設計
3. 実装
4. バグ修正
5. テスト
6. リリース判定

仕様が未定義の領域は先に`docs/ADLAIRE-ECOSYSTEM.md`へ追記してから実装する。

## テスト

変更後は次を実行する。

```sh
php tests/debug.php
```
