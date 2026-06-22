# AGENTS.md

このリポジトリで作業するエージェントは、変更前に必ず`docs/AGENTS.md`と`docs/ADLAIRE-ECOSYSTEM.md`を確認する。

このファイルは、作業エージェントの最高準拠ドキュメントであり、作業ルール、承認プロセス、構成制約、編集制約、実行前確認の正本である。

## 正本

- `docs/AGENTS.md`は作業エージェントの最高準拠ドキュメントである。
- 作業ルール、承認プロセス、構成制約、編集制約、実行前確認は`docs/AGENTS.md`を正とする。
- `docs/ADLAIRE-ECOSYSTEM.md`は仕様の最高準拠ドキュメントである。
- 仕様判断、Core仕様、機能仕様、禁止仕様は`docs/ADLAIRE-ECOSYSTEM.md`を正とする。
- READMEは`docs/README.md`に置き、外部向けの簡潔なプロジェクト説明のみとする。READMEは内部入口にしない。
- 詳細仕様、判断根拠、リリース条件は`docs/ADLAIRE-ECOSYSTEM.md`へ集約する。
- 作業ルールは`docs/AGENTS.md`へ集約する。
- テスト関連の補助ドキュメントは`docs/testing.md`へ集約する。
- 仕様と実装が異なる場合は、仕様を正として実装を修正する。
- 実装は確定仕様に厳格に従う。
- 仕様に明記されていない機能、挙動、境界、ファイル、依存関係は実装しない。
- 実装中に仕様不足が判明した場合は実装を止め、仕様確定案、仕様確定承認、バージョン計画承認、実装承認の順に戻す。
- Adlaireに関わる全てのプロジェクトは外部依存を認めないことを原則とする。
- 外部依存が必要に見える場合でも、まずAdlaire独自設計で代替する。
- 仕様で明示的に正選定された基盤を除き、外部サービス、外部同期、外部API、外部SDKへの依存を前提にしない。

## ドキュメント役割

| File | Role | 禁止 |
|------|------|------|
| `docs/AGENTS.md` | 作業エージェントの最高準拠、作業ルール、承認プロセス、構成制約 | 仕様詳細の重複 |
| `docs/ADLAIRE-ECOSYSTEM.md` | 仕様の最高準拠、判断根拠、リリース条件 | 作業手順の重複 |
| `docs/testing.md` | テスト方針、公式テスト入口、テスト範囲の集約 | バージョン計画の記載 |
| `docs/version-plan.md` | バージョン計画承認とバグ修正要約の正本 | テスト関係の記載 |
| `docs/README.md` | 外部向けの簡潔なプロジェクト説明 | 内部入口、詳細仕様、作業ルール |

ドキュメント修正時は、同じ内容を複数ファイルへ詳細重複させない。`docs/ADLAIRE-ECOSYSTEM.md`を仕様正本、`docs/AGENTS.md`を作業ルール正本、`docs/testing.md`をテスト正本として扱う。

現行仕様と異なる古い記載を残さない。過去バージョンの計画、承認、バグ修正要約は`docs/version-plan.md`へ集約し、現行仕様書では現行バージョンを正として記載する。

## 最高絶対原則

最高準拠ドキュメントを読まない時点で、実行プロセスは強制停止する。

作業開始前に必ず次を確認する。

- `docs/AGENTS.md`
- `docs/ADLAIRE-ECOSYSTEM.md`

この確認は、提案、仕様確定案、バージョン計画、実装、修正、削除、テスト、リリース判定の全てに先行する。

## 最高準拠ルール

この承認規則は、今後追加する運用方針ではなく、元々存在する最上位前提ルールである。

すべての工程は、ユーザー承認を得てから進める。承認前の仕様確定、実装、リリース判定は禁止する。未承認の作業は実行してはならない。

必須順序は次の通り。

1. 最高準拠ドキュメント確認
2. 仕様確定案
3. 仕様確定承認
4. バージョン計画承認
5. 実装承認
6. 実装
7. バグ修正
8. テスト
9. リリース判定承認
10. リリース判定

草案は仕様確定案として提示する。草案は、承認前の仕様確定案であり、仕様確定承認の対象である。提案案と仕様案を分ける無駄な承認プロセスは設けない。

