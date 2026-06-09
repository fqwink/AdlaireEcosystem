# Adlaire Ecosystem

## 概要

制約駆動型の軽量PHPバックエンドフレームワーク。

---

## 最高絶対原則

> 仕様に基づく実装

Adlaire Ecosystemにおける最高絶対原則は、**ドキュメントに定義された仕様に基づいて実装すること**である。

仕様は絶対であり、設計・実装・修正・テスト・デバッグ・ドキュメント更新のすべてにおいて最上位の判断基準とする。仕様外の動作・拡張・妥協・例外解釈を一切許容しない。

仕様と実装に差異がある場合は、仕様を正とし、実装を仕様へ一致させる。仕様が未定義の領域は実装しない。追加実装が必要な場合は、先に仕様を明文化してから実装する。

## 絶対原則

> 仕様に基づく自律的設計思想
>
> フレームワーク全体に適用。仕様が設計の唯一の根拠であり、仕様外の動作・拡張・妥協を一切許容しない。

---

## コア原則

| 原則 | 詳細 |
|------|------|
| **5ファイル原則** | `Core.php` `Database.php` + 予備3ファイルで構成。v0.3で`Deployer.php`が確定（予備枠2）、v0.5で`Logger.php`が確定し予備枠1が残る |
| **外部依存ゼロ** | サードパーティライブラリ・Composer一切不要。ただしlibSQL PHP拡張は任意依存として例外扱い（v0.6以降） |
| **PHP 8.3以降** | 起動時にバージョンチェック、8.2以前は即時エラー終了 |
| **フロントエンド機能なし** | フロントエンド系の機能は一切実装しない |
| **特化型** | 用途・対象環境は非公開 |
| **厳格** | 曖昧な入力・設定を許容しない。型・ルールに反した場合は即時エラー |
| **高速** | 不要な処理を排除、最小限のオーバーヘッドで動作 |
| **段階的拡張** | 確定仕様をベースに改良・新規機能を追加していく |

---

## モジュール仕様

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
| ボディパース | JSON / `multipart/form-data` / `application/x-www-form-urlencoded`。**遅延評価**（初回アクセス時にパース。GETリクエスト等での不要な`php://input`読み取りを排除）（v0.4以降） |
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
| JSON出力 | JSONレスポンスの構築・送出 |
| リダイレクト | 301 / 302 / **307 / 308**（307/308はv0.5以降） |
| キャッシュヘッダー | `Cache-Control` / `ETag` 設定 |
| CORS対応 | CORSヘッダーの一括設定 |
| エラーレスポンス | 統一フォーマットのエラー返却 |
| **共通レスポンス形式** | `success(mixed $data, int $status = 200)` / `paginated(array $result)`（v0.5以降） |

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
| エンジン | SQLite系・libSQL（v0.6以降）。ローカルファイル / インメモリ / libSQL HTTP / libSQL WebSocket |
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

### 6. デプロイメント（Deployer.php）

#### 動作フロー

**初回インストール**

| ステップ | 内容 |
|---------|------|
| 1 | `Deployer.php` を対象サーバーに手動設置 |
| 2 | 設定値を設定（設定管理強化参照） |
| 3 | 初回実行：リポジトリから全ファイルを取得・配置 |

**自律アップデートサイクル**

| ステップ | 内容 |
|---------|------|
| 1 | 起動トリガー（cron or HTTP呼び出し） |
| 2 | GitHub SSH + `git archive` でtar形式取得・展開（Gitコマンド・Git APIはケースバイケースで併用可。`git pull` 除く） |
| 3 | ローカルファイルとハッシュ比較・差分リスト生成 |
| 4 | 差分ファイルをバックアップ後に適用（`Deployer.php` 自身も対象） |
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
| **HTTPトリガー判定** | `PHP_SAPI`が`cli`および`cli-server`の場合はHTTPトリガー検証をスキップ。それ以外は常にHMAC署名・IP検証を適用（v0.4以降） |

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

