# Adlaire Ecosystem

## 概要

Adlaire Ecosystemは、デプロイメントシステムを軸とした軽量PHPバックエンドフレームワークである。複数のフレームワークで構成されたフレームワークでありながら、単体フレームワークとして使用可能にした設計思想を取り入れる。

デプロイメントシステムは分散型自律システム設計思想に基づく。その他の構成要素は、仕様制約内で一定の汎用性を持つフレームワーク機能として扱う。v0.229はRepository-Wide Specification-First Workflowとして、仕様策定、実装計画、実装の順序をリポジトリ全体へ適用した。v0.230はRepository Documentation Consistencyとして、Xserver / DB / 設定ファイル禁止 / 公開API廃止方針のドキュメント整合性をリリース検査へ統合した。v0.231はDeployment Axis Mapとして、リポジトリ全体をデプロイメントシステム軸の役割分類へ固定した。v0.232はDashboard Deploy Execution Specificationとして、ダッシュボード実行を既定OFFかつ安全ゲート必須の将来機能として仕様化した。v0.233からv0.270までは、分類別フレームワークとIntegration Coreへの大規模再編フェーズとし、v0.234はIntegration Core Conceptとして分類間連携Coreの責務を固定する。v0.270を安定版リリースとする。JSONは設定ファイルではなく、監査、履歴、リリース証跡、ログ、内部DB transport payloadなどの内部成果物用途に限り残す。

今後のAdlaire Ecosystemは、デプロイメントシステムを中核互換領域として扱う。`DeploymentCore.php`、デプロイマニフェスト、デプロイ準備判定、ロールバック契約は破壊的変更を行わず、安定版リリース時点でも互換性を維持する。一方で、デプロイメントシステム以外のFramework Core、モジュール、補助機能、ダッシュボードは互換性保証を行わず、現行ドキュメント仕様、公式デバッグテスト、リリース要件検証、配布マニフェストの一致を安定版条件とする。

将来的にAuris（`https://github.com/fqwink/Auris`）のシステムと統合する。ただし、Adlaire Ecosystemのリポジトリは独立したフレームワークリポジトリとして維持する。統合後、Aurisは独立システムとしては廃止し、Aurisというシステム名称を残したままAdlaire Ecosystem内のモジュールとして扱う。統合仕様が正式に明文化されるまで、本ドキュメントをAdlaire Ecosystem側の唯一の仕様根拠とする。

---

## ライセンスと開発参加方針

Adlaire Ecosystemはオープンソースとして公開する。通常の閲覧・利用・参照および商用利用はオープンソースライセンスに基づく。ただし、再配布および改変は商用利用ライセンスの対象とし、再配布条件、改変条件、表示義務、禁止事項はAdlaire Ecosystemの商用利用ライセンスに従う。クラウド事業での使用は、商用利用ライセンス・オープンソースライセンスのいずれにおいても禁止する。

本フレームワークはオープンコントリビューションではない。ソースコードが公開されていても、誰でも開発に参加できることを意味しない。開発参加、仕様変更、実装変更、公式リポジトリへの反映、リリース判断は、プロジェクトが認めた開発主体のみが行える。

| 項目 | 方針 |
|------|------|
| ソース公開 | オープンソースとして公開 |
| 通常利用 | オープンソースライセンス |
| 再配布 | 商用利用ライセンス |
| 改変 | 商用利用ライセンス |
| 商用利用 | オープンソースライセンス |
| クラウド事業利用 | 商用利用ライセンス・オープンソースライセンスを問わず禁止 |
| 開発参加 | オープンコントリビューションではない |
| 変更権限 | プロジェクトが認めた開発主体のみ |
| 公式反映 | 仕様・監査・リリース条件を満たす変更のみ |
| 判断基準 | 最高絶対原則であるドキュメント仕様を最上位とする |

### 配布境界

| 項目 | 方針 |
|------|------|
| 公式配布 | 公式配布元による配布のみを公式版として扱う |
| 非公式配布 | 公式版と名乗ることを禁止 |
| 再配布 | 商用利用ライセンス対象 |
| 改変版配布 | 商用利用ライセンス対象 |
| 公式名称 | 非公式配布・非承認改変版での公式名称使用を禁止 |

### クラウド事業禁止境界

クラウド事業での使用は禁止する。禁止はオープンソースライセンス・商用利用ライセンスの両方に適用し、ライセンス種別による例外を認めない。

| 禁止対象 | 内容 |
|----------|------|
| SaaS | Software as a Serviceとしての提供 |
| PaaS | Platform as a Serviceとしての提供 |
| DBaaS | Database as a Serviceとしての提供 |
| ホスティング基盤 | 第三者向けホスティング基盤での提供 |
| 管理型実行環境 | 管理型ランタイム・実行基盤としての提供 |
| クラウドインフラ事業 | クラウドインフラサービスの構成要素としての提供 |

---

## 最高絶対原則

> 仕様に基づく実装

Adlaire Ecosystemにおける最高絶対原則は、**ドキュメントに定義された仕様に基づいて実装すること**である。

仕様は絶対であり、設計・実装・修正・テスト・デバッグ・ドキュメント更新のすべてにおいて最上位の判断基準とする。仕様外の動作・拡張・妥協・例外解釈を一切許容しない。

仕様と実装に差異がある場合は、仕様を正とし、実装を仕様へ一致させる。仕様が未定義の領域は実装しない。追加実装が必要な場合は、先に仕様を明文化してから実装する。

### 明記ルール

開発は必ず次の順序で進める。

| 順序 | 必須工程 | 内容 |
|------|----------|------|
| 1 | 仕様策定 | 変更対象、制約、禁止事項、受入条件をドキュメントへ明記する |
| 2 | 実装計画 | 仕様に基づき、変更ファイル、検証方法、リリースゲート接続を計画する |
| 3 | 実装 | 仕様と実装計画に従ってコード・テスト・ドキュメントを変更する |

仕様策定前の実装、実装計画前の実装、仕様外の実装は許可しない。バグ修正、機能改良、テスト追加、デバッグ、ドキュメント更新、リリース作業のすべてにこの順序を適用する。

この開発順序はリポジトリ全体に適用する。対象は`DeploymentCore.php`、`FrameworkCore/`、`public_html/`、`scripts/`、`tests/`、`storage/`、`Dockerfile.xserver`、`docker-compose.xserver.yml`、`adlaire-ecosystem.md`を含み、例外パスは設けない。

## 絶対原則

> デプロイメントシステム限定の分散型自律システム設計思想と、仕様定義アーキテクチャに基づく汎用フレームワーク
>
> 分散型自律システム設計思想を適用する対象は、デプロイメントシステムのみとする。フレームワーク本体、Kernel、Extension、Database、Logger、Config、Middleware、Support、Aurisを含むモジュールは、汎用性フレームワークとして扱い、ドキュメント仕様に定めたアーキテクチャを設計思想とする。現時点でアーキテクチャは変更せず、仕様が設計の唯一の根拠であり、仕様外の動作・拡張・妥協を一切許容しない。

---

## コア原則

| 原則 | 詳細 |
|------|------|
| **デプロイメントシステム軸** | v0.51以降、フレームワークの中核軸をデプロイメントシステムとする |
| **Deployment Core** | デプロイメントシステムはルート配置の`DeploymentCore.php`単一ファイルCoreとし、Deployment Core用フォルダは作成しない |
| **デプロイメント分散型自律設計** | `DeploymentCore.php`を中心とするデプロイメントシステムに分散型自律システム設計思想を適用する |
| **分散型自律設計の適用範囲** | 分散型自律システム設計思想はデプロイメントシステムのみへ適用し、フレームワーク本体・モジュールへは適用しない |
| **Framework Core** | 汎用性フレームワークは`FrameworkCore/`を中核ディレクトリとする |
| **汎用フレームワーク範囲** | Core / Kernel / Extension / Database / Logger / Config / Middleware / Supportは、仕様制約内で一定の汎用性を持つフレームワーク機能として扱う |
| **仕様定義アーキテクチャ** | フレームワーク本体とモジュールは、ドキュメント仕様に定めたアーキテクチャを設計思想とする |
| **アーキテクチャ不変** | v0.51以降、現行アーキテクチャを変更しない。v0.130以降は10ファイル原則とマイクロカーネル構成を維持する |
| **Auris将来統合** | 将来的にAuris（`https://github.com/fqwink/Auris`）のシステムと統合する |
| **リポジトリ維持** | Auris統合後もAdlaire Ecosystemのリポジトリは独立したフレームワークリポジトリとして維持する |
| **Auris独立システム廃止** | Auris統合後、Aurisは独立システムとしては廃止する |
| **Aurisモジュール化** | Aurisというシステム名称を残し、Adlaire Ecosystem内の`Auris`モジュールとして扱う |
| **10ファイル原則** | `DeploymentCore.php` + `FrameworkCore/Core.php` `FrameworkCore/Kernel.php` `FrameworkCore/Extension.php` `FrameworkCore/Database.php` `FrameworkCore/Logger.php` `FrameworkCore/Config.php` `FrameworkCore/Middleware.php` `FrameworkCore/Support.php` + 予備1ファイルで構成 |
| **汎用機能** | 設定リポジトリ、ミドルウェアパイプライン、Routerミドルウェア、配列/文字列サポートヘルパーを提供する |
| **外部依存ゼロ** | サードパーティライブラリ・Composer一切不要。公開APIは廃止し、libSQL APIはDatabase層の内部DB transportに限定する |
| **PHP 8.3以降** | 起動時にバージョンチェック、8.2以前は即時エラー終了 |
| **フロントエンド機能なし** | フロントエンド系の機能は一切実装しない |
| **複合フレームワーク構成** | 複数のフレームワーク的責務を持つ構成要素で成り立つが、利用時は単体フレームワークとして扱える |
| **マイクロカーネル** | `Kernel.php`がサービス管理と拡張登録を担い、`Extension.php`が拡張契約を定義する |
| **分散型自律性** | デプロイメントシステムにのみ適用する。その他の構成要素は仕様で統合される汎用フレームワーク機能として扱う |
| **特化型** | 用途・対象環境は非公開 |
| **厳格** | 曖昧な入力・設定を許容しない。型・ルールに反した場合は即時エラー |
| **高速** | 不要な処理を排除、最小限のオーバーヘッドで動作 |
| **段階的拡張** | 確定仕様をベースに改良・新規機能を追加していく |
| **累積バージョン** | 新機能・機能改良・バグ修正・ビルド・テスト・ドキュメント更新など作業種別に関係なく、すべて`v0.x`形式の累積バージョンとして扱う |
| **非オープンコントリビューション** | 公開ソースであっても、誰でも開発参加できる方式ではない。公式開発は承認された開発主体に限定する |

---

## ディレクトリ構造

Adlaire Ecosystemのディレクトリ構造は、ルート配置のDeployment Core、Framework Core、Modules、Testsの4領域で構成する。ディレクトリ構造は仕様の一部であり、実装都合で任意に変更してはならない。

```text
AdlaireEcosystem/
├── FrameworkCore/
│   ├── Core.php
│   ├── Kernel.php
│   ├── Extension.php
│   ├── Database.php
│   ├── Logger.php
│   ├── Config.php
│   ├── Middleware.php
│   └── Support.php
├── modules/
│   └── Auris/
│       └── .gitkeep
├── tests/
│   └── debug.php
├── DeploymentCore.php
└── adlaire-ecosystem.md
```

### Deployment Core

Deployment Coreはフォルダを作成しない。デプロイメントシステムはルート配置の`DeploymentCore.php`単一ファイルCoreとして扱い、分散型自律システム設計思想の適用対象はこの単一ファイルCoreに限定する。

v0.202時点では、`DeploymentCore.php`をルートの公開エントリポイントかつDeployment Core本体として維持する。Deployment Core用ディレクトリを作成してはならない。将来の物理移動または分割を行う場合は、先に仕様へ明記し、公式テストとリリース要件を満たす必要がある。

| 項目 | 方針 |
|------|------|
| 中核ディレクトリ | なし |
| 論理Core名 | Deployment Core |
| 対象 | デプロイメントシステム |
| 現行コンポーネント | `DeploymentCore.php` |
| 配置 | ルート配置 |
| ファイル原則 | 単一ファイル原則 |
| 設計思想 | 分散型自律システム設計思想 |
| リリース要件 | `DeploymentCore.php`をルート公開エントリポイントとして維持 |

### Framework Core

Framework Coreは`FrameworkCore/`を中核ディレクトリとする。汎用性フレームワークはFramework Coreとして扱い、ドキュメント仕様に定めたアーキテクチャを設計思想とする。分散型自律システム設計思想はFramework Coreへ適用しない。

v0.202時点では、デプロイメントシステム以外の汎用性フレームワークを`FrameworkCore/`へ物理的に集約する。`FrameworkCore/Core.php`、`FrameworkCore/Kernel.php`、`FrameworkCore/Extension.php`、`FrameworkCore/Database.php`、`FrameworkCore/Logger.php`、`FrameworkCore/Config.php`、`FrameworkCore/Middleware.php`、`FrameworkCore/Support.php`をFramework Coreの構成要素として扱う。

| 項目 | 方針 |
|------|------|
| 中核ディレクトリ | `FrameworkCore/` |
| 論理Core名 | Framework Core |
| 対象 | 汎用性フレームワーク |
| 現行コンポーネント | `FrameworkCore/Core.php` `FrameworkCore/Kernel.php` `FrameworkCore/Extension.php` `FrameworkCore/Database.php` `FrameworkCore/Logger.php` `FrameworkCore/Config.php` `FrameworkCore/Middleware.php` `FrameworkCore/Support.php` |
| 設計思想 | 仕様定義アーキテクチャ |
| 分散型自律システム設計思想 | 適用しない |
| リリース要件 | 汎用性フレームワークは`FrameworkCore/`配下を公開配置とする |

### Modules

Modulesは`modules/`を中核ディレクトリとする。各モジュールは`modules/{ModuleName}/`配下に配置する。Aurisは`modules/Auris/`を公式統合モジュール候補ディレクトリとする。

モジュールはFramework Coreと同じく仕様定義アーキテクチャに従う。分散型自律システム設計思想は適用しない。

### Tests

Testsは`tests/`を中核ディレクトリとする。公式デバッグテストは`tests/debug.php`であり、リリース判定の受入条件として扱う。

### 本番同等テスト環境

本番環境はXserverレンタルサーバとする。ローカルテスト環境はDocker上の`php:8.3-apache`を基盤とし、Xserverレンタルサーバ相当プロファイルを監査することで本番同等テストとして扱う。

本番同等テストは、外部サービスへ接続せず、リポジトリ内で再現可能な条件のみを公式リリースゲートに含める。Xserver上での実機確認が必要な項目は、実機デプロイ前チェックリストとして扱い、ローカル公式デバッグテストではプロファイル整合性を検証する。

| 項目 | 方針 |
|------|------|
| 本番環境 | Xserverレンタルサーバ |
| ローカル同等環境 | Docker `php:8.3-apache` + Xserver互換プロファイル監査 |
| PHP要件 | PHP 8.3以上、PHP 8.3.x互換プロファイル |
| Webサーバ想定 | Apache互換の共有レンタルサーバ |
| ドキュメントルート | `public_html` |
| `.htaccess` | public_html運用で必須 |
| Composer | 必須としない |
| 外部依存 | 公式テストでは接続不要 |
| DB | SQLite / libSQL APIを正式軸とする。MySQL対応予定なし。libSQL APIは公開APIではなく内部DB transportとして扱う |
| デプロイ | `DeploymentCore.php`ルート配置、`FrameworkCore/`配下集約、Deployment Core用ディレクトリ禁止 |
| 公式検証 | `php_lint` / `official_debug_test` / `xserver_profile_audit` / `git_diff_check` |

---

## モジュール仕様

### 0. モジュール設計方針

モジュールは分散型自律システム設計思想の適用対象ではない。モジュールは、仕様定義アーキテクチャに基づく汎用フレームワーク構成要素として扱う。

`AutonomousModule`という既存契約名は、現行仕様上のモジュール名として残す。ただし、この名称はモジュール契約名であり、分散型自律システム設計思想をフレームワーク本体またはモジュールへ適用することを意味しない。

| 項目 | 方針 |
|------|------|
| 設計思想 | 仕様定義アーキテクチャ |
| 分散型自律システム設計思想 | 適用しない |
| 配置単位 | モジュール単位でディレクトリを作成する |
| 配置形式 | `modules/{ModuleName}/` |
| ファイル原則 | 3ファイル原則、5ファイル原則、7ファイル原則のいずれかを適用する |
| 既定原則 | 最小構成は3ファイル原則とする |
| 登録境界 | Kernel経由 |
| 通信境界 | Kernelの`send()`または仕様化されたmessage handler経由 |
| 公式モジュール | manifest、health、policy整合性を必須とする |
| Auris | Auris名称を保持した公式統合モジュール候補として扱う |

#### モジュールファイル原則

モジュールは、フレームワーク本体の10ファイル原則とは別に、各モジュールディレクトリ内で3ファイル原則、5ファイル原則、7ファイル原則のいずれかを選択する。

| 原則 | 用途 | 必須構成 |
|------|------|----------|
| 3ファイル原則 | 小規模・単機能モジュール | module本体、manifest、test |
| 5ファイル原則 | 標準モジュール | module本体、manifest、config、policy、test |
| 7ファイル原則 | 大規模・公式統合モジュール | module本体、manifest、config、policy、service、support、test |

#### モジュールディレクトリ原則

各モジュールは`modules/{ModuleName}/`配下に配置する。Aurisは`modules/Auris/`を公式統合モジュール候補ディレクトリとする。

モジュールディレクトリの作成は、モジュールの仕様化と同時に行う。仕様が未定義のモジュールディレクトリを作成してはならない。

### 1. ルーティング

| 項目 | 詳細 |
|------|------|
| HTTPメソッド | GET / POST / PUT / PATCH / DELETE |
| ルートグループ | プレフィックス対応 |
| エラーハンドリング | 404 / 405 |
| 名前付きルート | `name()` でルートに名前付け、URL生成に使用 |
| パラメータ制約 | `where()` で正規表現によるパラメータ制限 |
| RESTリソース | `resource()` でCRUDルートを一括登録 |
| **静的ルート最適化** | パラメータなしルートをハッシュマップで管理し、O(1)で照合。パラメータありルートとの二段階ディスパッチ（v0.4以降） |
| **パターンキャッシュ** | ルート登録時（`addRoute()`）にPCREパターンをコンパイル・キャッシュ。毎リクエストでの再コンパイルを排除（v0.4以降） |

### 2. リクエスト

