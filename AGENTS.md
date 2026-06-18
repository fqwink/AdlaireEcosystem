# AGENTS.md

このリポジトリで作業するエージェントは、変更前に必ず本ファイルと`ADLAIRE-ECOSYSTEM.md`を確認する。

## 正本

- 仕様の正本は`ADLAIRE-ECOSYSTEM.md`。
- READMEは日本語の簡易説明のみ。
- 詳細仕様、設計判断、リリース条件は`ADLAIRE-ECOSYSTEM.md`へ集約する。
- 仕様と実装が異なる場合は、仕様を正として実装を修正する。

## 現行方針

- 現行バージョンは`v0.001`。
- プロジェクト名はAdlaire Ecosystemを継承する。
- Adlaire EcosystemはBaaS Projectとしてゼロベースで再スタートする。
- `v0.001`で計画する中核機能はDeployment、Realtime Databaseのみ。
- 契約方式は従来型ではなく、Deployment Systemを軸にしたAdlaire独自方式として扱う。
- Authentication、Authorization、その他BaaS機能は未定義とし、Adlaire独自方式を確定するまで実装しない。
- SQLite / libSQL方針を優先する。

## 許可ディレクトリ

作成・維持できるディレクトリは次のみ。

- `Core/`
- `Applications/`
- `docs/`
- `tests/`

現行構成は上記ディレクトリに集約する。

## Coreファイル

`Core/`は次の3ファイルで構成する。

- `Project.php`
- `Deployment.php`
- `Database.php`

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

仕様が未定義の領域は先に`ADLAIRE-ECOSYSTEM.md`へ追記してから実装する。

## テスト

変更後は次を実行する。

```sh
php tests/debug.php
```