仕様確定は、仕様確定案に対する明示的な仕様確定承認を得るまで行わない。仕様確定承認後、実装承認の前に、明示的なバージョン計画承認を必ず得る。設計案と設計承認の工程は設けない。仕様確定承認、バージョン計画承認、実装承認は別工程であり、いずれかの承認を別工程の承認として扱ってはならない。

バージョン計画は`docs/version-plan.md`へ集約する。バージョン計画ファイルの記載は、要点のみを簡潔に明記する。テスト関係はバージョン計画に含めない。バグ修正はバグ修正後にまとめて記載する。

承認前の仕様確定、バージョン計画記載、実装、修正、削除、追加、リリース判定は禁止する。

承認範囲外の追加実装、先行実装、ついで実装は禁止する。仕様外実装、仕様未記載の拡張、確定仕様から逸脱した実装は禁止する。

バグ修正とテストは承認工程に含めない。実装後とバグ修正後は、追加承認を待たずにバグ修正ゼロと公式テストを必ず行う。

テスト関係は`docs/testing.md`に集約する。`docs/AGENTS.md`にはテスト詳細を記載しない。`docs/version-plan.md`にはテスト関係を記載しない。

外部依存を認めないことはAdlaire全体の最高準拠である。外部依存、外部同期、外部サービス前提の機能は、仕様確定承認、バージョン計画承認、実装承認を得るまで扱わない。remote syncのような外部同期前提の概念は採用せず、差分追跡、状態再構築、競合検出、復旧はRealtime DatabaseのEvent Log、Cursor、Snapshot、Replay、Export/Restoreで扱う。

## 現行方針

- 現行バージョンは`v0.021`。
- 名称はAdlaire Ecosystemを継承する。
- Adlaire EcosystemはBaaS Projectとしてゼロベースで再スタートする。
- 必須動作要件は`docs/ADLAIRE-ECOSYSTEM.md`を正とする。
- 現行で維持する中核機能はRealtime Database、Event Log、Authentication / Authorization。
- Deployment Systemは基本方針からやり直すため、現行仕様とソースコードを破棄済みとして扱う。
- Runtimeは廃止し、Runtime代替カテゴリは作らない。
- Authentication / AuthorizationはBaaS Core機能として扱う。
- Realtime DatabaseのDatabaseはSQLiteを正選定し、libSQLはSQLite互換の将来拡張として決定済みとする。ただし`v0.019`ではlibSQLを実装しない。

## 許可ディレクトリ

作成・維持できるディレクトリは次のみ。

- `Core/`
- `Applications/`
- `Docker/`
- `docs/`
- `tests/`

現行構成は上記ディレクトリに集約する。

## Core構成

`Core/`直下は共通基盤機能とエントリポイントの2機能で扱う。エントリポイントは単一ファイル原則で扱う。Event Logも単一ファイル原則で扱う。

- `Core/Database.php`
- `Core/EventLog.php`
- `Core/Auth.php`

`Core/EventLog.php`はRealtime Database、Authentication、Authorizationに共通するCore横断履歴基盤であり、エントリポイントではない。Event Log用フォルダは作成しない。

Core直下に置く境界フォルダは次の3つとする。

- `Database/`
- `Auth/`
- `Deployment/`

内部フォルダにはエントリポイントを置かない。内部フォルダ内のPHPファイルは内部実装のみとし、外部から直接参照しない。`Core/Deployment/`は境界フォルダのみとし、PHPファイルを置かない。

`Core/Database/`内のPHPファイルは次の3ファイルのみとする。

- `DatabaseCore.php`
- `DatabaseStorage.php`
- `DatabaseOperations.php`

`Core/Auth/`内のPHPファイルは次の3ファイルのみとする。

- `AuthCore.php`
- `AuthStorage.php`
- `AuthOperations.php`

Project境界は作成しない。名称、version、manifest、readiness、release summaryはDeployment Systemへ統合しない。

## Docker

- `Docker/`はDocker関連ファイルの境界として維持する。
- 今後のDockerfile、compose、Docker用スクリプト、Docker用設定は`Docker/`へ格納する。
- 初期状態では`Docker/.gitkeep`のみを置く。

## Applications

- `Applications/`はApplication Modulesの境界として維持する。
- Application ModulesはCore外のアプリケーション層として扱う。
- 初期状態では`Applications/.gitkeep`のみを置く。

## テスト

変更後は次を実行する。

```sh
php tests/debug.php
```