| 項目 | 詳細 |
|------|------|
| メソッド取得 | `GET` `POST` `PUT` `PATCH` `DELETE` |
| URI取得 | リクエストURIの取得 |
| ヘッダー取得 | 任意のヘッダー取得 |
| ボディパース | `multipart/form-data` / `application/x-www-form-urlencoded`。JSONリクエスト補助はv0.226で廃止 |
| クエリパラメータ | 取得対応 |
| IPアドレス | クライアントIP取得。**信頼プロキシIPホワイトリスト**対応（未設定時は`REMOTE_ADDR`のみ使用）（v0.4以降） |
| ファイルアップロード | `$_FILES` のラップ・バリデーション |
| Bearerトークン取得 | Authorizationヘッダーからトークン抽出 |
| ルートパラメータ取得 | `/users/{id}` の `id` 取得 |

### 3. レスポンス

| 項目 | 詳細 |
|------|------|
| ステータスコード | 任意のHTTPステータスコード設定 |
| ヘッダー設定 | 任意のレスポンスヘッダー設定 |
| リダイレクト | 301 / 302 / **307 / 308**（307/308はv0.5以降） |
| キャッシュヘッダー | `Cache-Control` / `ETag` 設定 |
| エラーレスポンス | `text/plain`形式のエラー返却。JSONレスポンス補助はv0.226で廃止 |

### 4. バリデーション

| ルール | 詳細 |
|--------|------|
| `required` | 必須チェック |
| 型チェック | `string` / `int` / `float` / `bool` / `array` |
| `min` / `max` | 最小・最大値チェック |
| `regex` | 正規表現チェック |
| `email` | メールアドレス形式チェック |
| `url` | URL形式チェック |
| `uuid` | UUID形式チェック |
| `date` | 日付形式チェック |
| `in` | 指定値リスト内チェック |
| `unique` | 一意性チェック。**`Database`インスタンスを`Adlaire::validate()`の第4引数で注入可能**（v0.4以降）。未指定時は`Database::default()`を使用 |
| エラー構造化 | バリデーションエラーの構造化返却 |
| カスタムルール | クロージャで独自ルール定義 |
| ネスト配列 | `items.*.name` 形式のドット記法 |
| 条件付き | `required_if` / `nullable` |
| カスタムメッセージ | ルールごとにエラーメッセージ上書き |
| バリデーションチェーン | 複数ルールの優先順位制御 |
| **`int`型の厳格性** | `int`ルールは文字列数値（`"123"`）を許容する。厳格な整数型チェックには`strict_int`ルールを使用（v0.4以降） |

### 5. データベース

| 項目 | 詳細 |
|------|------|
| エンジン | SQLite系 / libSQL API。ローカルファイル / インメモリ / libSQL HTTPS API / libSQL WebSocket fallback |
| クエリビルダー | SELECT（カラム指定・`*`）/ WHERE（`where()` / `orWhere()` / `whereIn()`）/ JOIN（`join()` / `leftJoin()`）/ ORDER BY / LIMIT / OFFSET / INSERT（単行・複数行）/ UPDATE / DELETE / 集計（`count()` / `sum()` / `avg()` / `min()` / `max()`）|
| **RAWクエリ** | `selectRaw(string $expression, array $bindings = [])` / `whereRaw(string $expression, array $bindings = [])` で任意のSQL式を記述可能。バインディングはプレースホルダー経由で渡す。SQLインジェクションの責任は呼び出し側が負う（v0.4以降） |
| **テーブルエイリアス** | `users AS u` 形式および `u.id` 形式の識別子を許容（v0.4以降） |
| **全件UPDATE/DELETE保護** | WHEREなしの`update()` / `delete()`は`RuntimeException`を投げる。全件操作が必要な場合は`allowWithoutWhere()`を明示的にチェーンして許可（v0.4以降） |
| サブクエリ | クエリビルダー内でのサブクエリ使用 |
| UNION | `union()` / `unionAll()` 対応 |
| Eager Loading | N+1問題対策。関連データを一括取得 |
| 複数DB接続 | 複数のDB接続を設定値で定義・切り替え可能。`connect()`は単一デフォルト接続の簡易設定用。`addConnection()`は名前付き複数接続の管理用。両者の混在時は`RuntimeException`（v0.4以降）。v0.6で`addConnection()`に`$url`・`token`引数を追加 |
| クエリログ・スロークエリ検知 | 実行クエリをログに記録。設定値で有効・無効・閾値を制御。閾値超過クエリは警告ログに記録。**最大件数上限**を設定可能（デフォルト: 1000件。超過時は古いエントリから破棄）（上限設定はv0.4以降） |
| プリペアドステートメント | SQLインジェクション対策 |
| トランザクション | データ整合性の確保 |
| マイグレーション | `up()` / `down()`・実行管理テーブル・タイムスタンプ順実行・`migrate` / `rollback` コマンド。**ファイル命名規則**: `YYYYMMDD_HHMMSS_description.php` 形式を強制。違反ファイルは読み込み時に例外。**`rollback()`**: 指定ステップ数が実行済みマイグレーション数を超える場合は例外（命名規則・rollback拡張はv0.4以降） |
| **ページネーション** | `QueryBuilder::paginate(int $perPage, int $page = 1)`。戻り値: `data` / `total` / `per_page` / `current_page` / `last_page`（v0.5以降） |

### 6. デプロイメント（DeploymentCore.php）

#### 動作フロー

**初回インストール**

| ステップ | 内容 |
|---------|------|
| 1 | `DeploymentCore.php` を対象サーバーに手動設置 |
| 2 | 設定値を設定（設定管理強化参照） |
| 3 | 初回実行：リポジトリから全ファイルを取得・配置 |

**自律アップデートサイクル**

| ステップ | 内容 |
|---------|------|
| 1 | 起動トリガー（cron or HTTP呼び出し） |
| 2 | GitHub SSH + `git archive` でtar形式取得・展開（Gitコマンド・Git連携口はケースバイケースで併用可。`git pull` 除く） |
| 3 | ローカルファイルとハッシュ比較・差分リスト生成 |
| 4 | 差分ファイルをバックアップ後に適用（`DeploymentCore.php` 自身も対象） |
| 5 | 失敗時はロールバック実行 |

#### 運用強化

| 機能 | 詳細 |
|------|------|
| ドライラン | 実際に適用せず差分のみ確認。変更対象ファイルリストをログに記録 |
| デプロイ履歴管理 | デプロイごとにスナップショットを保存。保持世代数は設定値で指定。日時・コミットSHA・変更ファイル一覧を記録 |
| ロックファイル | 実行中にロックファイルを生成し二重実行を防止。終了時・タイムアウト時に自動解放（ゾンビロック防止） |
| **ファイルキャッシュ** | `diff()`・`healthCheck()`間でファイルリストをキャッシュ。同一デプロイ内での重複ウォークを排除（v0.4以降） |
| **ロールバック完全性** | スナップショット保存時に`manifest.json`（デプロイ前のファイル一覧）を記録。ロールバック時に新規追加ファイルを`manifest.json`との差分で特定・削除し、デプロイ前の状態に完全復元（v0.4以降） |

#### セキュリティ強化

| 機能 | 詳細 |
|------|------|
| HMAC署名検証 | HTTPトリガー経由の実行時にSHA-256によるHMAC署名を検証。失敗時は即時エラー終了・ログ記録 |
| ログ改ざん検知 | ログファイル全体のHMACを `.log.hmac` として別ファイルに保存。次回起動時に検証。失敗時の続行可否は設定で制御。**`hmac_key`未設定時はWARNINGレベルのログを出力**（v0.4以降） |
| 実行元IPホワイトリスト | HTTPトリガー時に実行元IPを検証。ホワイトリストは設定値で指定。未設定時は全IP拒否 |
| デプロイ対象ホワイトリスト | デプロイ対象ファイルを明示的に指定。ホワイトリスト外のファイルは取得・適用しない |
| 設定値暗号化 | SSHキーパス等の機密設定値を暗号化して保持。復号は実行時のみ |
| **HTTPトリガー判定** | PHP実行モードが`cli`および`cli-server`の場合はHTTPトリガー検証をスキップ。それ以外は常にHMAC署名・IP検証を適用（v0.4以降） |

#### 信頼性強化

| 機能 | 詳細 |
|------|------|
| ヘルスチェック | デプロイ前にPHPファイルの構文検証（`PHP_BINARY`による`php -l`必須。`PHP_BINARY`が未検出の場合は例外を投げてデプロイを中断）（v0.4以降）。デプロイ後にエンドポイントへHTTPリクエストで動作確認。失敗時は即時中断・ロールバック |
| タイムアウト制御 | `git archive` 取得の最大待機時間を設定値で指定。超過時は即時中断・エラー終了 |
| パーミッション自動修正 | ファイル配置後に設定値で指定したパーミッションを自動適用 |
| メンテナンスモード | デプロイ中に503レスポンスを返す。デプロイ完了後に自動解除 |

#### 設定管理強化

| 機能 | 詳細 |
|------|------|
| 設定値バリデーション | 起動時に全設定値・`exec()`有効性・SSHキーパーミッション（600）・**`phar.readonly`が`Off`であること**を検証。不正・無効・未設定の場合は即時エラー終了（`phar.readonly`チェックはv0.4以降） |
| 環境変数対応 | 設定値を環境変数から取得可能。環境変数を設定値より優先 |

#### ログ

v0.5以降は`Logger.php`に集約済み。`DeploymentCore.php`は共通`Logger`を直接利用する。

| 機能 | 詳細 |
|------|------|
| ログレベル | DEBUG / INFO / WARNING / ERROR の4段階。出力レベルは設定値で制御 |
| ログローテーション | サイズ超過で自動ローテーション。保持世代数は設定値で指定 |
| 構造化ログ | JSON形式での出力に対応 |

#### 先送り機能

> 仕様確定まで実装しない。将来的に検討対象。rsyncは永久禁止。

| 機能 | 理由 |
|------|------|
| デプロイ通知（HTTPコールバック） | 仕様未確定 |
| 自前配布サーバー対応 | 現段階は対象外 |
| 自前パッケージレジストリ対応 | 現段階は対象外 |

### 7. ログ（Logger.php）

デプロイ・アプリケーション・デバッグログを統合する共通ログ基盤。

| 項目 | 詳細 |
|------|------|
| 出力形式 | JSON |
| ログレベル | DEBUG / INFO / WARNING / ERROR |
| ローテーション | サイズ超過時に世代管理つきで自動ローテーション |
| HMAC | `hmac_key`設定時にログファイルのHMACを `.hmac` として保存・検証 |
| マスク | `Authorization`ヘッダー、設定で指定した機密フィールドをマスク |
| デバッグ記録 | `APP_ENV=development`時のみリクエスト・レスポンス・クエリ・ルーティング情報を記録 |
| 本番動作 | 本番環境ではデバッグ記録を行わない |

---

## グローバル仕様

| 項目 | 詳細 |
|------|------|
| **未捕捉例外ハンドラ** | `Adlaire::init()`内で`set_exception_handler()`を設定。未捕捉例外を`text/plain`で返却し、本番環境では詳細を隠蔽する |

---

## ファイル構成

```
DeploymentCore.php              # デプロイメントCore
FrameworkCore/Core.php          # フレームワーク本体
FrameworkCore/Kernel.php        # マイクロカーネル（サービス管理・拡張登録）
FrameworkCore/Extension.php     # 拡張契約
FrameworkCore/Database.php      # データベース
FrameworkCore/Logger.php        # ログ基盤（デプロイ・アプリ・デバッグログ統合）
FrameworkCore/Config.php        # 設定リポジトリ
FrameworkCore/Middleware.php    # ミドルウェアパイプライン
FrameworkCore/Support.php       # 汎用サポートヘルパー
（予備）                       # 未使用1枠
```

---

## バージョン

### バージョン形式

Adlaire Ecosystemのバージョンは、作業種別に関係なく`v0.x`形式の累積バージョンとして策定する。新機能、機能改良、バグ修正、ビルド、テスト、デバッグ、仕様形式化、ドキュメント更新のいずれであっても、独立した分類バージョンを作らず、次の累積バージョンへ統合する。

バージョン番号は`v0.10`から`v0.202`のように単調増加させる。各バージョンは、それ以前の全仕様・修正・検証結果を含む累積状態を表す。過去バージョンの修正を後続バージョンへ含める場合も、後続バージョンを累積版として扱い、別系統のパッチ番号や作業種別番号を作らない。

| 原則 | 内容 |
|------|------|
| 累積管理 | すべての変更は次の`v0.x`へ累積する |
| 種別非依存 | 新機能・改良・バグ修正・ビルド・テスト・ドキュメント更新を同一形式で扱う |
| 単調増加 | バージョン番号は後戻りせず、`v0.10`から`v0.202`のように順に進める |
| 仕様優先 | バージョン内容は仕様書に明記された内容を正とする |
| 回帰継承 | 後続バージョンは過去バージョンの回帰テストと受入条件を継承する |

| バージョン | 内容 |
|-----------|------|
| v0.1 | 初期リリース：ルーティング・リクエスト・レスポンス・バリデーション |
| v0.2 | 仕様準拠開発：ルートパラメータ・名前付きルート・制約・RESTリソース・フォーム/ファイル/Bearer・レスポンス補助・高度バリデーション・SQLiteデータベース基盤 |
| v0.3 | DeploymentCore.php追加・バリデーション拡張・DB拡張 |
| v0.4 | **実装済み**（バグ修正・セキュリティ・信頼性・パフォーマンス・堅牢性・機能追加） |
| v0.5 | **実装済み**（`Logger.php`新設・ページネーション・リダイレクト拡張。共通JSONレスポンス補助はv0.226で廃止） |
| v0.6 | **実装済み**（libSQL対応。v0.226以降は公開APIではなく内部DB transportとして強化） |
| v0.7 | **実装済み**（実運用堅牢化：テスト基盤・Logger強化・Database堅牢化・Core運用機能・Deployer安全性強化） |
| v0.8 | **実装済み**（開発者体験強化：設定取得・テスト補助・デバッグ出力・DB補助・ルーティング確認） |
| v0.9 | **実装済み**（セキュリティ監査・仕様固定：セキュリティヘッダー・機密マスク・パス境界・監査テスト） |
| v0.10 | **実装済み**（安定版：バージョン固定・公開契約一覧・監査メタ情報・最終回帰テスト） |
| v0.11 | **実装済み**（形式化Phase 1：仕様ID・受入条件・テスト対応表・変更方針） |
| v0.12 | **実装済み**（形式化Phase 2：仕様監査・リリース要件マトリクス・リリース判定・回帰ゼロ基準） |
| v0.13 | **実装済み**（ライセンス・利用制限・公式統治の形式化：ライセンス方針・禁止用途・統治方針・公式版判定） |
| v0.14 | **実装済み**（公式メタ情報と配布境界の形式化：配布ポリシー・クラウド事業境界・公式メタ情報） |
| v0.15 | **実装済み**（仕様・監査・公式性の自己検証強化：仕様整合性・リリース判定連携） |
| v0.16 | **実装済み**（運用監査ログの形式化：監査イベントログ・Logger連携） |
| v0.17 | **実装済み**（仕様ドリフト検出：未対応仕様ID・監査キー・リリース判定チェックの検出） |
| v0.18 | **実装済み**（公式配布マニフェスト：配布物ファイル・公開契約・方針・リリース条件のマニフェスト化） |
| v0.19 | **実装済み**（マイクロカーネル導入・7ファイル原則：Kernel.php・Extension.php・拡張登録・サービス管理） |
| v0.20 | **実装済み**（拡張ライフサイクル形式化：依存関係・状態・拡張情報） |
| v0.21 | **実装済み**（拡張イベントバス：イベント登録・emit・payload連携） |
| v0.22 | **実装済み**（拡張設定スキーマ：設定検証・型制約・設定取得） |
| v0.23 | **実装済み**（拡張サンドボックス境界：許可サービス・未許可拒否） |
| v0.24 | **実装済み**（拡張監査マニフェスト：拡張状態・サービス・モジュール一覧） |
| v0.25 | **実装済み**（自律モジュール定義：AutonomousModule契約・モジュール登録） |
| v0.26 | **実装済み**（モジュール間メッセージング：send / handle） |
| v0.27 | **実装済み**（自律ヘルスチェック：Kernel / Adlaire health report） |
| v0.28 | **実装済み**（ポリシーエンジン：policyDecisionによるallow / deny判定） |
| v0.29 | **実装済み**（自律監査レポート：autonomousAuditReport） |
| v0.30 | **実装済み**（安定化版：stabilityContract・現行仕様検証） |
| v0.31-v0.35 | **実装済み**（権限・隔離・セキュリティ固定：Capability/Isolation方針を安定化契約へ統合） |
| v0.36-v0.40 | **実装済み**（分散実行・同期・復旧：分散自律性契約を安定化契約へ統合） |
| v0.41 | **実装済み**（公式拡張レジストリ仕様：officialExtensionRegistry） |
| v0.42 | **実装済み**（拡張署名メタ情報：extensionSignatureMetadata） |
| v0.43 | **実装済み**（リリースプロファイル：releaseProfiles） |
| v0.44 | **実装済み**（公式移行ポリシー：migrationPolicy） |
| v0.45 | **実装済み**（エコシステム監査レポート：ecosystemAuditReport） |
| v0.46 | **実装済み**（長期サポート方針：supportPolicy） |
| v0.47 | **実装済み**（セキュリティ修正プロトコル：securityFixProtocol） |
| v0.48 | **実装済み**（互換性なし方針：noCompatibilityPolicy） |
| v0.49 | **実装済み**（リリース凍結ポリシー：releaseFreezePolicy） |
| v0.50 | **長期安定版**（longTermStabilityContract：長期安定・Deployment Core互換維持・非デプロイ領域の互換性保証なし・公式テスト必須） |
| v0.51 | **実装済み**（デプロイメントシステム軸への方針変更：デプロイメント分散型自律設計・その他汎用フレームワーク・Auris将来統合・リポジトリ維持・アーキテクチャ不変） |
| v0.52 | **実装済み**（Auris統合後方針：Auris独立システム廃止・Auris名称保持・Aurisモジュール化） |
| v0.53 | **実装済み**（Aurisモジュール実体化：AurisModule・基本モジュールメッセージ・Kernel登録検証） |
| v0.54 | **実装済み**（Aurisモジュール監査強化：Auris manifest・policy validation・整合性検証） |
| v0.130 | **実装済み**（汎用フレームワーク強化版：Request補助・Responseヘッダー一括設定・Router middleware・Validator比較ルール・Support補助） |
| v0.200 | **安定版リリース**（バックエンドフレームワーク安定版：stableReleaseContract・typed Request/Config・Middleware一括登録・Support文字列補助・Docker公式デバッグ検証） |
| v0.202 | **実装済み**（Deployment Core / Framework Core構造固定、Xserverレンタルサーバ本番同等テストプロファイル追加、配布マニフェスト更新） |
| v0.203 | **実装済み**（SQLite / libSQL API Runtime Hardening：SQLite PRAGMA既定値、`Database::fromConfig()`、libSQL API timeout/retry/token/consistency、MySQL対応予定なし） |
| v0.204 | **実装済み**（Runtime Operations Hardening：`Adlaire::health()`、`Adlaire::configAudit()`、`scripts/release-check.sh`、リリース効率化ポリシー、プロバイダ非依存の運用診断） |
| v0.205 | **実装済み**（Operations Dashboard：`Adlaire::dashboardPolicy()`、`public_html/dashboard.php`、認証必須・読み取り専用・HTML操作画面） |
| v0.206 | **実装済み**（Configuration File Prohibition：`.env` / `.ini` / `.conf` / `.yaml`設定ファイル禁止、JSONメタデータ例外、`.env.xserver.example`削除） |
| v0.207 | **実装済み**（Deployment Preflight Guard：`Deployer::preflight()`、`Adlaire::deploymentPreflightPolicy()`、Deployment Core互換維持） |
| v0.208 | **実装済み**（Deployment Plan Preview：`Deployer::planPreview()`、added / modified / unchanged / skipped分類、Deployment Core変更検出） |
| v0.209 | **実装済み**（Deployment Compatibility Snapshot：`Deployer::compatibilitySnapshot()`、Deployment Core互換性証跡、preflight / plan preview証跡統合） |
| v0.210 | **実装済み**（Deployment Rollback Preview：`Deployer::rollbackPreview()`、restore / remove / missing分類） |
| v0.211 | **実装済み**（Deployment Safety Score：`Deployer::deploymentSafetyScore()`、互換性・rollback・preview証跡から安全スコア算出） |
| v0.212 | **実装済み**（Dashboard Control Visibility：実行ではなく制御情報をHTMLで可視化） |
| v0.213 | **実装済み**（Deployment History Visualization：`Deployer::deploymentHistorySummary()`、履歴サマリ可視化） |
| v0.214 | **実装済み**（Deployment Control Report：`Deployer::deploymentControlReport()`、制御情報統合レポート） |
| v0.215 | **実装済み**（Stable Release Gate：リリース準備とデプロイ安全性の統合判定） |
| v0.216 | **実装済み**（Adlaire UI Framework：`public_html/assets/adlaire-ui.css`、ダッシュボード表示基盤、設定ファイルなし） |
| v0.217 | **実装済み**（Deployment Control Snapshot：`Deployer::recordDeploymentControlSnapshot()`、JSONL監査成果物保存） |
| v0.218 | **実装済み**（Deployment Safety Score Details：`Deployer::deploymentSafetyScoreDetails()`、減点理由と重要度） |
| v0.219 | **実装済み**（Rollback State Preview：`Deployer::rollbackStatePreview()`、rollback後の想定状態） |
| v0.220 | **実装済み**（Dashboard Release Gate View：release gate / RC status / safety score表示方針） |
| v0.221 | **実装済み**（Deployment Timeline View：preflightからrelease gateまでの制御イベント順序） |
| v0.222 | **実装済み**（Adlaire UI Framework Expansion：table / badge / details / section / status layout） |
| v0.223 | **実装済み**（Release Evidence Bundle：`Deployer::releaseEvidenceBundle()`、リリース証跡統合） |
| v0.224 | **実装済み**（Deployment Control Diff：`Deployer::deploymentControlDiff()`、前回証跡との差分） |
| v0.225 | **実装済み**（Stable Release Candidate Gate：`Deployer::stableReleaseCandidateGate()`、RC判定） |
| v0.226 | **実装済み**（API Removal：公開API、JSONレスポンス、JSONリクエスト補助、CORS補助を完全廃止） |
| v0.227 | **実装済み**（libSQL API Hardening：内部DB transport、timeout / retry / token / consistency / test transport強化） |
| v0.228 | **実装済み**（Specification-First Development Workflow：仕様策定、実装計画、実装の順序を最高絶対原則として固定） |
| v0.229 | **実装済み**（Repository-Wide Specification-First Workflow：開発順序をリポジトリ全体へ適用、例外パスなし） |
| v0.230 | **実装済み**（Repository Documentation Consistency：Xserver / MySQL / 設定ファイル禁止 / 公開API廃止方針のドキュメント整合性をリリース検査へ統合） |
| v0.231 | **実装済み**（Deployment Axis Map：リポジトリ全体をDeployment Core / Deployment Control UI / Framework Support / Verification / Specificationへ分類） |
| v0.232 | **実装済み**（Dashboard Deploy Execution Specification：任意デプロイ実行を既定OFF・安全ゲート必須の将来機能として仕様化） |
| v0.233 | **実装済み**（Framework Classification Specification：Deployment / Backend / Frontend / CSS / JavaScript / Integration Coreを正式分類、v0.270安定版目標を固定） |
| v0.234 | **実装済み**（Integration Core Concept：分類別フレームワークを登録、ライフサイクル、依存関係、監査、リリース判定、デプロイ制御へ接続するCore責務を固定） |
| v0.270 | **安定版リリース予定**（Reorganized Framework Stable Release：分類別フレームワーク + Integration Core構成の安定版） |