v0.5以降は`Logger.php`に集約済み。`Deployer.php`は共通`Logger`を直接利用する。

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
| **未捕捉例外ハンドラ** | `Adlaire::init()`内で`set_exception_handler()`を設定。未捕捉例外をJSON形式で返却し、PHPデフォルトのHTML出力を防ぐ。本番環境では詳細を隠蔽し、開発環境ではスタックトレースを含める（v0.4以降） |

---

## ファイル構成

```
Core.php        # フレームワーク本体
Database.php    # データベース
Deployer.php    # デプロイメント
Logger.php      # ログ基盤（デプロイ・アプリ・デバッグログ統合）
（予備）         # 未使用
```

---

## バージョン

| バージョン | 内容 |
|-----------|------|
| v0.1 | 初期リリース：ルーティング・リクエスト・レスポンス・バリデーション |
| v0.2 | 仕様準拠開発：ルートパラメータ・名前付きルート・制約・RESTリソース・フォーム/ファイル/Bearer・レスポンス補助・高度バリデーション・SQLiteデータベース基盤 |
| v0.3 | Deployer.php追加・バリデーション拡張・DB拡張 |
| v0.4 | **実装済み**（バグ修正・セキュリティ・信頼性・パフォーマンス・堅牢性・機能追加） |
| v0.5 | **実装済み**（`Logger.php`新設・ページネーション・共通レスポンス形式・リダイレクト拡張） |
| v0.6 | **実装済み**（libSQL対応：HTTP / WebSocket・複数接続方式） |
| v0.7 | **実装済み**（実運用堅牢化：テスト基盤・Logger強化・Database堅牢化・Core運用機能・Deployer安全性強化） |
| v0.8 | **実装済み**（開発者体験強化：設定取得・テスト補助・デバッグ出力・DB補助・ルーティング確認） |

---

## v0.4 実装済み仕様

### Phase 1 — バグ修正

| # | ファイル | 対象 | 内容 |
|---|---------|------|------|
| 1-1 | `Database.php` | `QueryBuilder::update()` | WHEREなし全件更新を`RuntimeException`で阻止。全件許可は`allowWithoutWhere()`を明示チェーン |
| 1-2 | `Database.php` | `QueryBuilder::delete()` | WHEREなし全件削除を`RuntimeException`で阻止。全件許可は`allowWithoutWhere()`を明示チェーン |
| 1-3 | `Deployer.php` | `Deployer::backup()` | スナップショット保存時に`manifest.json`（デプロイ前ファイル一覧）を記録 |
| 1-4 | `Deployer.php` | `Deployer::rollbackLatest()` | `manifest.json`との差分で新規追加ファイルを特定・削除。完全復元を保証 |
| 1-5 | `Deployer.php` | `Deployer::healthCheck()` | `PHP_BINARY`未検出時は`token_get_all()`フォールバックを使わず即時例外 |
| 1-6 | `Deployer.php` | `Deployer::verifyHttpTrigger()` | `cli-server`を`cli`と同様にHTTPトリガー検証スキップ対象へ明示追加 |

### Phase 2 — セキュリティ・信頼性

| # | ファイル | 対象 | 内容 |
|---|---------|------|------|
| 2-1 | `Core.php` | `Request::parseIp()` | 信頼プロキシIPホワイトリスト対応。`Adlaire::init()`に`trustedProxies`設定を追加。未設定時は`REMOTE_ADDR`のみ使用 |
| 2-2 | `Deployer.php` | `DeployConfig::validate()` | `phar.readonly`が`On`の場合は即時エラー終了 |
| 2-3 | `Logger.php` | `Logger::__construct()` | `hmacKey`未設定時にWARNINGレベルのログを出力。`Deployer.php`からも共通利用 |
| 2-4 | `Core.php` | `Adlaire::init()` | `set_exception_handler()`を設定。未捕捉例外をJSON形式で返却。`APP_ENV`環境変数で本番/開発を切り替え |

### Phase 3 — パフォーマンス

