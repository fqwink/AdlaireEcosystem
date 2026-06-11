# AGENTS.md

このリポジトリで作業するエージェントは、必ず本ファイルと`adlaire-ecosystem.md`を確認してから変更する。

## 正本

- 仕様の正本は`adlaire-ecosystem.md`。
- READMEは簡易説明のみ。詳細仕様や判断根拠をREADMEへ重複記載しない。
- 仕様と実装が異なる場合は、仕様を正として実装を修正する。

## 最高絶対原則

1. 仕様に基づく実装。
2. 各フレームワーク5ファイル原則。

Core / Backend / Frontend / CSS / JavaScriptは各5ファイル構成を維持する。増減が必要な場合は、最高絶対原則の変更として扱う。

## 開発順序

すべての変更は次の順序で進める。

1. 仕様策定
2. 実装計画
3. 実装
4. テスト
5. ドキュメント整合

仕様が未定義の領域は実装しない。

## 現行方針

- 現行バージョンは`v0.277`。
- リポジトリ全体を互換性なしの破壊的変更前提で開発する。
- 過去バージョンとの互換性維持を目的にしたshim、alias、旧入口は追加しない。
- 互換性よりも現行仕様、単純さ、安定リリース判定を優先する。
- Public APIは廃止。復活させない。
- API内部依存も避ける。
- 設定ファイルはフレームワーク全体で禁止。
- JSONは監査、履歴、証跡、ログ、内部libSQL transport payload用途のみ許可。
- MySQL対応予定なし。
- SQLiteと内部libSQL transportを軸にする。
- Xserverは本番同等検証プロファイルであり、フレームワーク前提ではない。
- Docker関連ファイルは`Docker/`配下へ集約する。

## Deployment Core

- Deployment Systemはこのフレームワークの中核であり、Coreとして扱う。
- 正式入口は`Core/Deployment.php`。
- rootの`DeploymentCore.php`互換入口は廃止済み。復活させない。
- `Frameworks/Deployment/`は廃止済み。復活させない。
- `DeploymentCore`ディレクトリも作成しない。
- Deployment Coreも互換性保証なしの破壊的変更を前提にする。

## Application Modules

- Application Modulesは`Applications/`配下に置く。
- CMS、EC、静的生成ジェネレーター、Wikiなどのアプリ機能層を指す。
- Application ModulesはDeployment Coreへ依存させない。
- Deploymentは配置・実行基盤、Application Modulesはアプリ機能として分離する。
- 1 Applicationは原則5ファイル構成にする。

## テスト

変更後は原則として次を実行する。

```sh
sh scripts/release-check.sh
```

Dockerで検証する場合:

```sh
docker run --rm -v "$PWD:/app" -w /app php:8.3-cli sh scripts/release-check.sh
```

リリース判定では、少なくとも以下を維持する。

- `php -d phar.readonly=0 tests/debug.php`が成功する。
- `scripts/xserver-profile-audit.sh`が成功する。
- 各フレームワークの5ファイル原則が成功する。
- root `DeploymentCore.php`が存在しない。
- `FrameworkCore/`が存在しない。

## 禁止事項

- `.env*`, `*.ini`, `*.conf`, `*.yaml`, `*.yml`, `config.php`, `*.config.php`を追加しない。
- Composer必須化をしない。
- Public API、JSON response helper、JSON request helper、CORS helperを復活させない。
- root `DeploymentCore.php`互換shimを復活させない。
- 互換性維持用のshim、legacy alias、旧APIラッパーを追加しない。
- 5ファイル原則を満たさない状態でリリース可能にしない。