---

## v0.4 実装済み仕様

### Phase 1 — バグ修正

| # | ファイル | 対象 | 内容 |
|---|---------|------|------|
| 1-1 | `Database.php` | `QueryBuilder::update()` | WHEREなし全件更新を`RuntimeException`で阻止。全件許可は`allowWithoutWhere()`を明示チェーン |
| 1-2 | `Database.php` | `QueryBuilder::delete()` | WHEREなし全件削除を`RuntimeException`で阻止。全件許可は`allowWithoutWhere()`を明示チェーン |
| 1-3 | `DeploymentCore.php` | `Deployer::backup()` | スナップショット保存時に`manifest.json`（デプロイ前ファイル一覧）を記録 |
| 1-4 | `DeploymentCore.php` | `Deployer::rollbackLatest()` | `manifest.json`との差分で新規追加ファイルを特定・削除。完全復元を保証 |
| 1-5 | `DeploymentCore.php` | `Deployer::healthCheck()` | `PHP_BINARY`未検出時は`token_get_all()`フォールバックを使わず即時例外 |
| 1-6 | `DeploymentCore.php` | `Deployer::verifyHttpTrigger()` | `cli-server`を`cli`と同様にHTTPトリガー検証スキップ対象へ明示追加 |

### Phase 2 — セキュリティ・信頼性

| # | ファイル | 対象 | 内容 |
|---|---------|------|------|
| 2-1 | `Core.php` | `Request::parseIp()` | 信頼プロキシIPホワイトリスト対応。`Adlaire::init()`に`trustedProxies`設定を追加。未設定時は`REMOTE_ADDR`のみ使用 |
| 2-2 | `DeploymentCore.php` | `DeployConfig::validate()` | `phar.readonly`が`On`の場合は即時エラー終了 |
| 2-3 | `Logger.php` | `Logger::__construct()` | `hmacKey`未設定時にWARNINGレベルのログを出力。`DeploymentCore.php`からも共通利用 |
| 2-4 | `Core.php` | `Adlaire::init()` | `set_exception_handler()`を設定。未捕捉例外はv0.226以降`text/plain`で返却。`APP_ENV`環境変数で本番/開発を切り替え |

### Phase 3 — パフォーマンス

| # | ファイル | 対象 | 内容 |
|---|---------|------|------|
| 3-1 | `Core.php` | `Router::addRoute()` | ルート登録時にPCREパターンをコンパイル・キャッシュ。`$routes`に`pattern`・`paramNames`を保持 |
| 3-2 | `Core.php` | `Router::dispatch()` | パラメータなし静的ルートをハッシュマップ（`method#uri`をキー）で先引き。O(1)照合後にパラメータありルートへフォールバック |
| 3-3 | `Core.php` | `Request::parseBody()` | コンストラクタでの即時実行を廃止。`body()`/`input()`初回呼び出し時に遅延パース |
| 3-4 | `DeploymentCore.php` | `Deployer::files()` | `$this->fileCache`にキャッシュ。`diff()`・`healthCheck()`で共有 |
| 3-5 | `Database.php` | `Database::enableQueryLog()` | `$maxEntries`引数を追加（デフォルト: 1000）。超過時は古いエントリから破棄 |

### Phase 4 — 堅牢性・機能追加

| # | ファイル | 対象 | 内容 |
|---|---------|------|------|
| 4-1 | `Database.php` | `QueryBuilder::assertIdentifier()` | `users AS u`・`u.id`形式を許容するようパターンを拡張 |
| 4-2 | `Core.php` | `Validator::validateUnique()` | `Adlaire::validate()`の第4引数で`Database`インスタンスを注入可能に。未指定時は`Database::default()`にフォールバック |
| 4-3 | `Core.php` | `Validator` | `strict_int`ルールを追加。文字列数値（`"123"`）を拒否。既存`int`ルールの挙動は変えない |
| 4-4 | `Database.php` | `QueryBuilder::selectRaw()` / `whereRaw()` | `selectRaw(string $expression, array $bindings = [])` / `whereRaw(string $expression, array $bindings = [])` を追加。識別子バリデーションをスキップ。インジェクション責任は呼び出し側 |
| 4-5 | `Database.php` | `Migrator::pendingMigrations()` | ファイル名が`YYYYMMDD_HHMMSS_description.php`形式に違反する場合は`InvalidArgumentException` |
| 4-6 | `Database.php` | `Migrator::rollback()` | 指定ステップ数が実行済みマイグレーション数を超える場合は`RuntimeException` |
| 4-7 | `Database.php` | `Database::connect()` / `addConnection()` | `connect()`をデフォルト単一接続専用に制限。`addConnection()`との混在時は`RuntimeException` |

---

## v0.5 実装済み仕様

### 新機能 — Logger.php

#### 基本方針

デプロイ・アプリケーション・デバッグのログ出力を`Logger`に集約する。`DeploymentCore.php`は共通`Logger`を直接利用する。リクエスト・レスポンス・クエリ・ルーティング・エラーの情報を1リクエスト1行のJSONログとして記録するデバッグ機能を含む。デバッグ記録は`APP_ENV=development`時のみ有効。本番では一切記録しない。

#### 記録対象（デバッグモード）

**リクエスト**

| 項目 | 内容 |
|------|------|
| メソッド | GET / POST 等 |
| URI | リクエストURI |
| ヘッダー | 全ヘッダー（`Authorization`はマスク） |
| ボディ | JSONボディ（サイズ上限あり。超過時は切り捨て＋フラグ） |
| クエリパラメータ | `$_GET`の内容 |
| IPアドレス | クライアントIP |

**レスポンス**

| 項目 | 内容 |
|------|------|
| ステータスコード | HTTPステータス |
| ヘッダー | 送出したレスポンスヘッダー |
| 処理時間 | リクエスト受信〜レスポンス送出までの時間（ms） |
| ピークメモリ | `memory_get_peak_usage()` |

**クエリ**

| 項目 | 内容 |
|------|------|
| 実行SQL | プレースホルダー形式 |
| バインディング | バインド値一覧 |
| 実行時間 | クエリごとの処理時間（ms） |
| スロークエリフラグ | 閾値超過時に`true` |

**ルーティング**

| 項目 | 内容 |
|------|------|
| マッチしたルート | パス・メソッド・名前 |
| ルートパラメータ | 抽出されたパラメータ |
| マッチ失敗 | 404/405の場合はその旨を記録 |

**エラー**

| 項目 | 内容 |
|------|------|
| 未捕捉例外 | クラス・メッセージ・スタックトレース |
| バリデーションエラー | フィールド・ルール・メッセージ |

#### ログ仕様

| 項目 | 内容 |
|------|------|
| 出力形式 | JSON |
| 出力先 | 設定値で指定したファイルパス |
| ローテーション | サイズ超過で自動ローテーション。保持世代数は設定値で指定 |
| マスク対象 | `Authorization`ヘッダー・設定で指定したフィールド名（`password`等） |
| ボディサイズ上限 | 設定値で指定（デフォルト: 4096バイト） |
| デバッグ記録の有効条件 | `APP_ENV=development`時のみ。本番では一切記録しない |

#### 実装方針

| 項目 | 内容 |
|------|------|
| クラス名 | `Logger` |
| ファイル | `Logger.php`（予備枠1を使用） |
| 統合箇所 | `Adlaire::init()`で初期化。`Adlaire::run()`のdispatch前後でデバッグ情報を収集 |
| DB連携 | `Database::enableQueryLog()`と連携。dispatch後にクエリログを取り込む |
| Deployer連携 | `DeploymentCore.php`は`Logger`を直接利用 |
| 独立性 | `Core.php`・`Database.php`への変更は最小限。`Logger`が各クラスから情報を取得する設計 |

### 追加機能

#### ページネーション（Database.php）

`limit()`・`offset()`の延長。ページ番号からLIMIT/OFFSETを自動計算する。

| 項目 | 内容 |
|------|------|
| メソッド | `QueryBuilder::paginate(int $perPage, int $page = 1)` |
| 戻り値 | `data` / `total` / `per_page` / `current_page` / `last_page` |
| 内部実装 | `count()`で総件数取得後、LIMIT/OFFSETを自動計算して`get()` |

#### リダイレクト拡張（Core.php）

`Response::redirect()`に307/308を追加し、4種のリダイレクトに対応する。

| 項目 | 内容 |
|------|------|
| `Response::redirect()` | 307 / 308 を許容ステータスに追加。301/302と合わせて4種対応 |

---

## v0.6 実装済み仕様

### SQLite / libSQL API接続仕様（Database.php）

#### 現行基本方針

`Database.php`はSQLite（PDO）とlibSQL APIを公式DB接続方式とする。v0.226のAPI Removalは公開HTTP/JSON APIを廃止する方針であり、Database層の内部libSQL API transportは対象外とする。

#### 接続方式

| 方式 | 接続文字列形式 | ドライバ | 外部依存 |
|------|--------------|---------|---------|
| ローカルファイル | `file:path/to/db.sqlite`（v0.6で新形式追加。既存のパス直指定も継続サポート） | `pdo_sqlite` | なし |
| インメモリ | `:memory:` | `pdo_sqlite` | なし |
| libSQL API | `https://db.example.com` / `libsql://db.example.com` | `LibSqlApiDriver` | live接続時のみcurl拡張 |
| libSQL WebSocket fallback | `wss://db.example.com` | `LibSqlWebSocketDriver`（HTTPS API fallback） | live接続時のみcurl拡張 |

#### libSQL API強化

| 項目 | 内容 |
|------|------|
| timeout | `timeout_seconds`で設定可能。既定30秒 |
| retry | `retries`で設定可能。`0`以上 |
| token | `token_required`で必須化可能。BearerトークンはDB接続専用 |
| consistency | `strong` / `eventual` を選択可能 |
| test transport | `transport` callableにより外部通信なしで公式テスト可能 |
| 公開API境界 | libSQL APIは内部DB transportであり、公開API・JSONレスポンス補助・CORS補助を復活させない |

#### 外部依存原則の扱い

コア原則「外部依存ゼロ」に合わせ、Composerやサードパーティライブラリは使用しない。liveのlibSQL API接続時のみPHP標準拡張としてのcurlを利用する。公式テストは`transport` callableを使い外部サービスへ接続しない。

#### 接続設計

| 項目 | 内容 |
|------|------|
| 接続設定 | `Database::addConnection(string $name, string $url, bool $default, ?string $token, array $options = [])`。`token`引数はlibSQL API接続用 |
| 方式自動判定 | `file:`・`:memory:`・スキームなしパスはPDO SQLite、`https:` / `libsql:` / `wss:` は内部libSQL API transport |
| クエリ実行 | `QueryBuilder`はそのまま使用。接続方式の差異は`Database`内部で吸収 |
| トランザクション | SQLite / libSQL APIの双方でSAVEPOINTによるネストトランザクションを維持 |
| クエリログ | 接続方式にかかわらず既存の`enableQueryLog()`で統一管理 |
| 設定連携 | `Database::fromConfig()`で配列または`ConfigRepository`から接続を登録 |
| ランタイム監査 | `Database::runtimeProfile()`でSQLite / libSQL APIの適用設定を確認 |

#### 実装方針
| 追加クラス | `DatabaseDriver`インターフェースを`Database.php`内に定義。`PdoDriver` / `LibSqlApiDriver` / `LibSqlWebSocketDriver`で実装 |
| `QueryBuilder`への影響 | なし。`Database`がドライバの差異を吸収する設計 |
| 公開APIへの影響 | なし。public entry、Response補助、Request補助は復活させない |

---

## v0.7 実装済み仕様

### 基本方針

v0.7は実運用堅牢化リリースとする。新規ファイルは追加せず、既存の`Core.php`・`Database.php`・`DeploymentCore.php`・`Logger.php`・`tests/debug.php`を対象に、運用時の安全性・観測性・テスト性を強化する。

### Phase 1 — テスト基盤の正式化

| # | ファイル | 対象 | 内容 |
|---|---------|------|------|
| 1-1 | `tests/debug.php` | テスト分類 | Request / Validator / Router / Database / Logger / Deployer のカテゴリを明確化 |
| 1-2 | `tests/debug.php` | 境界テスト | クエリログ上限、マイグレーション命名、書き込みガード、Loggerローテーション、Deployer失敗時ロールバックを検証 |
| 1-3 | `adlaire-ecosystem.md` | テスト仕様 | Docker上で`php -d phar.readonly=0 tests/debug.php`を公式デバッグテストとして定義 |
| 1-4 | `tests/debug.php` | 終了条件 | 全テスト成功時のみ`OK`を出力する |

### Phase 2 — Logger強化

| # | ファイル | 対象 | 内容 |
|---|---------|------|------|
| 2-1 | `Logger.php` | `request_id` | デバッグログ1行ごとに`request_id`を記録。未指定時は自動生成 |
| 2-2 | `Logger.php` | `component` | ログ出力元を`core` / `database` / `deployer` / `logger`として記録可能にする |
| 2-3 | `Logger.php` | HMAC警告 | `hmac_key`未設定時のWARNINGはインスタンスごとに1回のみ出力 |
| 2-4 | `Logger.php` | ローテーション | `keep`世代を超える古いログを残さない |
| 2-5 | `Core.php` | Logger連携 | `Adlaire::init()`のlogger設定で`request_id`を受け付ける |

### Phase 3 — Database堅牢化

| # | ファイル | 対象 | 内容 |
|---|---------|------|------|
| 3-1 | `Database.php` | `QueryBuilder::whereNull()` | `IS NULL`条件を追加 |
| 3-2 | `Database.php` | `QueryBuilder::whereNotNull()` | `IS NOT NULL`条件を追加 |
| 3-3 | `Database.php` | `QueryBuilder::whereBetween()` | `BETWEEN ? AND ?`条件を追加 |
| 3-4 | `Database.php` | `QueryBuilder::exists()` | 条件に一致する行の存在確認を追加 |
| 3-5 | `Database.php` | `QueryBuilder::insertGetId()` | INSERT後の主キーID取得を追加。PDO接続以外では`RuntimeException` |
| 3-6 | `Database.php` | `QueryBuilder::paginate()` | 既存の`limit` / `offset`状態を破壊しない |

### Phase 4 — Core運用機能