| # | ファイル | 対象 | 内容 |
|---|---------|------|------|
| 3-1 | `Core.php` | `Router::addRoute()` | ルート登録時にPCREパターンをコンパイル・キャッシュ。`$routes`に`pattern`・`paramNames`を保持 |
| 3-2 | `Core.php` | `Router::dispatch()` | パラメータなし静的ルートをハッシュマップ（`method#uri`をキー）で先引き。O(1)照合後にパラメータありルートへフォールバック |
| 3-3 | `Core.php` | `Request::parseBody()` | コンストラクタでの即時実行を廃止。`body()`/`input()`初回呼び出し時に遅延パース |
| 3-4 | `Deployer.php` | `Deployer::files()` | `$this->fileCache`にキャッシュ。`diff()`・`healthCheck()`で共有 |
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

デプロイ・アプリケーション・デバッグのログ出力を`Logger`に集約する。`Deployer.php`は共通`Logger`を直接利用する。リクエスト・レスポンス・クエリ・ルーティング・エラーの情報を1リクエスト1行のJSONログとして記録するデバッグ機能を含む。デバッグ記録は`APP_ENV=development`時のみ有効。本番では一切記録しない。

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
| Deployer連携 | `Deployer.php`は`Logger`を直接利用 |
| 独立性 | `Core.php`・`Database.php`への変更は最小限。`Logger`が各クラスから情報を取得する設計 |

### 追加機能

#### ページネーション（Database.php）

`limit()`・`offset()`の延長。ページ番号からLIMIT/OFFSETを自動計算する。

| 項目 | 内容 |
|------|------|
| メソッド | `QueryBuilder::paginate(int $perPage, int $page = 1)` |
| 戻り値 | `data` / `total` / `per_page` / `current_page` / `last_page` |
| 内部実装 | `count()`で総件数取得後、LIMIT/OFFSETを自動計算して`get()` |

#### 共通レスポンス形式（Core.php）

エラーレスポンスは`error()`で統一済み。成功側を統一する。

| 項目 | 内容 |
|------|------|
| `Response::success(mixed $data, int $status = 200)` | `{'data': ...}` 形式でJSON出力 |
| `Response::paginated(array $result)` | `paginate()`の戻り値をそのままJSON出力 |

#### リダイレクト拡張（Core.php）

`Response::redirect()`に307/308を追加し、4種のリダイレクトに対応する。

| 項目 | 内容 |
|------|------|
| `Response::redirect()` | 307 / 308 を許容ステータスに追加。301/302と合わせて4種対応 |

---

## v0.6 実装済み仕様

### libSQL対応（Database.php）

#### libSQL追加の基本方針

`Database.php`にlibSQL接続方式を追加する。既存のSQLite（PDO）はそのまま維持し、libSQL HTTP・WebSocketを新たな接続方式として追加する。libSQL PHP拡張は任意依存とし、未インストール時はHTTP方式にフォールバックする。

#### 接続方式

| 方式 | 接続文字列形式 | ドライバ | 外部依存 |
|------|--------------|---------|---------|
| ローカルファイル | `file:path/to/db.sqlite`（v0.6で新形式追加。既存のパス直指定も継続サポート） | `pdo_sqlite` | なし |
| インメモリ | `:memory:` | `pdo_sqlite` | なし |
| libSQL HTTP | `https://db.example.com` | curl拡張（PHP標準） | なし |
| libSQL WebSocket | `wss://db.example.com` | libSQL PHP拡張（任意） | libSQL PHP拡張 |

#### フォールバック方針

| 条件 | 動作 |
|------|------|
| libSQL PHP拡張インストール済み | WebSocket方式を使用 |
| libSQL PHP拡張未インストール・WebSocket指定時 | HTTP方式にフォールバック |
| フォールバック発生時 | WARNINGログを出力（v0.5以降の`Logger`と連携） |

#### 外部依存原則の扱い

コア原則「外部依存ゼロ」の例外としてlibSQL PHP拡張を任意依存に位置付ける。curl拡張はPHP標準拡張のため原則の範囲内。詳細はコア原則を参照。

#### 認証

| 項目 | 内容 |
|------|------|
| 認証方式 | DB接続用Bearerトークン（`Authorization: Bearer <token>`）。`Deployer.php`のHMAC署名検証とは用途が異なる |
| トークン設定 | `Database::addConnection()`の`token`パラメータで指定 |
| トークン保持 | 接続インスタンス内で保持。ログ・クエリログには出力しない |