| # | ファイル | 対象 | 内容 |
|---|---------|------|------|
| 4-1 | `Core.php` | OPTIONS | OPTIONSメソッドをルーティング対象へ追加。CORS補助はv0.226で廃止 |
| 4-2 | `Core.php` | 405 | 405応答時の`Allow`ヘッダーにOPTIONSを含める |
| 4-3 | `Core.php` | `Adlaire::init()` | `trustedProxies` / `logger`設定項目を公式設定として扱う |
| 4-4 | `Core.php` | Logger連携 | 404 / 405 のルーティング失敗情報をLoggerへ渡す |

### Phase 5 — Deployer安全性強化

| # | ファイル | 対象 | 内容 |
|---|---------|------|------|
| 5-1 | `DeploymentCore.php` | 失敗時rollback | 今回のデプロイでスナップショット作成後に失敗した場合のみロールバックする |
| 5-2 | `DeploymentCore.php` | 初回デプロイ | `target_dir`未作成時は空ターゲットとして扱い、バックアップmanifestを作成する |
| 5-3 | `DeploymentCore.php` | 履歴 | deploy履歴に`phase`と`status`を記録する |
| 5-4 | `DeploymentCore.php` | apply失敗ログ | apply中に失敗したファイル名をログへ記録する |
| 5-5 | `DeploymentCore.php` | allowlist | `deploy_allowlist`未設定時は全ファイル許可、設定時は一致ファイルのみ許可する挙動をテストで固定する |

---

## v0.8 実装済み仕様

### 基本方針

v0.8は開発者体験強化リリースとする。外部依存ゼロと5ファイル原則を維持し、既存契約を壊さずに、設定取得・テスト補助・デバッグ出力・DB補助・ルーティング確認を追加する。

### Phase 1 — Core設定取得

| # | ファイル | 対象 | 内容 |
|---|---------|------|------|
| 1-1 | `Core.php` | `Adlaire::config()` | `Adlaire::init(array $config)`で受け取った設定をドット記法で取得可能にする |
| 1-2 | `Core.php` | `Adlaire::env()` | 環境変数を型変換つきで取得する。`true` / `false` / 数値文字列をPHP型へ変換 |

### Phase 2 — Router確認補助

| # | ファイル | 対象 | 内容 |
|---|---------|------|------|
| 2-1 | `Core.php` | `Router::routes()` | 登録済みルートのメソッド・パス・名前・制約を配列で取得 |
| 2-2 | `Core.php` | `Router::has()` | 名前付きルートの存在確認 |
| 2-3 | `Core.php` | `Router::methodsFor()` | 指定URIに一致するHTTPメソッド一覧を取得 |

### Phase 3 — Response補助

| # | ファイル | 対象 | 内容 |
|---|---------|------|------|
| 3-1 | `Core.php` | `Response::noContent()` | 204レスポンスを送出 |
| 3-2 | `Core.php` | `Response::created()` | 201レスポンスを`success()`形式で送出 |

### Phase 4 — Database補助

| # | ファイル | 対象 | 内容 |
|---|---------|------|------|
| 4-1 | `Database.php` | `Database::resetConnectionsForTesting()` | テスト専用で接続状態をリセットする。通常運用では使用禁止 |
| 4-2 | `Database.php` | `QueryBuilder::pluck()` | 単一カラムの値一覧を取得 |
| 4-3 | `Database.php` | `QueryBuilder::value()` | 先頭行の単一カラム値を取得 |

### Phase 5 — Logger / Deployer補助

| # | ファイル | 対象 | 内容 |
|---|---------|------|------|
| 5-1 | `Logger.php` | `Logger::withComponent()` | 同じ出力先・設定を維持したままcomponentだけ変更したLoggerを生成 |
| 5-2 | `DeploymentCore.php` | `Deployer::validateOnly()` | fetch/applyを行わず、設定・ロック・ヘルスチェック前提条件を検証する |
| 5-3 | `tests/debug.php` | v0.8テスト | Core設定、Router確認、DB補助、Logger component派生、Deployer validateOnlyを検証 |

---

## v0.9 実装済み仕様

### 基本方針

v0.9はセキュリティ監査・仕様固定リリースとする。v0.10に向け、公開契約の安全性、ログ出力の機密保護、デプロイ時のファイル境界、監査テストを固定する。新規ファイルは追加しない。

### Phase 1 — Coreセキュリティ

| # | ファイル | 対象 | 内容 |
|---|---------|------|------|
| 1-1 | `Core.php` | `Response::securityHeaders()` | `X-Content-Type-Options` / `X-Frame-Options` / `Referrer-Policy` / `Permissions-Policy`を一括設定 |
| 1-2 | `Core.php` | `Response::securityHeaders()` | 既存ヘッダー設定と同じバリデーションを通す |

### Phase 2 — Logger機密保護

| # | ファイル | 対象 | 内容 |
|---|---------|------|------|
| 2-1 | `Logger.php` | `Logger::maskArray()` | 機密キーは完全一致だけでなく、キー名に`password` / `token` / `secret`を含む場合もマスク |
| 2-2 | `Logger.php` | `Logger::maskHeaders()` | `Authorization`ヘッダーを大文字小文字に関係なくマスク |
| 2-3 | `tests/debug.php` | Logger監査 | ネストした機密フィールドと派生キーがマスクされることを検証 |

### Phase 3 — Deployerパス境界

| # | ファイル | 対象 | 内容 |
|---|---------|------|------|
| 3-1 | `DeploymentCore.php` | デプロイ対象パス | 絶対パス、`..`、NULバイト、空文字をデプロイ対象ファイル名として拒否 |
| 3-2 | `DeploymentCore.php` | `diff()` / `backup()` / `apply()` / `rollbackSnapshot()` | ファイル操作前に相対パス検証を必ず通す |
| 3-3 | `DeploymentCore.php` | `allowed()` | allowlist判定前に相対パス検証を通す |
| 3-4 | `tests/debug.php` | Deployer監査 | 不正パスが拒否されることを検証 |

### Phase 4 — 仕様固定テスト

| # | ファイル | 対象 | 内容 |
|---|---------|------|------|
| 4-1 | `tests/debug.php` | セキュリティ監査 | Responseセキュリティヘッダー、Logger機密マスク、Deployerパス境界を検証 |
| 4-2 | `tests/debug.php` | 回帰条件 | v0.9までの全デバッグテストが成功した場合のみ`OK`を出力 |

---

## v0.10 実装済み仕様

### 基本方針

v0.10は安定版リリースとする。v0.9までの仕様を整理し、バージョン表記・公開契約・監査メタ情報・最終回帰テストを整備する。v0.206以降はデプロイメントシステムのみ互換性を維持し、それ以外の領域は互換性保証を行わない。

### Phase 1 — バージョン固定

| # | ファイル | 対象 | 内容 |
|---|---------|------|------|
| 1-1 | `Core.php` | `ADLAIRE_VERSION` | フレームワークバージョン定数を`0.10.0`として定義 |
| 1-2 | `Database.php` / `DeploymentCore.php` / `Logger.php` | DocBlock | `@version 0.10.0`へ更新 |
| 1-3 | `Core.php` | `Adlaire::version()` | 現在のバージョン文字列を返す |

### Phase 2 — 公開契約一覧

| # | ファイル | 対象 | 内容 |
|---|---------|------|------|
| 2-1 | `Core.php` | 公開契約 | Core / Database / Deployer / Logger の公開契約をドキュメントで固定 |
| 2-2 | `adlaire-ecosystem.md` | 公開契約 | v0.10時点の公開契約固定方針を明記 |

#### 公開契約固定方針

v0.10時点の公開契約はドキュメントで固定する。v0.206以降は公開向けの機械可読な入口一覧を廃止し、リポジトリ内部のリリース判定は公開契約メタデータへ依存しない。

### Phase 3 — 監査メタ情報

| # | ファイル | 対象 | 内容 |
|---|---------|------|------|
| 3-1 | `Core.php` | `Adlaire::audit()` | バージョン、PHP要件、ファイル原則、外部依存原則、公式テストコマンドを返す |
| 3-2 | `tests/debug.php` | 監査テスト | `Adlaire::version()` / `audit()` を検証 |

### Phase 4 — 最終回帰

| # | ファイル | 対象 | 内容 |
|---|---------|------|------|
| 4-1 | `tests/debug.php` | 回帰条件 | v0.10までの全デバッグテスト成功時のみ`OK`を出力 |
| 4-2 | `adlaire-ecosystem.md` | 安定版条件 | Docker上の公式デバッグテスト成功をv0.10安定版条件とする |

#### 安定版条件

v0.10安定版の成立条件は、Docker上で以下の公式デバッグテストが成功することとする。

```bash
php -d phar.readonly=0 tests/debug.php
```

---

## v0.11 実装済み仕様

### 基本方針

v0.11は形式化Phase 1リリースとする。v0.10で固定した公開契約と安定版条件を前提に、仕様を実装・テスト・監査へ直接対応できる粒度へ分解する。実装はドキュメント仕様に必ず従い、仕様と実装が矛盾する場合は仕様を最高絶対原則として実装を修正する。バージョンは累積形式に従い`0.11.0`とする。

### Phase 1 — 仕様ID体系

| # | 対象 | 内容 |
|---|------|------|
| 1-1 | `Core.php` | `Adlaire::specificationIds()`でCore / Database / Logger / Deployer / Test / Releaseごとの仕様IDを返す |
| 1-2 | `Core.php` | 仕様IDは`CORE-REQ-*` / `DB-REQ-*` / `LOGGER-REQ-*` / `DEPLOY-REQ-*` / `TEST-REQ-*` / `RELEASE-REQ-*`形式とする |
| 1-3 | `Core.php` | `Adlaire::audit()`に仕様ID一覧を含める |

### Phase 2 — 受入条件

| # | 対象 | 内容 |
|---|------|------|
| 2-1 | `Core.php` | `Adlaire::audit()`に`required_verifications`を含める |
| 2-2 | `Core.php` | 受入条件に`php_lint` / `official_debug_test` / `git_diff_check`を含める |
| 2-3 | `tests/debug.php` | 監査テストで累積バージョン形式・仕様ID・必須検証項目を検証する |

### Phase 3 — テスト対応表

| # | 対象 | 内容 |
|---|------|------|
| 3-1 | `Core.php` | `Adlaire::testSpecificationMap()`でテスト名と仕様IDの対応表を返す |
| 3-2 | `Core.php` | `Adlaire::audit()`にテスト対応表を含める |
| 3-3 | `tests/debug.php` | `adlaire_audit`テストで対応表が監査メタ情報に含まれることを検証する |

### Phase 4 — 変更方針

| # | 対象 | 内容 |
|---|------|------|
| 4-1 | `Core.php` | `Adlaire::audit()`にデプロイメントシステム互換維持と非デプロイ領域の破壊的変更許可を含める |
| 4-2 | `tests/debug.php` | Deployment Core互換維持、非デプロイ領域の互換性保証なし、破壊的変更許可が監査メタ情報に含まれることを検証する |
| 4-3 | 公開契約 | Deployment Core契約は維持し、それ以外のメソッド削除、引数変更、戻り値構造変更は現行仕様と公式テストに従って許可する |

---

## v0.12 実装済み仕様

### 基本方針

v0.12は形式化Phase 2リリースとする。v0.11で作成した仕様ID・受入条件・テスト対応表を監査可能なリリース判定へ発展させる。リリース可否は実装者の主観ではなく、仕様書・テスト結果・リリース要件マトリクス・監査記録に基づいて判断する。バージョンは累積形式に従い`0.12.0`とする。

### Phase 1 — 仕様監査

| # | 対象 | 内容 |
|---|------|------|
| 1-1 | `Core.php` | `Adlaire::audit()`にライセンス方針を含める |
| 1-2 | `Core.php` | `Adlaire::audit()`に非オープンコントリビューション方針を含める |
| 1-3 | `Core.php` | `Adlaire::audit()`に分散型自律性システム設計思想を含める |
| 1-4 | `Core.php` | `Adlaire::audit()`に複合フレームワーク構成と単体フレームワーク利用可能性を含める |
| 1-5 | `tests/debug.php` | `release_readiness`テストで監査メタ情報を検証する |

### Phase 2 — リリース要件マトリクス

| # | 対象 | 内容 |
|---|------|------|
| 2-1 | `Core.php` | `Adlaire::releaseRequirementMatrix()`でPHP 8.3以上のリリース要件を返す |
| 2-2 | `Core.php` | v0.10公開契約をリリース要件マトリクスに含める |
| 2-3 | `Core.php` | v0.11形式化仕様をリリース要件マトリクスに含める |
| 2-4 | `Core.php` | ローカルDocker上の公式デバッグテストを基準環境としてリリース要件マトリクスに含める |
| 2-5 | `Core.php` | 外部依存ゼロ、公開API廃止、内部libSQL API transport強化をリリース要件マトリクスに含める |

### Phase 3 — リリース判定

| # | 対象 | 内容 |
|---|------|------|
| 3-1 | `Core.php` | `Adlaire::releaseReadiness()`でリリース判定を返す |
| 3-2 | `Core.php` | 判定条件に累積バージョン、ライセンス、参加方針、設計思想、リリース要件、Deployment Core互換維持、必須検証を含める |
| 3-3 | `tests/debug.php` | `release_readiness`テストで`ready: true`と全チェック成功を検証する |

### Phase 4 — 回帰ゼロ基準

| # | 対象 | 内容 |
|---|------|------|
| 4-1 | バグ修正 | 修正ごとに関連テストと全体デバッグテストを実行する |
| 4-2 | 回帰 | 既存テストの失敗、仕様IDとの不一致、公開契約破壊を回帰として扱う |
| 4-3 | 完了条件 | Docker上の公式デバッグテストが`OK`を出力し、`Adlaire::releaseReadiness()`が`ready: true`を返すことを完了条件とする |

---

## v0.13 実装済み仕様

### 基本方針

v0.13はライセンス・利用制限・公式統治の形式化リリースとする。v0.12で監査メタ情報に含めたライセンス方針、禁止用途、非オープンコントリビューション、公式版判定を、個別の契約情報として取得可能にし、監査・リリース判定・公式デバッグテストで固定する。バージョンは累積形式に従い`v0.13`とする。

### Phase 1 — ライセンス方針

| # | 対象 | 内容 |
|---|------|------|
| 1-1 | `Core.php` | `Adlaire::licensePolicy()`でライセンス方針を返す |
| 1-2 | `Core.php` | 通常利用と商用利用はオープンソースライセンスとして返す |
| 1-3 | `Core.php` | 再配布と改変は商用利用ライセンスとして返す |
| 1-4 | `Core.php` | `Adlaire::audit()`に`license_policy`として同一内容を含める |

### Phase 2 — 禁止用途

| # | 対象 | 内容 |
|---|------|------|
| 2-1 | `Core.php` | `Adlaire::prohibitedUsePolicy()`で禁止用途方針を返す |
| 2-2 | `Core.php` | クラウド事業利用を禁止として返す |
| 2-3 | `Core.php` | クラウド事業利用禁止はオープンソースライセンス・商用利用ライセンスの両方に適用する |
| 2-4 | `Core.php` | `Adlaire::audit()`に`prohibited_use_policy`として同一内容を含める |

### Phase 3 — 公式統治モデル

| # | 対象 | 内容 |
|---|------|------|
| 3-1 | `Core.php` | `Adlaire::governancePolicy()`で公式統治方針を返す |
| 3-2 | `Core.php` | オープンコントリビューション不可を返す |
| 3-3 | `Core.php` | 仕様変更・実装変更・リリース判断は承認制として返す |
| 3-4 | `Core.php` | 外部パッチの公式採用は保証しない |

### Phase 4 — 公式版判定

| # | 対象 | 内容 |
|---|------|------|
| 4-1 | `Core.php` | `Adlaire::officialReleasePolicy()`で公式版判定方針を返す |
| 4-2 | `Core.php` | 公式版判定に仕様準拠、監査メタ情報一致、公式デバッグテスト、リリース準備完了、承認済み開発主体を含める |
| 4-3 | `Core.php` | `Adlaire::releaseReadiness()`の判定条件に禁止用途方針、統治方針、公式版判定を含める |
| 4-4 | `tests/debug.php` | `license_governance`テストでライセンス、禁止用途、統治、公式版判定を検証する |

---

## v0.14 実装済み仕様

### 基本方針

v0.14は公式メタ情報と配布境界の形式化リリースとする。v0.13で形式化したライセンス、禁止用途、統治、公式版判定を前提に、公式配布、非公式版の名乗り禁止、クラウド事業禁止境界、公式メタ情報を個別の契約情報として取得可能にし、監査・リリース判定・公式デバッグテストで固定する。バージョンは累積形式に従い`v0.14`とする。

### Phase 1 — 公式配布ポリシー

| # | 対象 | 内容 |
|---|------|------|
| 1-1 | `Core.php` | `Adlaire::distributionPolicy()`で公式配布方針を返す |
| 1-2 | `Core.php` | 再配布は商用利用ライセンス対象として返す |
| 1-3 | `Core.php` | 改変版配布は商用利用ライセンス対象として返す |
| 1-4 | `Core.php` | 非公式配布は公式版と名乗れないことを返す |

### Phase 2 — クラウド事業禁止境界

| # | 対象 | 内容 |
|---|------|------|
| 2-1 | `Core.php` | `Adlaire::cloudBusinessBoundary()`でクラウド事業禁止境界を返す |
| 2-2 | `Core.php` | SaaS / PaaS / DBaaS / ホスティング基盤 / 管理型実行環境 / クラウドインフラ事業を禁止対象として返す |
| 2-3 | `Core.php` | クラウド事業禁止はオープンソースライセンス・商用利用ライセンスの両方に適用する |

### Phase 3 — 公式メタ情報

| # | 対象 | 内容 |
|---|------|------|
| 3-1 | `Core.php` | `Adlaire::officialMetadata()`で公式メタ情報を返す |
| 3-2 | `Core.php` | 公式メタ情報にバージョン、公式テストコマンド、公開契約、ライセンス方針、禁止用途方針、統治方針、公式版判定を含める |
| 3-3 | `Core.php` | `Adlaire::audit()`に`distribution_policy` / `cloud_business_boundary` / `official_metadata`を含める |

### Phase 4 — 公式メタ情報テスト

| # | 対象 | 内容 |
|---|------|------|
| 4-1 | `tests/debug.php` | `official_metadata`テストを追加する |
| 4-2 | `tests/debug.php` | 公式配布、非公式版の名乗り禁止、クラウド事業境界、公式メタ情報、監査メタ情報一致を検証する |
| 4-3 | `Core.php` | `Adlaire::releaseReadiness()`の判定条件に配布ポリシー、クラウド事業境界、公式メタ情報を含める |

---

## v0.15 実装済み仕様

### 基本方針

v0.15は仕様・監査・公式性の自己検証強化リリースとする。仕様書、監査出力、公式デバッグテストの三者一致をフレームワーク自身の検証対象に含める。バージョンは累積形式に従い`v0.15`とする。

| # | 対象 | 内容 |
|---|------|------|
| 1-1 | `Core.php` | `Adlaire::specificationIntegrity()`で仕様整合性を返す |
| 1-2 | `Core.php` | バージョン形式、ライセンス方針、クラウド事業禁止、統治方針、配布境界、公式メタ情報を検証する |
| 1-3 | `Core.php` | `Adlaire::audit()`に`specification_integrity`を含める |
| 1-4 | `Core.php` | `Adlaire::releaseReadiness()`に`specification_integrity`チェックを含める |
| 1-5 | `tests/debug.php` | `specification_integrity`テストで全チェック成功を検証する |

---

## v0.16 実装済み仕様

### 基本方針

v0.16は運用監査ログの形式化リリースとする。仕様・公式性・ライセンス・配布境界に関する判断を、既存LoggerのJSONログとして記録可能にする。バージョンは累積形式に従い`v0.16`とする。

| # | 対象 | 内容 |
|---|------|------|
| 1-1 | `Logger.php` | `Logger::auditEvent()`で監査イベントを記録する |
| 1-2 | `Logger.php` | 監査イベントは`component=audit`としてJSONログへ出力する |
| 1-3 | `Logger.php` | 監査イベントのcontextは既存Loggerの機密マスクを継承する |
| 1-4 | `tests/debug.php` | Loggerテストで監査イベント名、audit component、機密マスクを検証する |

---

## v0.17 実装済み仕様

### 基本方針

v0.17は仕様ドリフト検出リリースとする。仕様ID、テスト対応表、監査メタ情報、リリース判定チェックの不一致を検出可能にする。バージョンは累積形式に従い`v0.17`とする。

| # | 対象 | 内容 |
|---|------|------|
| 1-1 | `Core.php` | `Adlaire::specificationDrift()`で仕様ドリフト情報を返す |
| 1-2 | `Core.php` | 未対応仕様ID、未知仕様ID、欠落監査キー、欠落リリース判定チェックを返す |
| 1-3 | `Core.php` | `Adlaire::audit()`に`specification_drift`を含める |
| 1-4 | `Core.php` | `Adlaire::releaseReadiness()`は`drift=false`の場合のみ成功とする |
| 1-5 | `tests/debug.php` | `specification_drift`テストでドリフトなしを検証する |

---

## v0.18 実装済み仕様

### 基本方針

v0.18は公式配布マニフェストリリースとする。配布物そのものに相当する公式メタ情報として、ファイル一覧、公開契約、ライセンス方針、禁止用途方針、配布方針、公式版判定、公式テスト条件を返す。バージョンは累積形式に従い`v0.18`とする。

| # | 対象 | 内容 |
|---|------|------|
| 1-1 | `Core.php` | `Adlaire::distributionManifest()`で公式配布マニフェストを返す |
| 1-2 | `Core.php` | マニフェストに`Core.php` / `Database.php` / `DeploymentCore.php` / `Logger.php` / `tests/debug.php` / `adlaire-ecosystem.md`を含める |
| 1-3 | `Core.php` | マニフェストに公開契約、ライセンス方針、禁止用途方針、配布方針、公式版判定、公式デバッグテストを含める |
| 1-4 | `Core.php` | `Adlaire::audit()`に`distribution_manifest`を含める |
| 1-5 | `Core.php` | `Adlaire::releaseReadiness()`に`distribution_manifest`チェックを含める |
| 1-6 | `tests/debug.php` | `distribution_manifest`テストでファイル一覧、方針一致、リリース準備条件を検証する |

---

## v0.19 実装済み仕様

### 基本方針

v0.19はマイクロカーネルアーキテクチャ導入リリースとする。従来の5ファイル原則を7ファイル原則へ格上げし、`Kernel.php`と`Extension.php`を追加する。`Core.php`は既存の単体フレームワーク利用性を維持しながら、`Adlaire::kernel()`を通じてサービス管理と拡張登録を提供する。バージョンは累積形式に従い`v0.19`とする。

### Phase 1 — 7ファイル原則

| # | 対象 | 内容 |
|---|------|------|
| 1-1 | `adlaire-ecosystem.md` | コア原則を7ファイル原則へ格上げする |
| 1-2 | `Kernel.php` | マイクロカーネル本体を追加する |
| 1-3 | `Extension.php` | 拡張契約を追加する |
| 1-4 | `Core.php` | `ADLAIRE_VERSION`を`v0.19`へ更新する |

### Phase 2 — マイクロカーネル

| # | 対象 | 内容 |
|---|------|------|
| 2-1 | `Kernel.php` | `MicroKernel`でサービス登録、取得、存在確認、サービス一覧取得を提供する |
| 2-2 | `Kernel.php` | `AdlaireExtension`の登録とbootを管理する |
| 2-3 | `Kernel.php` | 同名拡張の重複登録を拒否する |
| 2-4 | `Extension.php` | `name()` / `register()` / `boot()`を拡張契約として定義する |

### Phase 3 — Core統合

| # | 対象 | 内容 |
|---|------|------|
| 3-1 | `Core.php` | `Extension.php`と`Kernel.php`を読み込む |
| 3-2 | `Core.php` | `Adlaire::init()`で`MicroKernel`を初期化する |
| 3-3 | `Core.php` | `router` / `request` / `response` / `logger`をカーネルサービスとして登録する |
| 3-4 | `Core.php` | `Adlaire::kernel()`でカーネルを返す |

### Phase 4 — 監査・テスト

| # | 対象 | 内容 |
|---|------|------|
| 4-1 | `Core.php` | 公開契約に`MicroKernel`と`AdlaireExtension`を含める |
| 4-2 | `Core.php` | `Adlaire::audit()`の`file_principle`を`7 files`へ更新する |
| 4-3 | `Core.php` | `Adlaire::distributionManifest()`に`Kernel.php`と`Extension.php`を含める |
| 4-4 | `tests/debug.php` | `microkernel`テストでサービス管理、拡張登録、boot、重複登録拒否を検証する |

---

## v0.20 実装済み仕様

v0.20は拡張ライフサイクル形式化リリースとする。`MicroKernel::requires()`と`extensionInfo()`で依存関係、登録状態、boot状態、設定検証結果、許可サービスを確認可能にする。

| # | 対象 | 内容 |
|---|------|------|
| 1-1 | `Kernel.php` | 拡張状態として`registered` / `booted` / `failed` / `skipped`を定義 |
| 1-2 | `Kernel.php` | 拡張依存関係を`requires()`で定義 |
| 1-3 | `Kernel.php` | `extensionInfo()`で状態と依存を返す |

---

## v0.21 実装済み仕様

v0.21は拡張イベントバスリリースとする。拡張同士を直接結合せず、イベント登録とemitで連携する。

| # | 対象 | 内容 |
|---|------|------|
| 1-1 | `Kernel.php` | `on()`でイベントリスナーを登録 |
| 1-2 | `Kernel.php` | `emit()`でpayloadを渡してイベントを実行 |
| 1-3 | `tests/debug.php` | イベント結果とpayload受け渡しを検証 |

---

## v0.22 実装済み仕様

v0.22は拡張設定スキーマリリースとする。拡張設定の必須キーと型をboot前に検証する。

| # | 対象 | 内容 |
|---|------|------|
| 1-1 | `Kernel.php` | `configureExtension()`で設定とスキーマを登録 |
| 1-2 | `Kernel.php` | `extensionConfig()`で設定を取得 |
| 1-3 | `Kernel.php` | `string` / `int` / `bool` / `array`型を検証 |

---

## v0.23 実装済み仕様

v0.23は拡張サンドボックス境界リリースとする。拡張ごとに利用可能なサービスを明示し、未許可サービス取得を拒否する。

| # | 対象 | 内容 |
|---|------|------|
| 1-1 | `Kernel.php` | `allowServices()`で拡張ごとの許可サービスを定義 |
| 1-2 | `Kernel.php` | `serviceFor()`で許可済みサービスのみ取得可能 |
| 1-3 | `tests/debug.php` | 許可サービス取得と未許可サービス拒否を検証 |

---

## v0.24 実装済み仕様

v0.24は拡張監査マニフェストリリースとする。カーネル内の拡張、サービス、モジュール、boot状態を一覧化する。

| # | 対象 | 内容 |
|---|------|------|
| 1-1 | `Kernel.php` | `extensionManifest()`で拡張監査マニフェストを返す |
| 1-2 | `Kernel.php` | 拡張状態、サービス一覧、モジュール一覧、boot状態を含める |
| 1-3 | `tests/debug.php` | `autonomous_system`テストでマニフェストを検証 |

---

## v0.25 実装済み仕様

v0.25は自律モジュール定義リリースとする。`AutonomousModule`契約を追加し、モジュールID、責務、依存、メッセージ処理、ヘルスチェックを定義する。

| # | 対象 | 内容 |
|---|------|------|
| 1-1 | `Extension.php` | `AutonomousModule`契約を追加 |
| 1-2 | `Kernel.php` | `registerModule()`で自律モジュールを登録 |
| 1-3 | `Kernel.php` | `modules()`で登録済みモジュール一覧を返す |

---

## v0.26 実装済み仕様

v0.26はモジュール間メッセージングリリースとする。登録モジュールへメッセージを送信し、必要に応じてカーネル側のメッセージハンドラで処理する。

| # | 対象 | 内容 |
|---|------|------|
| 1-1 | `Kernel.php` | `send()`でモジュールへメッセージを送信 |
| 1-2 | `Kernel.php` | `handle()`でメッセージハンドラを登録 |
| 1-3 | `tests/debug.php` | モジュール処理とハンドラ処理を検証 |

---

## v0.27 実装済み仕様

v0.27は自律ヘルスチェックリリースとする。KernelとAdlaireの両方でヘルスレポートを取得できる。

| # | 対象 | 内容 |
|---|------|------|
| 1-1 | `Kernel.php` | `healthReport()`で拡張とモジュール状態を返す |
| 1-2 | `Core.php` | `Adlaire::healthReport()`で全体状態を返す |
| 1-3 | `tests/debug.php` | ready状態を検証 |

---

## v0.28 実装済み仕様

v0.28はポリシーエンジンリリースとする。クラウド事業禁止と商用利用許可を共通ポリシー判定として扱う。

| # | 対象 | 内容 |
|---|------|------|
| 1-1 | `Extension.php` | `PolicyRule`契約を追加 |
| 1-2 | `Core.php` | `Adlaire::policyDecision()`でallow / deny / reasonを返す |
| 1-3 | `tests/debug.php` | クラウド事業禁止と商用利用許可を検証 |

---

## v0.29 実装済み仕様

v0.29は自律監査レポートリリースとする。ライセンス、統治、カーネル、ポリシー、ドリフト、配布マニフェストを統合した監査レポートを返す。

| # | 対象 | 内容 |
|---|------|------|
| 1-1 | `Core.php` | `Adlaire::autonomousAuditReport()`を追加 |
| 1-2 | `Core.php` | レポートにversion / release readiness / license / governance / kernel / policies / drift / manifestを含める |
| 1-3 | `tests/debug.php` | 自律監査レポートの必須セクションを検証 |

---

## v0.30 実装済み仕様

v0.30は安定化版リリースとする。v0.20からv0.29までのマイクロカーネル/自律性システム機能を現行仕様として検証する。v0.206以降はDeployment Coreのみ互換性を維持し、それ以外の領域は互換性保証を行わず、破壊的変更を許可する。

| # | 対象 | 内容 |
|---|------|------|
| 1-1 | `Core.php` | `Adlaire::stabilityContract()`を追加 |
| 1-2 | `Core.php` | 公開契約、Kernel契約、拡張契約、自律モジュール契約、Policy契約を現行仕様として整理 |
| 1-3 | `Core.php` | Deployment Core互換維持、非デプロイ領域の破壊的変更許可、公式デバッグテスト必須を安定化条件に含める |
| 1-4 | `tests/debug.php` | `autonomous_system`テストで安定化契約を検証 |

---

## v0.41-v0.50 実装済み仕様

### 基本方針

v0.41からv0.50は公式エコシステム管理・長期安定化フェーズとする。公式拡張、署名、リリース要件、Deployment Core互換維持、移行、サポート、セキュリティ修正、非デプロイ領域の互換性なし方針、リリース凍結、長期安定契約を監査可能な契約として扱う。v0.50は長期安定版としてリリースする。

### v0.41 — 公式拡張レジストリ仕様

| # | 対象 | 内容 |
|---|------|------|
| 1-1 | `Core.php` | `Adlaire::officialExtensionRegistry()`を追加 |
| 1-2 | `Core.php` | official / approved / rejected / unknown状態を定義 |
| 1-3 | `Core.php` | unknown拡張は公式版として扱わない |

### v0.42 — 拡張署名メタ情報

| # | 対象 | 内容 |
|---|------|------|
| 1-1 | `Core.php` | `Adlaire::extensionSignatureMetadata()`を追加 |
| 1-2 | `Core.php` | 署名アルゴリズム、署名者、署名状態、期限切れ拒否をメタ情報として返す |

### v0.43 — リリースプロファイル

| # | 対象 | 内容 |
|---|------|------|
| 1-1 | `Core.php` | `Adlaire::releaseProfiles()`を追加 |
| 1-2 | `Core.php` | minimal / standard / audited / distributed / extension-enabledを定義 |

### v0.44 — 公式移行ポリシー

| # | 対象 | 内容 |
|---|------|------|
| 1-1 | `Core.php` | `Adlaire::migrationPolicy()`を追加 |
| 1-2 | `Core.php` | Deployment Core破壊的変更禁止、非デプロイ領域の破壊的変更許可、公式テスト必須、ドキュメント更新必須を定義 |

### v0.45 — エコシステム監査レポート

| # | 対象 | 内容 |
|---|------|------|
| 1-1 | `Core.php` | `Adlaire::ecosystemAuditReport()`を追加 |
| 1-2 | `Core.php` | 拡張レジストリ、署名、リリース要件、移行、統治、安定性を含める |

### v0.46 — 長期サポート方針

| # | 対象 | 内容 |
|---|------|------|
| 1-1 | `Core.php` | `Adlaire::supportPolicy()`を追加 |
| 1-2 | `Core.php` | security / breaking / documentation fixesを許可し、Deployment Core以外の互換性修正を保証しない |

### v0.47 — セキュリティ修正プロトコル

| # | 対象 | 内容 |
|---|------|------|
| 1-1 | `Core.php` | `Adlaire::securityFixProtocol()`を追加 |
| 1-2 | `Core.php` | report / assess / patch / test / audit / release / document手順を固定 |

### v0.48 — 互換性なし方針

| # | 対象 | 内容 |
|---|------|------|
| 1-1 | `Core.php` | `Adlaire::noCompatibilityPolicy()`を追加 |
| 1-2 | `Core.php` | Deployment Core互換維持、非デプロイ領域の互換性保証なし、破壊的変更許可、移行ドキュメント必須を定義 |

### v0.49 — リリース凍結ポリシー

| # | 対象 | 内容 |
|---|------|------|
| 1-1 | `Core.php` | `Adlaire::releaseFreezePolicy()`を追加 |
| 1-2 | `Core.php` | 許可変更、禁止変更、承認必須、公式テスト必須を定義 |

### v0.50 — 長期安定版

| # | 対象 | 内容 |
|---|------|------|
| 1-1 | `Core.php` | `Adlaire::longTermStabilityContract()`を追加 |
| 1-2 | `Core.php` | Deployment Core互換維持、非デプロイ領域の互換性保証なし、破壊的変更許可、公式テスト必須、ドキュメント絶対、クラウド事業禁止固定、非オープンコントリビューション固定を定義 |
| 1-3 | `tests/debug.php` | `long_term_stability`テストで長期安定版条件を検証 |
| 1-4 | `adlaire-ecosystem.md` | v0.50を長期安定版として明記 |

---

## v0.54 実装済み仕様

### 基本方針

v0.54はAurisモジュール監査強化リリースとする。v0.51で定義したデプロイメントシステム軸、v0.52で定義したAuris独立システム廃止・名称保持・モジュール化方針、v0.53で実体化した`AurisModule`を継承し、Aurisモジュールのマニフェストとポリシー整合性検証を追加する。

v0.54時点ではアーキテクチャは変更しない。7ファイル原則、マイクロカーネル構成、現行公開契約、長期安定契約、クラウド事業禁止、非オープンコントリビューション方針は継続する。Deployment Coreのみ互換性を維持し、それ以外の互換性保証は行わない。

将来的にAuris（`https://github.com/fqwink/Auris`）のシステムと統合する。ただし、このフレームワークのリポジトリは独立したフレームワークリポジトリとして維持する。統合は将来予定であり、現時点ではアーキテクチャ変更を伴わない。

v0.54では、Auris統合後の扱いが`AurisModule`の返すマニフェストと`Adlaire::aurisIntegrationPolicy()`の内容で一致していることを検証可能にする。

### Phase 1 — デプロイメントシステム軸

| # | 対象 | 内容 |
|---|------|------|
| 1-1 | `Core.php` | `Adlaire::deploymentAxisPolicy()`を追加 |
| 1-2 | `Core.php` | `framework_axis`を`deployment system`として定義 |
| 1-3 | `Core.php` | デプロイメントシステムの主要構成要素を`DeploymentCore.php`として定義 |
| 1-4 | `Core.php` | デプロイメントシステムに分散型自律システム設計思想を適用 |

### Phase 2 — 汎用フレームワーク範囲

| # | 対象 | 内容 |
|---|------|------|
| 2-1 | `Core.php` | Core / Kernel / Extension / Database / Loggerを汎用フレームワーク範囲として定義 |
| 2-2 | `Core.php` | 汎用フレームワーク範囲は仕様制約内で一定の汎用性を持つものとして扱う |
| 2-3 | `Core.php` | 単体フレームワークとしての利用方針を維持 |

### Phase 3 — アーキテクチャ不変

| # | 対象 | 内容 |
|---|------|------|
| 3-1 | `Core.php` | `architecture_changed`を`false`として固定 |
| 3-2 | `Core.php` | 7ファイル原則を維持 |
| 3-3 | `Core.php` | マイクロカーネル方針を維持 |
| 3-4 | `adlaire-ecosystem.md` | 現時点でアーキテクチャを変更しないことを明記 |

### Phase 4 — 監査・配布・テスト

| # | 対象 | 内容 |
|---|------|------|
| 4-1 | `Core.php` | 監査結果に`deployment_axis_policy`を含める |
| 4-2 | `Core.php` | 配布マニフェストに`deployment_axis_policy`を含める |
| 4-3 | `Core.php` | リリース判定に`deployment_axis_policy`チェックを追加 |
| 4-4 | `Core.php` | 仕様整合性・仕様ドリフト確認に`deployment_axis_policy`を追加 |
| 4-5 | `tests/debug.php` | `deployment_axis_policy`テストで方針・監査・配布・リリース判定を検証 |

### Phase 5 — Auris将来統合方針