#### API設計

| 項目 | 内容 |
|------|------|
| 接続設定 | `Database::addConnection(string $name, string $url, bool $default, ?string $token)` へ変更。既存の`$path`引数を`$url`に改名しlibSQL URLも受け付ける。`token`引数を追加 |
| 方式自動判定 | URLスキームで自動判定。`file:`・`:memory:` → PDO、`https:` → HTTP、`wss:` → WebSocket（拡張あり）or HTTPフォールバック |
| クエリ実行 | `QueryBuilder`はそのまま使用。接続方式の差異は`Database`内部で吸収 |
| トランザクション | HTTP・WebSocket方式でもSAVEPOINTによるネストトランザクションを維持 |
| クエリログ | 接続方式にかかわらず既存の`enableQueryLog()`で統一管理 |

#### libSQL実装方針
| 追加クラス | `LibSqlDriver`インターフェースを`Database.php`内に定義。`PdoDriver`・`HttpDriver`・`WebSocketDriver`で実装 |
| `QueryBuilder`への影響 | なし。`Database`がドライバの差異を吸収する設計 |
| 起動時チェック | HTTP方式使用時はcurl拡張の有効性を確認。無効時は即時例外 |

---

## v0.7 実装済み仕様

### 基本方針

v0.7は実運用堅牢化リリースとする。新規ファイルは追加せず、既存の`Core.php`・`Database.php`・`Deployer.php`・`Logger.php`・`tests/debug.php`を対象に、運用時の安全性・観測性・テスト性を強化する。

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
| 4-1 | `Core.php` | OPTIONS | CORSプリフライト用にOPTIONSメソッドをルーティング対象へ追加 |
| 4-2 | `Core.php` | 405 | 405応答時の`Allow`ヘッダーにOPTIONSを含める |
| 4-3 | `Core.php` | `Adlaire::init()` | `trustedProxies` / `logger`設定項目を公式設定として扱う |
| 4-4 | `Core.php` | Logger連携 | 404 / 405 のルーティング失敗情報をLoggerへ渡す |

### Phase 5 — Deployer安全性強化

| # | ファイル | 対象 | 内容 |
|---|---------|------|------|
| 5-1 | `Deployer.php` | 失敗時rollback | 今回のデプロイでスナップショット作成後に失敗した場合のみロールバックする |
| 5-2 | `Deployer.php` | 初回デプロイ | `target_dir`未作成時は空ターゲットとして扱い、バックアップmanifestを作成する |
| 5-3 | `Deployer.php` | 履歴 | deploy履歴に`phase`と`status`を記録する |
| 5-4 | `Deployer.php` | apply失敗ログ | apply中に失敗したファイル名をログへ記録する |
| 5-5 | `Deployer.php` | allowlist | `deploy_allowlist`未設定時は全ファイル許可、設定時は一致ファイルのみ許可する挙動をテストで固定する |

---

## v0.8 実装済み仕様

### 基本方針

v0.8は開発者体験強化リリースとする。外部依存ゼロと5ファイル原則を維持し、既存APIを壊さずに、設定取得・テスト補助・デバッグ出力・DB補助・ルーティング確認を追加する。

### Phase 1 — Core設定取得

| # | ファイル | 対象 | 内容 |
|---|---------|------|------|
| 1-1 | `Core.php` | `Adlaire::config()` | `Adlaire::init(array $config)`で受け取った設定をドット記法で取得可能にする |
| 1-2 | `Core.php` | `Adlaire::env()` | 環境変数を型変換つきで取得する。`true` / `false` / 数値文字列をPHP型へ変換 |
| 1-3 | `Core.php` | `Request::isJson()` | `Content-Type`がJSONか判定 |
| 1-4 | `Core.php` | `Request::expectsJson()` | `Accept`ヘッダーがJSONを期待するか判定 |

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
| 5-2 | `Deployer.php` | `Deployer::validateOnly()` | fetch/applyを行わず、設定・ロック・ヘルスチェック前提条件を検証する |
| 5-3 | `tests/debug.php` | v0.8テスト | Core設定、Router確認、DB補助、Logger component派生、Deployer validateOnlyを検証 |