| # | 対象 | 内容 |
|---|------|------|
| 5-1 | `Core.php` | `Adlaire::aurisIntegrationPolicy()`を追加 |
| 5-2 | `Core.php` | Auris統合先を`https://github.com/fqwink/Auris`として定義 |
| 5-3 | `Core.php` | Adlaire Ecosystemリポジトリを独立したフレームワークリポジトリとして維持 |
| 5-4 | `Core.php` | Auris統合方針においても`architecture_changed`を`false`として固定 |
| 5-5 | `Core.php` | 監査・配布マニフェスト・リリース判定に`auris_integration_policy`を含める |
| 5-6 | `tests/debug.php` | `auris_integration_policy`テストで将来統合・リポジトリ維持・アーキテクチャ不変を検証 |

### Phase 6 — Auris統合後の廃止・モジュール化方針

| # | 対象 | 内容 |
|---|------|------|
| 6-1 | `Core.php` | `aurisIntegrationPolicy()`に`auris_independent_system_after_integration`を追加し、`abolished`として定義 |
| 6-2 | `Core.php` | `auris_repository_after_integration`を`deprecated`として定義 |
| 6-3 | `Core.php` | `auris_name_retained`を`true`として定義 |
| 6-4 | `Core.php` | `auris_module_name`を`Auris`として定義 |
| 6-5 | `Core.php` | `auris_moduleization`を`true`として定義 |
| 6-6 | `Core.php` | 仕様整合性・リリース判定でAuris独立システム廃止・名称保持・モジュール化を検証 |
| 6-7 | `tests/debug.php` | `auris_integration_policy`テストでAuris廃止・名称保持・モジュール化を検証 |

### Phase 7 — Aurisモジュール実体化

| # | 対象 | 内容 |
|---|------|------|
| 7-1 | `Extension.php` | `AurisModule`を追加し、`AutonomousModule`として実装 |
| 7-2 | `Extension.php` | `id()`で`Auris`を返し、Auris名称保持をコードで固定 |
| 7-3 | `Extension.php` | `auris.status`で名称保持・独立システム廃止・リポジトリ非推奨化を返す |
| 7-4 | `Extension.php` | `auris.policy`で統合後モジュール方針・アーキテクチャ不変を返す |
| 7-5 | `Extension.php` | `auris.metadata`でモジュール責務・依存・payloadを返す |
| 7-6 | `Core.php` | 公開契約に`AurisModule`を追加 |
| 7-7 | `Core.php` | `aurisIntegrationPolicy()`に`auris_module_class`と`auris_module_messages`を追加 |
| 7-8 | `tests/debug.php` | `auris_module`テストで直接実行・Kernel登録・メッセージ送信・ヘルスチェックを検証 |

### Phase 8 — Aurisモジュール監査強化

| # | 対象 | 内容 |
|---|------|------|
| 8-1 | `Extension.php` | `auris.manifest`メッセージを追加 |
| 8-2 | `Extension.php` | `auris.validate`メッセージを追加 |
| 8-3 | `Extension.php` | manifestでAuris ID、名称保持、モジュール化、廃止方針、対応メッセージ、health、policyを返す |
| 8-4 | `Extension.php` | validateで`Adlaire::aurisIntegrationPolicy()`との整合性を検証 |
| 8-5 | `Core.php` | `aurisIntegrationPolicy()`に`auris.manifest`と`auris.validate`を追加 |
| 8-6 | `Core.php` | Auris manifest必須・policy validation必須を監査条件に追加 |
| 8-7 | `tests/debug.php` | `auris_module`テストでmanifest、policy validation、invalid policy検出、Kernel経由validationを検証 |

---

## v0.130 実装済み仕様

### 基本方針

v0.130は汎用フレームワーク強化版とする。v0.100で定義した10ファイル原則とデプロイメントシステム軸を継承し、一般的なバックエンドフレームワークとして利用しやすいHTTP補助、Router middleware、Validator比較ルール、Support補助を追加する。

v0.130までに、ソースコード全体はデプロイメントシステムを軸にしながら、ある程度の汎用性あるフレームワークとして機能する状態へ改良する。現時点でアーキテクチャは変更せず、10ファイル原則、マイクロカーネル構成、Aurisモジュール方針、長期安定契約を継承する。

### Phase 1 — Deployer設定マニフェスト

| # | 対象 | 内容 |
|---|------|------|
| 1-1 | `DeploymentCore.php` | `DeployConfig::bool()`を追加 |
| 1-2 | `DeploymentCore.php` | `DeployConfig::deploymentManifest()`を追加 |
| 1-3 | `DeploymentCore.php` | deployment axis、repository、branch、各ディレクトリ、allowlist、integration modulesを公開 |
| 1-4 | `DeploymentCore.php` | Aurisがintegration modulesに含まれる場合、Auris統合考慮を`true`にする |

### Phase 2 — Deployerシステムマニフェスト

| # | 対象 | 内容 |
|---|------|------|
| 2-1 | `DeploymentCore.php` | `Deployer::deploymentSystemManifest()`を追加 |
| 2-2 | `DeploymentCore.php` | component、axis、design philosophy、architecture unchangedを公開 |
| 2-3 | `DeploymentCore.php` | required directoriesと設定マニフェストを含める |
| 2-4 | `DeploymentCore.php` | Auris統合考慮をマニフェストに含める |

### Phase 3 — Deployer Readiness

| # | 対象 | 内容 |
|---|------|------|
| 3-1 | `DeploymentCore.php` | `Deployer::deploymentReadiness()`を追加 |
| 3-2 | `DeploymentCore.php` | config valid、deployment axis、distributed autonomous design、Auris統合考慮、architecture unchangedを検証 |
| 3-3 | `DeploymentCore.php` | target / work / backupディレクトリ準備状態を検証 |
| 3-4 | `tests/debug.php` | Deployer readinessの全チェック成功を検証 |

### Phase 4 — Core v0.130到達ポリシー

| # | 対象 | 内容 |
|---|------|------|
| 4-1 | `Core.php` | `deploymentAxisPolicy()`に`v0_130_target`を追加 |
| 4-2 | `Core.php` | source code scopeをCore / Kernel / Extension / Database / Deployer / Logger / Config / Middleware / Supportとして定義 |
| 4-3 | `Core.php` | Deployer manifest必須、readiness必須、Auris統合考慮必須を定義 |
| 4-4 | `Core.php` | 仕様整合性・リリース判定でv0.130到達条件を検証 |
| 4-5 | `tests/debug.php` | `deployment_axis_policy`テストでv0.130到達条件を検証 |

### Phase 5 — 10ファイル原則

| # | 対象 | 内容 |
|---|------|------|
| 5-1 | `Config.php` | `ConfigRepository`を追加 |
| 5-2 | `Middleware.php` | `MiddlewarePipeline`を追加 |
| 5-3 | `Support.php` | `AdlaireSupport`を追加 |
| 5-4 | `Core.php` | 10ファイル原則を監査・配布マニフェスト・リリース判定へ反映 |
| 5-5 | `tests/debug.php` | 10ファイル原則と追加公開契約を検証 |

### Phase 6 — 汎用フレームワーク機能

| # | 対象 | 内容 |
|---|------|------|
| 6-1 | `Core.php` | Routerグローバルミドルウェアを追加 |
| 6-2 | `Core.php` | RouteDefinition単位のミドルウェアを追加 |
| 6-3 | `Config.php` | dot notationによる設定取得・設定更新・mergeを提供 |
| 6-4 | `Middleware.php` | 汎用パイプライン処理を提供 |
| 6-5 | `Support.php` | dot notation data get/setとslug生成を提供 |
| 6-6 | `tests/debug.php` | Router middleware、ConfigRepository、MiddlewarePipeline、AdlaireSupportを検証 |

### Phase 7 — HTTP補助機能

| # | 対象 | 内容 |
|---|------|------|
| 7-1 | `Core.php` | `Request::all()`を追加 |
| 7-2 | `Core.php` | `Request::only()`を追加 |
| 7-3 | `Core.php` | `Request::except()`を追加 |
| 7-4 | `Core.php` | `Response::headers(array $headers = [])`でヘッダー取得と一括設定に対応 |
| 7-5 | `tests/debug.php` | Request補助とResponseヘッダー一括設定を検証 |

### Phase 8 — Router Middleware強化

| # | 対象 | 内容 |
|---|------|------|
| 8-1 | `Core.php` | Routerグローバルミドルウェアを追加 |
| 8-2 | `Core.php` | RouteDefinition単位のミドルウェアを追加 |
| 8-3 | `Core.php` | Router groupでグループミドルウェアを指定可能にする |
| 8-4 | `Core.php` | `routes()`に`middleware_count`を追加 |
| 8-5 | `tests/debug.php` | global / route middlewareの実行順序を検証 |

### Phase 9 — Validator / Support強化

| # | 対象 | 内容 |
|---|------|------|
| 9-1 | `Core.php` | Validatorに`same`ルールを追加 |
| 9-2 | `Core.php` | Validatorに`different`ルールを追加 |
| 9-3 | `Core.php` | Validatorに`confirmed`ルールを追加 |
| 9-4 | `Support.php` | `AdlaireSupport::dataHas()`を追加 |
| 9-5 | `Support.php` | `AdlaireSupport::studly()`を追加 |
| 9-6 | `tests/debug.php` | 追加ValidatorルールとSupport補助を検証 |

---

## v0.200 安定版リリース仕様

### 基本方針

v0.200はバックエンドフレームワーク安定版リリースとする。v0.130で到達した10ファイル原則、デプロイメントシステム軸、マイクロカーネル構成、Aurisモジュール統合方針、長期安定契約をすべて継承する。

v0.200までに、バックエンドフレームワークとしての利用性を高めるため、Request / Config / Middleware / Supportの補助機能を追加する。ただし、v0.206以降はDeployment Coreのみ互換性を維持し、その他の公開契約削除、引数変更、戻り値構造変更を現行仕様と公式テストに従って許可する。クラウド事業禁止方針の緩和、オープンコントリビューション化は行わない。

### Phase 1 — v0.200安定版契約

| # | 対象 | 内容 |
|---|------|------|
| 1-1 | `Core.php` | `Adlaire::stableReleaseContract()`を追加 |
| 1-2 | `Core.php` | release nameを`v0.200 stable backend framework release`として定義 |
| 1-3 | `Core.php` | routing / middleware / validation / database / logging / deployment / configuration / support helpers / microkernel / Auris module integrationを安定版能力として定義 |
| 1-4 | `Core.php` | Deployment Core no breaking changes、non-deployment breaking changes allowed、10ファイル原則、deployment axis、Docker debug verifiedを安定版条件として定義 |
| 1-5 | `tests/debug.php` | `stable_release_contract`テストで契約・監査・配布マニフェスト・リリース判定を検証 |

### Phase 2 — Core v0.200到達ポリシー

| # | 対象 | 内容 |
|---|------|------|
| 2-1 | `Core.php` | `deploymentAxisPolicy()`に`v0_200_target`を定義 |
| 2-2 | `Core.php` | backend framework capability requiredを`true`として定義 |
| 2-3 | `Core.php` | stable release requiredを`true`として定義 |
| 2-4 | `Core.php` | 仕様整合性・リリース判定でv0.200到達条件を検証 |
| 2-5 | `tests/debug.php` | `deployment_axis_policy`テストでv0.200到達条件を検証 |

### Phase 3 — Request補助強化

| # | 対象 | 内容 |
|---|------|------|
| 3-1 | `Core.php` | `Request::string()`を追加 |
| 3-2 | `Core.php` | `Request::integer()`を追加 |
| 3-3 | `Core.php` | `Request::boolean()`を追加 |
| 3-4 | `tests/debug.php` | query inputに対するtyped helperの変換結果を検証 |

### Phase 4 — Config補助強化

| # | 対象 | 内容 |
|---|------|------|
| 4-1 | `Config.php` | `ConfigRepository::required()`を追加 |
| 4-2 | `Config.php` | `ConfigRepository::integer()`を追加 |
| 4-3 | `Config.php` | `ConfigRepository::boolean()`を追加 |
| 4-4 | `tests/debug.php` | 必須キー取得、bool変換、int変換を検証 |

### Phase 5 — Middleware / Support補助強化

| # | 対象 | 内容 |
|---|------|------|
| 5-1 | `Middleware.php` | `MiddlewarePipeline::through()`を追加 |
| 5-2 | `Middleware.php` | middleware配列のcallable検証を行う |
| 5-3 | `Support.php` | `AdlaireSupport::snake()`を追加 |
| 5-4 | `Support.php` | `AdlaireSupport::classBasename()`を追加 |
| 5-5 | `tests/debug.php` | middleware一括登録、snake case、class basenameを検証 |

### Phase 6 — 安定版受入条件

| # | 対象 | 内容 |
|---|------|------|
| 6-1 | `Core.php` | `Adlaire::audit()`に`stable_release_contract`を含める |
| 6-2 | `Core.php` | `Adlaire::distributionManifest()`に`stable_release_contract`を含める |
| 6-3 | `Core.php` | `Adlaire::specificationIntegrity()`に安定版契約チェックを含める |
| 6-4 | `Core.php` | `Adlaire::releaseReadiness()`に安定版契約チェックを含める |
| 6-5 | `tests/debug.php` | Docker上の公式デバッグテストが`OK`を出力し、`Adlaire::releaseReadiness()`が`ready: true`を返すことを完了条件とする |

---

## v0.202 構造固定仕様

### 基本方針

v0.202はDeployment Core / Framework Core構造固定と、Xserverレンタルサーバ本番同等テストプロファイルを追加するリリースとする。v0.200安定版を継承し、デプロイメントシステムを`DeploymentCore.php`へ改名してルート配置の単一ファイルCoreとする。

デプロイメントシステム以外の汎用性フレームワークは、すべて`FrameworkCore/`ディレクトリへ物理的に集約する。`FrameworkCore/`外に汎用性フレームワークのPHP公開ファイルを配置してはならない。

### Phase 1 — Deployment Core固定

| # | 対象 | 内容 |
|---|------|------|
| 1-1 | `DeploymentCore.php` | デプロイメントシステムのルート単一ファイル名を`DeploymentCore.php`へ固定 |
| 1-2 | `DeploymentCore.php` | ルート配置の単一ファイルCoreとして維持 |
| 1-3 | `DeploymentCore.php` | `FrameworkCore/Logger.php`を読み込む |
| 1-4 | `DeploymentCore.php` | `deploymentSystemManifest()`のcomponentを`DeploymentCore.php`として返す |

### Phase 2 — Framework Core物理集約

| # | 対象 | 内容 |
|---|------|------|
| 2-1 | `FrameworkCore/Core.php` | フレームワーク本体をFramework Coreへ移動 |
| 2-2 | `FrameworkCore/Kernel.php` | マイクロカーネルをFramework Coreへ移動 |
| 2-3 | `FrameworkCore/Extension.php` | 拡張契約をFramework Coreへ移動 |
| 2-4 | `FrameworkCore/Database.php` | データベース基盤をFramework Coreへ移動 |
| 2-5 | `FrameworkCore/Logger.php` | ログ基盤をFramework Coreへ移動 |
| 2-6 | `FrameworkCore/Config.php` | 設定リポジトリをFramework Coreへ移動 |
| 2-7 | `FrameworkCore/Middleware.php` | ミドルウェアパイプラインをFramework Coreへ移動 |
| 2-8 | `FrameworkCore/Support.php` | サポートヘルパーをFramework Coreへ移動 |

### Phase 3 — 監査・配布マニフェスト更新

| # | 対象 | 内容 |
|---|------|------|
| 3-1 | `FrameworkCore/Core.php` | 公開契約の物理配置を`FrameworkCore/...`と`DeploymentCore.php`へ更新 |
| 3-2 | `FrameworkCore/Core.php` | `deploymentAxisPolicy()`に`v0_202_target`を定義 |
| 3-3 | `FrameworkCore/Core.php` | `distributionManifest()`を新しい物理配置へ更新 |
| 3-4 | `tests/debug.php` | 新しい物理配置、公開契約、配布マニフェスト、公式デバッグテストを検証 |

### Phase 4 — Xserver本番同等テスト

| # | 対象 | 内容 |
|---|------|------|
| 4-1 | `FrameworkCore/Core.php` | `productionEnvironmentPolicy()`でXserverレンタルサーバ本番環境を仕様化 |
| 4-2 | `FrameworkCore/Core.php` | `audit()` / `releaseRequirementMatrix()` / `releaseReadiness()` / `distributionManifest()`へ本番同等プロファイルを追加 |
| 4-3 | `tests/debug.php` | `production_equivalent_environment`テストでXserver互換プロファイル、PHP 8.3、`.htaccess`、`public_html`、外部依存なしを検証 |
| 4-4 | `tests/debug.php` | 公式リリースゲートに`xserver_profile_audit`を追加 |

---

## v0.203 SQLite / libSQL API Runtime Hardening仕様

### 基本方針

v0.203は、MySQLへ対応範囲を広げず、SQLite / libSQL API軸を運用しやすくするリリースとする。v0.226以降も公開APIは廃止するが、libSQL APIは内部DB transportとして維持・強化する。

### Phase 1 — SQLite既定値強化

| # | 対象 | 内容 |
|---|------|------|
| 1-1 | `FrameworkCore/Database.php` | SQLite接続時に`PRAGMA foreign_keys = ON`を既定で適用 |
| 1-2 | `FrameworkCore/Database.php` | `PRAGMA busy_timeout = 5000`を既定で適用 |
| 1-3 | `FrameworkCore/Database.php` | ファイルDBでは`PRAGMA journal_mode = WAL`を既定で適用 |
| 1-4 | `FrameworkCore/Database.php` | インメモリDBではWAL強制を行わない |
| 1-5 | `tests/debug.php` | `runtimeProfile()`でSQLiteランタイム設定を検証 |

### Phase 2 — 設定連携

| # | 対象 | 内容 |
|---|------|------|
| 2-1 | `FrameworkCore/Database.php` | `Database::fromConfig()`を追加 |
| 2-2 | `FrameworkCore/Database.php` | 配列と`ConfigRepository`の両方からDB接続を登録可能にする |
| 2-3 | `FrameworkCore/Database.php` | SQLiteのランタイムオプションを`options`で受け付ける |
| 2-4 | `tests/debug.php` | `Database::fromConfig()`で接続登録・既定接続・SQLiteオプション反映を検証 |

### Phase 3 — libSQL API運用強化

| # | 対象 | 内容 |
|---|------|------|
| 3-1 | `FrameworkCore/Database.php` | `https:` / `libsql:` DB URLを内部libSQL API transportとして処理 |
| 3-2 | `FrameworkCore/Database.php` | `wss:` DB URLをHTTPS API fallbackとして処理 |
| 3-3 | `FrameworkCore/Database.php` | `timeout_seconds` / `retries` / `token_required` / `consistency`を検証 |
| 3-4 | `FrameworkCore/Database.php` | `transport` callableで外部通信なしに公式テスト可能にする |
| 3-5 | `FrameworkCore/Core.php` | `databaseRuntimeHardeningPolicy()`で内部libSQL API強化方針を公開 |
| 3-6 | `tests/debug.php` | libSQL API payload、Bearer、runtimeProfile、fallbackを検証 |

### Phase 4 — MySQL非対応方針

| # | 対象 | 内容 |
|---|------|------|
| 4-1 | `FrameworkCore/Core.php` | `databaseRuntimeHardeningPolicy()`に`mysql_support_planned = false`を明記 |
| 4-2 | `FrameworkCore/Core.php` | `stableReleaseContract()`にMySQL対応予定なしを含める |
| 4-3 | `tests/debug.php` | MySQL DSNが非対応として拒否されることを検証 |
| 4-4 | `adlaire-ecosystem.md` | DB方針をSQLite / libSQL API軸として明文化 |

---

## v0.204 Runtime Operations Hardening仕様

### 基本方針

v0.204は、特定ホスティング環境に依存せず、リポジトリ全体の安定版リリース判断を効率化する運用診断リリースとする。DB強化はv0.203を継承し、v0.204ではアプリが自身の状態・設定・リリース準備状況を標準診断で説明できることを完了条件とする。

### Phase 1 — 標準Health Check

| # | 対象 | 内容 |
|---|------|------|
| 1-1 | `FrameworkCore/Core.php` | `Adlaire::health()`を追加 |
| 1-2 | `FrameworkCore/Core.php` | PHPバージョン、実行環境、フレームワークバージョンを標準チェック |
| 1-3 | `FrameworkCore/Core.php` | DB接続チェックを任意化 |
| 1-4 | `FrameworkCore/Core.php` | 書き込み可能パスチェックを任意化 |
| 1-5 | `tests/debug.php` | `runtime_operations_hardening`テストでヘルスチェック結果を検証 |

### Phase 2 — 設定監査

| # | 対象 | 内容 |
|---|------|------|
| 2-1 | `FrameworkCore/Core.php` | `Adlaire::configAudit()`を追加 |
| 2-2 | `FrameworkCore/Core.php` | 必須環境変数の存在確認を標準化 |
| 2-3 | `FrameworkCore/Core.php` | 本番環境で`APP_DEBUG=true`を失敗扱いにする |
| 2-4 | `FrameworkCore/Core.php` | 書き込み可能パス監査を標準化 |
| 2-5 | `tests/debug.php` | 正常設定と本番デバッグ有効時の失敗を検証 |

### Phase 3 — リリース効率化

| # | 対象 | 内容 |
|---|------|------|
| 3-1 | `FrameworkCore/Core.php` | `runtimeOperationsHardeningPolicy()`を追加 |
| 3-2 | `FrameworkCore/Core.php` | 単一公式デバッグコマンドを安定版確認の中心として公開 |
| 3-3 | `scripts/release-check.sh` | PHP構文検査、公式デバッグテスト、プロファイル監査を一括実行 |
| 3-4 | `FrameworkCore/Core.php` | source lint、release readiness、distribution manifestをリリース効率化条件として公開 |
| 3-5 | `FrameworkCore/Core.php` | `audit()` / `releaseRequirementMatrix()` / `distributionManifest()` / `releaseReadiness()`に運用強化ポリシーを含める |
| 3-6 | `tests/debug.php` | 監査、リリース要件、配布、リリース判定への反映を検証 |

---

## v0.205 Operations Dashboard仕様

### 基本方針

v0.205は、v0.204の運用診断結果を可視化する任意ダッシュボードを追加する。ダッシュボードはデフォルト無効、認証必須、読み取り専用とする。ダッシュボードからデプロイ、設定変更、任意コマンド実行、DBクエリ実行、外部通信を行ってはならない。

### Phase 1 — Dashboard Core

| # | 対象 | 内容 |
|---|------|------|
| 1-1 | `FrameworkCore/Core.php` | `Adlaire::dashboardPolicy()`を追加 |
| 1-2 | `FrameworkCore/Core.php` | ダッシュボード用JSON出力を作らず、表示データ集約は`public_html/dashboard.php`内に限定 |
| 1-3 | `FrameworkCore/Core.php` | `dashboardEnabled()` / `dashboardTokenConfigured()`を追加 |
| 1-4 | `FrameworkCore/Core.php` | ダッシュボードポリシーを`audit()` / `releaseRequirementMatrix()` / `distributionManifest()` / `releaseReadiness()`へ追加 |

### Phase 2 — Dashboard Entry Point

| # | 対象 | 内容 |
|---|------|------|
| 2-1 | `public_html/dashboard.php` | 依存なしの単一PHPダッシュボードを追加 |
| 2-2 | `public_html/dashboard.php` | `ADLAIRE_DASHBOARD_ENABLED=true`でのみ有効化 |
| 2-3 | `public_html/dashboard.php` | `ADLAIRE_DASHBOARD_TOKEN`によるBearer tokenまたはsession token認証を必須化 |
| 2-4 | `public_html/dashboard.php` | HTTP/JSON出力を提供しない |
| 2-5 | `public_html/dashboard.php` | HTML表示は`overview` / `health` / `config_audit` / `release_readiness` / `distribution` / `database` / `security`を表示 |

### Phase 3 — 安全制約

| # | 対象 | 内容 |
|---|------|------|
| 3-1 | `FrameworkCore/Core.php` | `dashboardPolicy()`で`read_only = true`を公開 |
| 3-2 | `FrameworkCore/Core.php` | `command_execution_allowed = false`を公開 |
| 3-3 | `FrameworkCore/Core.php` | `writes_allowed = false`を公開 |
| 3-4 | `FrameworkCore/Core.php` | `external_network_allowed = false`を公開 |
| 3-5 | `tests/debug.php` | ダッシュボードデータにトークン値が含まれないことを検証 |

### Phase 4 — リリースゲート

| # | 対象 | 内容 |
|---|------|------|
| 4-1 | `tests/debug.php` | `operations_dashboard`テストを追加 |
| 4-2 | `scripts/release-check.sh` | `public_html/dashboard.php`のPHP構文検査を追加 |
| 4-3 | `scripts/xserver-profile-audit.sh` | `public_html/dashboard.php`の存在検証を追加 |

---

## v0.206 Configuration File Prohibition仕様

### 基本方針

v0.206は、フレームワーク全体でランタイム設定ファイルを禁止する。設定値は環境変数または実行時配列のみから渡す。`ConfigRepository`は設定ファイルではなく実行時配列リポジトリとして許可する。JSONは設定ファイルではなく、マニフェスト、履歴、監査、リリースメタデータ、機械可読ポリシーのメタデータ用途に限り許可する。

### Phase 1 — 禁止対象

| # | 対象 | 内容 |
|---|------|------|
| 1-1 | `.env*` | フレームワーク設定ファイルとして禁止 |
| 1-2 | `*.ini` | フレームワーク設定ファイルとして禁止 |
| 1-3 | `*.conf` | フレームワーク設定ファイルとして禁止 |
| 1-4 | `*.yaml` / `*.yml` | フレームワーク設定ファイルとして禁止。ただし`docker-compose.xserver.yml`はテスト用ツール例外 |
| 1-5 | `config.php` / `*.config.php` | フレームワーク設定ファイルとして禁止 |

### Phase 2 — JSONメタデータ例外

| # | 対象 | 内容 |
|---|------|------|
| 2-1 | `manifest.json` | デプロイスナップショットのメタデータとして許可 |
| 2-2 | `deploy_history.jsonl` | デプロイ履歴として許可 |
| 2-3 | JSON監査出力 | 監査・リリース・機械可読ポリシーの出力として許可 |
| 2-4 | JSON秘密設定 | トークン、HMACキー、DB接続情報などの秘密設定保存は禁止 |

### Phase 3 — 実装

| # | 対象 | 内容 |
|---|------|------|
| 3-1 | `.env.xserver.example` | 削除 |
| 3-2 | `docker-compose.xserver.yml` | `env_file`を廃止し、非秘密のテスト用デフォルトだけ`environment`へ直書き |
| 3-3 | `config/xserver/*` | リポジトリ内設定ファイルを削除し、Dockerfile内のテスト用生成へ移行 |
| 3-4 | `FrameworkCore/Core.php` | `configurationFilePolicy()`を追加 |
| 3-5 | `scripts/xserver-profile-audit.sh` | 禁止設定ファイルの不在を検証 |

---

## v0.207 Deployment Preflight Guard仕様

### 基本方針

v0.207は、フレームワーク中核であるデプロイメントシステムの互換性を維持したまま、デプロイ前の安全確認を強化する。`DeploymentCore.php`の既存実行契約は破壊せず、読み取り専用の`Deployer::preflight()`を追加する。preflightは外部通信、任意コマンド実行、ファイル変更を行わず、ディレクトリ、ログ、allowlist、lock、履歴保持、Deployment Core契約を検証する。

### Phase 1 — Deployment Core Preflight

| # | 対象 | 内容 |
|---|------|------|
| 1-1 | `DeploymentCore.php` | `Deployer::preflight()`を追加 |
| 1-2 | `DeploymentCore.php` | `DeploymentCore.php` / deployment system / architecture unchangedを検証 |
| 1-3 | `DeploymentCore.php` | target / work / backup / log ディレクトリの存在と書き込み可否を検証 |
| 1-4 | `DeploymentCore.php` | `deploy_allowlist`設定済み、lock利用可能、history保持数妥当を検証 |
| 1-5 | `tests/debug.php` | `deployer_config`テストでpreflight成功を検証 |

### Phase 2 — Framework Policy

| # | 対象 | 内容 |
|---|------|------|
| 2-1 | `FrameworkCore/Core.php` | `deploymentPreflightPolicy()`を追加 |
| 2-2 | `FrameworkCore/Core.php` | Deployment Core互換維持、破壊的変更禁止、読み取り専用、コマンド実行なしを定義 |
| 2-3 | `FrameworkCore/Core.php` | `audit()` / `releaseRequirementMatrix()` / `distributionManifest()` / `releaseReadiness()`へ追加 |
| 2-4 | `tests/debug.php` | `deployment_preflight_policy`テストを追加 |

### Phase 3 — リリースゲート

| # | 対象 | 内容 |
|---|------|------|
| 3-1 | `FrameworkCore/Core.php` | `RELEASE-REQ-016`と`DEPLOY-REQ-003`を追加 |
| 3-2 | `tests/debug.php` | 仕様対応表と仕様ドリフト検出にpreflightを含める |
| 3-3 | `scripts/release-check.sh` | 既存の公式デバッグテストでpreflight検証を通す |

---

## v0.208 Deployment Plan Preview仕様

### 基本方針

v0.208は、デプロイメントシステムの互換性を維持したまま、デプロイ前の変更内容を読み取り専用で可視化する。`DeploymentCore.php`の既存実行契約は破壊せず、`Deployer::planPreview()`を追加する。plan previewは外部通信、任意コマンド実行、ファイル変更を行わず、sourceとtargetの差分を`added` / `modified` / `unchanged` / `skipped`に分類する。

Deployment Core自身の変更は、安全判定上の重要情報として`deployment_core_change_detected`で明示する。デプロイ実行、ロールバック実行、設定ファイル追加、公開向けHTTP/JSON出力は行わない。

### Phase 1 — Deployment Core Plan Preview

| # | 対象 | 内容 |
|---|------|------|
| 1-1 | `DeploymentCore.php` | `Deployer::planPreview(string $sourceDir)`を追加 |
| 1-2 | `DeploymentCore.php` | `deploy_allowlist`を適用し、対象外ファイルを`skipped`へ分類 |
| 1-3 | `DeploymentCore.php` | targetに存在しない対象ファイルを`added`へ分類 |
| 1-4 | `DeploymentCore.php` | sourceとtargetのハッシュが異なる対象ファイルを`modified`へ分類 |
| 1-5 | `DeploymentCore.php` | sourceとtargetのハッシュが同じ対象ファイルを`unchanged`へ分類 |
| 1-6 | `DeploymentCore.php` | `DeploymentCore.php`が追加または変更対象に含まれる場合、Deployment Core変更検出をtrueにする |
| 1-7 | `tests/debug.php` | `deployer_config`テストで分類、読み取り専用、コマンド実行なし、書き込みなしを検証 |

### Phase 2 — Framework Policy

| # | 対象 | 内容 |
|---|------|------|
| 2-1 | `FrameworkCore/Core.php` | `deploymentPlanPreviewPolicy()`を追加 |
| 2-2 | `FrameworkCore/Core.php` | 読み取り専用、コマンド実行なし、書き込みなし、ネットワークアクセスなしを定義 |
| 2-3 | `FrameworkCore/Core.php` | 分類種別、Deployment Core変更検出、allowlist適用を方針として固定 |
| 2-4 | `FrameworkCore/Core.php` | `audit()` / `releaseRequirementMatrix()` / `distributionManifest()` / `releaseReadiness()`へ追加 |
| 2-5 | `tests/debug.php` | `deployment_plan_preview_policy`テストを追加 |

### Phase 3 — リリースゲート

| # | 対象 | 内容 |
|---|------|------|
| 3-1 | `FrameworkCore/Core.php` | `RELEASE-REQ-017`と`DEPLOY-REQ-004`を追加 |
| 3-2 | `tests/debug.php` | 仕様対応表と仕様ドリフト検出にplan previewを含める |
| 3-3 | `scripts/release-check.sh` | 既存の公式デバッグテストでplan preview検証を通す |

---

## v0.209 Deployment Compatibility Snapshot仕様

### 基本方針

v0.209は、デプロイメントシステムの互換性証跡を読み取り専用で固定する。`DeploymentCore.php`の既存実行契約は破壊せず、`Deployer::compatibilitySnapshot()`を追加する。compatibility snapshotは外部通信、任意コマンド実行、ファイル変更を行わず、Deployment Coreのコンポーネント、デプロイメント軸、アーキテクチャ不変、preflight結果、plan previewの読み取り専用性を一括して返す。

このスナップショットは、デプロイ実行可否を即時に決定するものではなく、v0.210以降のロールバックプレビュー、v0.211安全スコア、v0.215安定版リリース統合で利用する制御情報とする。設定ファイル追加、公開向けHTTP/JSON出力、Deployment Coreの破壊的変更は行わない。

### Phase 1 — Deployment Core Compatibility Snapshot

| # | 対象 | 内容 |
|---|------|------|
| 1-1 | `DeploymentCore.php` | `Deployer::compatibilitySnapshot(?string $sourceDir = null)`を追加 |
| 1-2 | `DeploymentCore.php` | Deployment Coreコンポーネント、デプロイメント軸、アーキテクチャ不変を検証 |
| 1-3 | `DeploymentCore.php` | `preflight()`結果を互換性証跡として含める |
| 1-4 | `DeploymentCore.php` | source指定時は`planPreview()`のsummaryとDeployment Core変更検出を含める |
| 1-5 | `DeploymentCore.php` | 読み取り専用、コマンド実行なし、書き込みなしを返却値で明示 |
| 1-6 | `tests/debug.php` | `deployer_config`テストで互換性証跡、preflight証跡、plan preview証跡を検証 |

### Phase 2 — Framework Policy

| # | 対象 | 内容 |
|---|------|------|
| 2-1 | `FrameworkCore/Core.php` | `deploymentCompatibilitySnapshotPolicy()`を追加 |
| 2-2 | `FrameworkCore/Core.php` | Deployment Core互換性保証、破壊的変更禁止、読み取り専用、コマンド実行なし、書き込みなしを定義 |
| 2-3 | `FrameworkCore/Core.php` | `audit()` / `releaseRequirementMatrix()` / `distributionManifest()` / `releaseReadiness()`へ追加 |
| 2-4 | `tests/debug.php` | `deployment_compatibility_snapshot_policy`テストを追加 |

### Phase 3 — リリースゲート

| # | 対象 | 内容 |
|---|------|------|
| 3-1 | `FrameworkCore/Core.php` | `RELEASE-REQ-018`と`DEPLOY-REQ-005`を追加 |
| 3-2 | `tests/debug.php` | 仕様対応表と仕様ドリフト検出にcompatibility snapshotを含める |
| 3-3 | `scripts/release-check.sh` | 既存の公式デバッグテストでcompatibility snapshot検証を通す |

---

## v0.210-v0.216 Deployment Control統合仕様

### 基本方針

v0.210からv0.216は、デプロイメントシステムを完全に制御するための読み取り専用情報を段階的に整備する。公開向けHTTP/JSON出力、設定ファイル追加、任意コマンド実行、ファイル変更、Deployment Coreの破壊的変更は行わない。実装は`DeploymentCore.php`の既存実行契約を維持し、Framework Core側ではポリシー、監査、リリース要件、配布マニフェスト、公式デバッグテストへ接続する。

### v0.210 — Deployment Rollback Preview

| # | 対象 | 内容 |
|---|------|------|
| 1-1 | `DeploymentCore.php` | `Deployer::rollbackPreview()`を追加 |
| 1-2 | `DeploymentCore.php` | 最新snapshotから復元対象を`restore`へ分類 |
| 1-3 | `DeploymentCore.php` | manifestとの差分でrollback時に削除されるファイルを`remove`へ分類 |
| 1-4 | `DeploymentCore.php` | targetに存在しない復元対象を`missing`へ分類 |
| 1-5 | `FrameworkCore/Core.php` | `deploymentRollbackPreviewPolicy()`を追加 |

### v0.211 — Deployment Safety Score

| # | 対象 | 内容 |
|---|------|------|
| 2-1 | `DeploymentCore.php` | `Deployer::deploymentSafetyScore()`を追加 |
| 2-2 | `DeploymentCore.php` | compatibility snapshot、rollback preview、plan previewを安全スコア入力にする |
| 2-3 | `DeploymentCore.php` | 70点以上をリリース最低基準とする |
| 2-4 | `FrameworkCore/Core.php` | `deploymentSafetyScorePolicy()`を追加 |

### v0.212 — Dashboard Control Visibility

| # | 対象 | 内容 |
|---|------|------|
| 3-1 | `public_html/dashboard.php` | Deployment Control、Safety Score、Deploy Historyセクションを追加 |
| 3-2 | `FrameworkCore/Core.php` | `dashboardControlVisibilityPolicy()`を追加 |
| 3-3 | `tests/debug.php` | ダッシュボードがHTML表示のみで制御情報を表示することを検証 |

### v0.213 — Deployment History Visualization

| # | 対象 | 内容 |
|---|------|------|
| 4-1 | `DeploymentCore.php` | `Deployer::deploymentHistorySummary()`を追加 |
| 4-2 | `DeploymentCore.php` | `deploy_history.jsonl`を読み取り専用で集計 |
| 4-3 | `FrameworkCore/Core.php` | `deploymentHistoryVisualizationPolicy()`を追加 |

### v0.214 — Deployment Control Report

| # | 対象 | 内容 |
|---|------|------|
| 5-1 | `DeploymentCore.php` | `Deployer::deploymentControlReport()`を追加 |
| 5-2 | `DeploymentCore.php` | preflight、plan preview、compatibility snapshot、rollback preview、safety score、historyを統合 |
| 5-3 | `FrameworkCore/Core.php` | `deploymentControlReportPolicy()`を追加 |

### v0.215 — Stable Release Gate

| # | 対象 | 内容 |
|---|------|------|
| 6-1 | `FrameworkCore/Core.php` | `stableReleaseGatePolicy()`を追加 |
| 6-2 | `FrameworkCore/Core.php` | release readiness、deployment safety score、compatibility snapshot、rollback previewを統合判定入力にする |
| 6-3 | `tests/debug.php` | 公式デバッグテストでstable release gateを検証 |

### v0.216 — Adlaire UI Framework

| # | 対象 | 内容 |
|---|------|------|
| 7-1 | `public_html/assets/adlaire-ui.css` | ダッシュボード表示用CSS基盤を追加 |
| 7-2 | `public_html/dashboard.php` | インラインCSSをCSSアセット参照へ移行 |
| 7-3 | `FrameworkCore/Core.php` | `uiFrameworkPolicy()`を追加 |
| 7-4 | `scripts/xserver-profile-audit.sh` | CSSアセット存在確認を追加 |
| 7-5 | `tests/debug.php` | CSSアセットとダッシュボード参照を検証 |

---

## v0.217-v0.225 Release Candidate Control仕様

### 基本方針

v0.217からv0.225は、安定版候補判定に必要な証跡を保存・比較・統合する。JSONは設定ファイルではなく、履歴、監査、リリース証跡の成果物としてのみ許可する。公開向けHTTP/JSON出力、設定ファイル追加、Deployment Coreの破壊的変更は行わない。

### v0.217 — Deployment Control Snapshot

| # | 対象 | 内容 |
|---|------|------|
| 1-1 | `DeploymentCore.php` | `Deployer::recordDeploymentControlSnapshot()`を追加 |
| 1-2 | `DeploymentCore.php` | `deployment_control_snapshots.jsonl`へcontrol reportを監査成果物として記録 |
| 1-3 | `FrameworkCore/Core.php` | `deploymentControlSnapshotPolicy()`を追加 |

### v0.218 — Deployment Safety Score Details

| # | 対象 | 内容 |
|---|------|------|
| 2-1 | `DeploymentCore.php` | `Deployer::deploymentSafetyScoreDetails()`を追加 |
| 2-2 | `DeploymentCore.php` | 減点理由、重要度、減点値を返す |
| 2-3 | `FrameworkCore/Core.php` | `deploymentSafetyScoreDetailsPolicy()`を追加 |

### v0.219 — Rollback State Preview

| # | 対象 | 内容 |
|---|------|------|
| 3-1 | `DeploymentCore.php` | `Deployer::rollbackStatePreview()`を追加 |
| 3-2 | `DeploymentCore.php` | restored / removed / missingの想定状態を返す |
| 3-3 | `FrameworkCore/Core.php` | `rollbackStatePreviewPolicy()`を追加 |

### v0.220 — Dashboard Release Gate View

| # | 対象 | 内容 |
|---|------|------|
| 4-1 | `FrameworkCore/Core.php` | `dashboardReleaseGateViewPolicy()`を追加 |
| 4-2 | `public_html/dashboard.php` | release gate、RC status、safety scoreを表示対象として扱う |

### v0.221 — Deployment Timeline View

| # | 対象 | 内容 |
|---|------|------|
| 5-1 | `FrameworkCore/Core.php` | `deploymentTimelinePolicy()`を追加 |
| 5-2 | `FrameworkCore/Core.php` | preflight、plan preview、compatibility snapshot、rollback preview、safety score、release gateを時系列イベントとして固定 |

### v0.222 — Adlaire UI Framework Expansion

| # | 対象 | 内容 |
|---|------|------|
| 6-1 | `FrameworkCore/Core.php` | `uiFrameworkExpansionPolicy()`を追加 |
| 6-2 | `public_html/assets/adlaire-ui.css` | table、badge、details、section、status layout用classを追加 |

### v0.223 — Release Evidence Bundle

| # | 対象 | 内容 |
|---|------|------|
| 7-1 | `DeploymentCore.php` | `Deployer::releaseEvidenceBundle()`を追加 |
| 7-2 | `DeploymentCore.php` | control reportとrelease gate inputsを統合 |
| 7-3 | `FrameworkCore/Core.php` | `releaseEvidenceBundlePolicy()`を追加 |

### v0.224 — Deployment Control Diff

| # | 対象 | 内容 |
|---|------|------|
| 8-1 | `DeploymentCore.php` | `Deployer::deploymentControlDiff()`を追加 |
| 8-2 | `DeploymentCore.php` | 前回control reportと現在control reportの変更sectionを返す |
| 8-3 | `FrameworkCore/Core.php` | `deploymentControlDiffPolicy()`を追加 |

### v0.225 — Stable Release Candidate Gate

| # | 対象 | 内容 |
|---|------|------|
| 9-1 | `DeploymentCore.php` | `Deployer::stableReleaseCandidateGate()`を追加 |
| 9-2 | `DeploymentCore.php` | compatibility snapshot、rollback preview、deployment safety scoreをRC判定入力にする |
| 9-3 | `FrameworkCore/Core.php` | `stableReleaseCandidateGatePolicy()`を追加 |
| 9-4 | `tests/debug.php` | v0.217-v0.225のポリシーと実動作を公式デバッグテストへ追加 |

---

## v0.226 API Removal仕様

### 基本方針

v0.226は、フレームワーク全体から公開API機能を完全に廃止する。公開API、JSONレスポンス補助、JSONリクエスト補助、CORS補助は提供しない。`public_html/index.php`はHTMLまたは`text/plain`のみを返す。ダッシュボードもHTML操作画面に限定し、JSON出力を提供しない。

JSONは設定ファイルとしては禁止し、内部メタデータ、manifest、履歴、監査、リリース証跡、構造化ログ、Database層の内部libSQL API payload用途に限り許可する。libSQL APIは公開HTTP/JSON APIではなく、DB transportとしてのみ扱う。

### Phase 1 — Core API Removal

| # | 対象 | 内容 |
|---|------|------|
| 1-1 | `FrameworkCore/Core.php` | `Request::isJson()` / `Request::expectsJson()`を削除 |
| 1-2 | `FrameworkCore/Core.php` | JSONリクエストボディパースを削除 |
| 1-3 | `FrameworkCore/Core.php` | `Response::json()` / `success()` / `created()` / `paginated()`を削除 |
| 1-4 | `FrameworkCore/Core.php` | `Response::cors()`を削除 |
| 1-5 | `FrameworkCore/Core.php` | 未捕捉例外レスポンスを`text/plain`へ変更 |

### Phase 2 — Public Entry Removal

| # | 対象 | 内容 |
|---|------|------|
| 2-1 | `public_html/index.php` | `/`をHTML出力へ変更 |
| 2-2 | `public_html/index.php` | `/health`を`text/plain`出力へ変更 |
| 2-3 | `public_html/dashboard.php` | HTML操作画面限定を維持 |

### Phase 3 — Release Gate

| # | 対象 | 内容 |
|---|------|------|
| 3-1 | `FrameworkCore/Core.php` | `apiRemovalPolicy()`を追加 |
| 3-2 | `FrameworkCore/Core.php` | `audit()` / `releaseRequirementMatrix()` / `distributionManifest()` / `releaseReadiness()`へ接続 |
| 3-3 | `tests/debug.php` | API関連メソッド不存在、JSONメタデータ例外維持、内部libSQL API許可、公式デバッグテストを検証 |

---

## v0.227 libSQL API Hardening仕様

### 基本方針

v0.227は、公開API廃止を維持したまま、Database層の内部libSQL API transportを強化する。これはアプリケーション外部へ公開するHTTP/JSON APIではなく、`Database`がlibSQLへ接続するための内部DB transportである。

### Phase 1 — libSQL API Driver

| # | 対象 | 内容 |
|---|------|------|
| 1-1 | `FrameworkCore/Database.php` | `LibSqlApiDriver`を追加 |
| 1-2 | `FrameworkCore/Database.php` | `https:` / `libsql:` URLをlibSQL API endpointとして扱う |
| 1-3 | `FrameworkCore/Database.php` | `wss:` URLをHTTPS API fallbackとして扱う |
| 1-4 | `FrameworkCore/Database.php` | libSQL APIレスポンスのcolumns / rowsを`AdlaireStatement`へ変換 |

### Phase 2 — Runtime Hardening

| # | 対象 | 内容 |
|---|------|------|
| 2-1 | `FrameworkCore/Database.php` | `timeout_seconds`を検証 |
| 2-2 | `FrameworkCore/Database.php` | `retries`を検証し、transport error / 429 / 5xxを再試行対象にする |
| 2-3 | `FrameworkCore/Database.php` | `token_required`でBearer token必須化 |
| 2-4 | `FrameworkCore/Database.php` | `consistency`を`strong` / `eventual`に制限 |
| 2-5 | `FrameworkCore/Database.php` | `transport` callableで外部通信なしの公式テストを可能にする |

### Phase 3 — Release Gate

| # | 対象 | 内容 |
|---|------|------|
| 3-1 | `FrameworkCore/Core.php` | `databaseRuntimeHardeningPolicy()`へ内部libSQL API transportを追加 |
| 3-2 | `FrameworkCore/Core.php` | `apiRemovalPolicy()`で公開API廃止と内部libSQL API許可の境界を明記 |
| 3-3 | `tests/debug.php` | libSQL API payload、Bearer header、runtime profile、WebSocket fallback、token_requiredを検証 |

---

## v0.228 Specification-First Development Workflow仕様

### 基本方針

v0.228は、最高絶対原則として開発順序を固定する。すべての変更は、仕様策定、実装計画、実装の順で進める。仕様が未定義のまま実装しない。実装計画が未定義のまま実装しない。

### Phase 1 — 最高絶対原則への明記

| # | 対象 | 内容 |
|---|------|------|
| 1-1 | `adlaire-ecosystem.md` | 最高絶対原則に明記ルールを追加 |
| 1-2 | `adlaire-ecosystem.md` | 仕様策定、実装計画、実装の必須順序を定義 |
| 1-3 | `adlaire-ecosystem.md` | バグ修正、機能改良、テスト、デバッグ、ドキュメント更新、リリース作業へ適用 |

### Phase 2 — Core Policy

| # | 対象 | 内容 |
|---|------|------|
| 2-1 | `FrameworkCore/Core.php` | `developmentWorkflowPolicy()`を追加 |
| 2-2 | `FrameworkCore/Core.php` | `governancePolicy()`へ開発順序と禁止事項を追加 |
| 2-3 | `FrameworkCore/Core.php` | `audit()` / `distributionManifest()` / `releaseRequirementMatrix()` / `releaseReadiness()`へ接続 |

### Phase 3 — Verification

| # | 対象 | 内容 |
|---|------|------|
| 3-1 | `tests/debug.php` | 仕様策定、実装計画、実装の順序を検証 |
| 3-2 | `tests/debug.php` | 仕様なし実装と計画なし実装が禁止されていることを検証 |
| 3-3 | `tests/debug.php` | 仕様整合性、仕様ドリフト、リリース準備の全ゲートに接続 |

---

## v0.229 Repository-Wide Specification-First Workflow仕様

### 基本方針

v0.229は、v0.228で定義した仕様策定、実装計画、実装の順序をリポジトリ全体に適用する。Framework Coreだけでなく、Deployment Core、公開エントリ、スクリプト、テスト、ドキュメント、Docker関連ファイル、静的アセットを含むすべての変更が対象である。例外パスは設けない。

### Phase 1 — Repository Scope

| # | 対象 | 内容 |
|---|------|------|
| 1-1 | `adlaire-ecosystem.md` | リポジトリ全体適用を最高絶対原則へ明記 |
| 1-2 | `FrameworkCore/Core.php` | `developmentWorkflowPolicy()`へ`repository_wide = true`を追加 |
| 1-3 | `FrameworkCore/Core.php` | `repository_scope`にルートファイル、FrameworkCore、public_html、scripts、tests、storage、Docker関連、仕様書を含める |
| 1-4 | `FrameworkCore/Core.php` | `exempt_paths = []`として例外なしを明記 |

### Phase 2 — Release Gate

| # | 対象 | 内容 |
|---|------|------|
| 2-1 | `FrameworkCore/Core.php` | 仕様整合性でリポジトリ全体適用を検証 |
| 2-2 | `FrameworkCore/Core.php` | リリース要件で例外パスなしを検証 |
| 2-3 | `tests/debug.php` | 公式デバッグテストでリポジトリスコープと例外なしを検証 |

---

## v0.230 Repository Documentation Consistency仕様

### 基本方針

v0.230は、リポジトリ全体のドキュメントを現在の中核方針へ揃える。Xserver本番同等プロファイルは参考運用プロファイルとして維持するが、Xserver本番を前提条件にしない。DB方針はSQLite / 内部libSQL API transportを正式軸とし、MySQL対応予定なしを維持する。設定ファイルはフレームワーク全体で禁止し、JSONは設定ファイルではなく監査、履歴、リリース証跡、ログ、内部DB transport payloadなどの成果物用途に限り許可する。

公開API、JSONレスポンス補助、JSONリクエスト補助、CORS補助は復活させない。Xserver運用ドキュメント、仕様書、リリース検査はこの方針と矛盾してはならない。

### Phase 1 — Documentation Consistency

| # | 対象 | 内容 |
|---|------|------|
| 1-1 | `docs/xserver-production-equivalent.md` | Xserver MySQL-compatible connection記述を削除 |
| 1-2 | `docs/xserver-production-equivalent.md` | `.env`系設定ファイルを許容する記述を削除 |
| 1-3 | `docs/xserver-production-equivalent.md` | SQLite / 内部libSQL API transport、MySQL対応予定なし、サーバ環境変数のみを明記 |
| 1-4 | `README.md` | 仕様書を正とする短い運用入口を作成し、公開API廃止、設定ファイル禁止、SQLite / 内部libSQL API軸を明記 |

### Phase 2 — Release Check

| # | 対象 | 内容 |
|---|------|------|
| 2-1 | `scripts/release-check.sh` | Xserverドキュメントに旧MySQL互換記述が残らないことを検査 |
| 2-2 | `scripts/release-check.sh` | Xserverドキュメントに`.env`系設定ファイル許容記述が残らないことを検査 |
| 2-3 | `scripts/release-check.sh` | 公式デバッグテスト、Xserver profile audit、ドキュメント整合性検査を安定版リリース条件として一括実行 |

---

## v0.231 Deployment Axis Map仕様

v0.231は、物理ファイル移動を行わず、リポジトリ全体の役割だけをデプロイメントシステム軸で固定する。Deployment Core互換性を維持し、現行ダッシュボードは読み取り専用、コマンド実行なし、書き込みなしを維持する。

| 役割 | 対象 | 方針 |
|------|------|------|
| Deployment Core | `DeploymentCore.php` | 中核互換領域、破壊的変更禁止 |
| Deployment Control UI | `public_html/dashboard.php`, `public_html/assets/adlaire-ui.css` | 読み取り専用、実行なし |
| Framework Support | `FrameworkCore/*` | デプロイ軸を支える補助領域 |
| Verification | `tests/debug.php`, `scripts/*` | 公式検証、リリースゲート |
| Specification | `adlaire-ecosystem.md`, `README.md`, `docs/*` | 仕様・運用入口・補足 |

実装は`deploymentAxisMapPolicy()`、`audit()`、`distributionManifest()`、`releaseRequirementMatrix()`、`releaseReadiness()`、`tests/debug.php`へ接続する。任意デプロイ実行は本リリースでは扱わず、別リリースで仕様変更、安全条件、監査ログ、CSRF対策、二段階確認、Deployment Core契約維持を明記してから扱う。

---

## v0.232 Dashboard Deploy Execution Specification仕様

v0.232は、ダッシュボードからの任意デプロイ実行を将来機能として仕様化する。実行実装はまだ有効化しない。

| 項目 | 方針 |
|------|------|
| 既定状態 | OFF |
| 実装状態 | 仕様化のみ、未実装 |
| 公開API | 不要、復活させない |
| 設定ファイル | 追加しない |
| Deployment Core | 互換維持 |
| 必須安全条件 | CSRF、二段階確認、短命実行トークン、承認済みdeploy profile、preflight、plan preview、dry-run、rollback preview、安全スコア70以上、監査ログ |

実装は`dashboardDeployExecutionPolicy()`、`audit()`、`distributionManifest()`、`releaseRequirementMatrix()`、`releaseReadiness()`、`tests/debug.php`へ接続する。実際の実行処理はv0.233以降で安全ゲート、実行アダプタ、監査ログ、UIの順に扱う。

---

## v0.233 Framework Classification Specification仕様

v0.233は、v0.270安定版へ向けた大規模再編の分類を固定する。物理移動はまだ行わず、分類とIntegration Coreの責務を先に確定する。

| 分類 | 現在の対象 | 方針 |
|------|------------|------|
| Deployment Framework | `DeploymentCore.php` | 互換維持、中核デプロイ制御 |
| Backend Framework | `FrameworkCore/Core.php`, `FrameworkCore/Database.php`, `FrameworkCore/Middleware.php` | ルーティング、DB、検証、バックエンド補助 |
| Frontend Framework | `public_html/index.php`, `public_html/dashboard.php` | HTML公開面、ダッシュボード表示 |
| CSS Framework | `public_html/assets/adlaire-ui.css` | UIスタイル基盤 |
| JavaScript Framework | 未実装 | 将来の安全ポリシー下で扱う |
| Integration Core | `FrameworkCore/Core.php`, `FrameworkCore/Kernel.php` | 各分類を登録、監査、連携、リリース判定へ接続 |

v0.233-v0.270は、分類、Integration Core、互換境界、registry、lifecycle、依存関係、物理再配置、ドキュメント再編、リリースゲートを段階的に進める。v0.270を分類別フレームワーク + Integration Core構成の安定版リリースとする。

---

## v0.234 Integration Core Concept仕様

v0.234は、Integration Coreを分類別フレームワークの連携Coreとして定義する。物理移動はまだ行わず、公開APIにも依存しない。

| 責務 | 内容 |
|------|------|
| Registry | Deployment / Backend / Frontend / CSS / JavaScript Frameworkを登録・列挙 |
| Lifecycle | boot / audit / validate / release checkの共通処理を定義 |
| Dependency | フレームワーク間依存を管理 |
| Audit | 分類別監査を統合 |
| Release | release readinessへ統合 |
| Deployment Control | Deployment Frameworkと各分類の状態を接続 |
| Compatibility | Deployment Framework互換境界を維持 |

実装は`integrationCorePolicy()`、`audit()`、`distributionManifest()`、`releaseRequirementMatrix()`、`releaseReadiness()`、`tests/debug.php`へ接続する。
