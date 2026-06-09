<?php

/**
 * Adlaire Ecosystem - Database.php
 *
 * @version v0.230
 * @php     >= 8.3
 */

declare(strict_types=1);

if (PHP_VERSION_ID < 80300) {
    echo 'Adlaire Ecosystem requires PHP 8.3 or higher. Current version: ' . PHP_VERSION;
    exit(1);
}

interface DatabaseDriver
{
    public function execute(string $sql, array $bindings = []): AdlaireStatement;

    public function exec(string $sql): void;

    public function runtimeProfile(): array;

    public function beginTransaction(): void;

    public function commit(): void;

    public function rollBack(): void;

    public function inTransaction(): bool;
}

final class AdlaireStatement
{
    private int $cursor = 0;

    public function __construct(
        private array $rows = [],
        private int $rowCount = 0
    ) {
    }

    public static function fromPdo(PDOStatement $statement): self
    {
        $rows = $statement->columnCount() > 0 ? $statement->fetchAll(PDO::FETCH_ASSOC) : [];
        return new self(is_array($rows) ? $rows : [], $statement->rowCount());
    }

    public function fetch(int $mode = PDO::FETCH_ASSOC): mixed
    {
        if ($this->cursor >= count($this->rows)) {
            return false;
        }
        $row = $this->rows[$this->cursor++];
        if ($mode === PDO::FETCH_COLUMN) {
            return is_array($row) ? reset($row) : false;
        }
        return $row;
    }

    public function fetchAll(int $mode = PDO::FETCH_ASSOC): array
    {
        if ($mode === PDO::FETCH_COLUMN) {
            return array_map(static fn(array $row): mixed => reset($row), $this->rows);
        }
        return $this->rows;
    }

    public function rowCount(): int
    {
        return $this->rowCount;
    }
}

final class PdoDriver implements DatabaseDriver
{
    private PDO $pdo;
    private array $runtimeProfile = [];

    public function __construct(string $path, array $options = [])
    {
        $this->pdo = new PDO('sqlite:' . $path);
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $this->configureSqlite($path, $options);
    }

    public function pdo(): PDO
    {
        return $this->pdo;
    }

    public function runtimeProfile(): array
    {
        return $this->runtimeProfile;
    }

    public function execute(string $sql, array $bindings = []): AdlaireStatement
    {
        $statement = $this->pdo->prepare($sql);
        if (!$statement instanceof PDOStatement) {
            throw new RuntimeException('Failed to prepare SQL statement.');
        }
        $statement->execute($bindings);
        return AdlaireStatement::fromPdo($statement);
    }

    public function exec(string $sql): void
    {
        $result = $this->pdo->exec($sql);
        if ($result === false) {
            throw new RuntimeException('Failed to execute SQL statement.');
        }
    }

    public function beginTransaction(): void
    {
        $this->pdo->beginTransaction();
    }

    public function commit(): void
    {
        $this->pdo->commit();
    }

    public function rollBack(): void
    {
        $this->pdo->rollBack();
    }

    public function inTransaction(): bool
    {
        return $this->pdo->inTransaction();
    }

    private function configureSqlite(string $path, array $options): void
    {
        $foreignKeys = $options['foreign_keys'] ?? true;
        $busyTimeoutMs = (int)($options['busy_timeout_ms'] ?? 5000);
        $journalMode = $options['journal_mode'] ?? ($path === ':memory:' ? null : 'WAL');
        $synchronous = $options['synchronous'] ?? ($journalMode === null ? null : 'NORMAL');

        if ($foreignKeys !== false) {
            $this->pdo->exec('PRAGMA foreign_keys = ON');
        }

        if ($busyTimeoutMs < 0) {
            throw new InvalidArgumentException('SQLite busy_timeout_ms must be zero or greater.');
        }
        $this->pdo->exec('PRAGMA busy_timeout = ' . $busyTimeoutMs);

        if (is_string($journalMode) && $journalMode !== '') {
            $this->assertPragmaToken($journalMode, 'journal_mode');
            $this->pdo->exec('PRAGMA journal_mode = ' . strtoupper($journalMode));
        }

        if (is_string($synchronous) && $synchronous !== '') {
            $this->assertPragmaToken($synchronous, 'synchronous');
            $this->pdo->exec('PRAGMA synchronous = ' . strtoupper($synchronous));
        }

        $this->runtimeProfile = [
            'driver' => 'sqlite',
            'path' => $path,
            'foreign_keys' => $this->pragmaValue('foreign_keys') === '1',
            'busy_timeout_ms' => (int)$this->pragmaValue('busy_timeout'),
            'journal_mode' => strtolower($this->pragmaValue('journal_mode')),
            'synchronous' => $this->pragmaValue('synchronous'),
        ];
    }

    private function pragmaValue(string $name): string
    {
        $statement = $this->pdo->query('PRAGMA ' . $name);
        if (!$statement instanceof PDOStatement) {
            return '';
        }
        $value = $statement->fetchColumn();
        return is_scalar($value) ? (string)$value : '';
    }

    private function assertPragmaToken(string $value, string $name): void
    {
        if (preg_match('/^[A-Za-z_]+$/', $value) !== 1) {
            throw new InvalidArgumentException("Invalid SQLite {$name}: {$value}");
        }
    }
}

class LibSqlApiDriver implements DatabaseDriver
{
    private bool $inTransaction = false;
    private string $apiUrl;
    private int $timeoutSeconds;
    private int $retries;
    private bool $tokenRequired;
    private string $consistency;
    private string $userAgent;
    private mixed $transport;

    public function __construct(
        private string $url,
        private ?string $token = null,
        array $options = []
    ) {
        $apiPath = trim((string)($options['api_path'] ?? '/v2/pipeline'));
        if ($apiPath === '') {
            throw new InvalidArgumentException('libSQL API path must not be empty.');
        }
        $this->apiUrl = '/' . ltrim(rtrim($apiPath, '/'), '/');
        $this->timeoutSeconds = (int)($options['timeout_seconds'] ?? 30);
        $this->retries = (int)($options['retries'] ?? 0);
        $this->tokenRequired = (bool)($options['token_required'] ?? false);
        $this->consistency = (string)($options['consistency'] ?? 'strong');
        $this->userAgent = (string)($options['user_agent'] ?? 'AdlaireEcosystem/libsql-api');
        $this->transport = $options['transport'] ?? null;

        if (!in_array($this->consistency, ['strong', 'eventual'], true)) {
            throw new InvalidArgumentException('libSQL consistency must be strong or eventual.');
        }
        if ($this->timeoutSeconds < 1) {
            throw new InvalidArgumentException('libSQL API timeout_seconds must be at least 1.');
        }
        if ($this->retries < 0) {
            throw new InvalidArgumentException('libSQL API retries must be zero or greater.');
        }
        if ($this->tokenRequired && ($this->token === null || $this->token === '')) {
            throw new InvalidArgumentException('libSQL API token is required for this connection profile.');
        }
        if ($this->transport !== null && !is_callable($this->transport)) {
            throw new InvalidArgumentException('libSQL API transport option must be callable.');
        }
        if ($this->transport === null && !extension_loaded('curl')) {
            throw new RuntimeException('curl extension is required for libSQL API connections.');
        }
    }

    public function runtimeProfile(): array
    {
        return [
            'driver' => 'libsql-api',
            'url' => $this->url,
            'api_path' => $this->apiUrl,
            'timeout_seconds' => $this->timeoutSeconds,
            'retries' => $this->retries,
            'token_configured' => $this->token !== null && $this->token !== '',
            'token_required' => $this->tokenRequired,
            'consistency' => $this->consistency,
            'custom_transport' => $this->transport !== null,
        ];
    }

    public function execute(string $sql, array $bindings = []): AdlaireStatement
    {
        return $this->statementFromResponse($this->request($sql, $bindings));
    }

    public function exec(string $sql): void
    {
        $this->execute($sql);
    }

    public function beginTransaction(): void
    {
        $this->execute('BEGIN');
        $this->inTransaction = true;
    }

    public function commit(): void
    {
        $this->execute('COMMIT');
        $this->inTransaction = false;
    }

    public function rollBack(): void
    {
        $this->execute('ROLLBACK');
        $this->inTransaction = false;
    }

    public function inTransaction(): bool
    {
        return $this->inTransaction;
    }

    protected function request(string $sql, array $bindings): array
    {
        $payload = $this->encodePayload($sql, $bindings);
        $headers = [
            'Content-Type: application/json',
            'Accept: application/json',
            'User-Agent: ' . $this->userAgent,
            'X-Adlaire-DB-Consistency: ' . $this->consistency,
        ];
        if ($this->token !== null && $this->token !== '') {
            $headers[] = 'Authorization: Bearer ' . $this->token;
        }

        $attempt = 0;
        do {
            if ($attempt > 0) {
                usleep(min(250000, 50000 * $attempt));
            }
            $response = $this->send($payload, $headers);
            $attempt++;
        } while ($this->shouldRetry($response['status'], $attempt));

        if ($response['status'] < 200 || $response['status'] >= 300) {
            throw new RuntimeException('libSQL API request failed: ' . $this->classifyFailure($response['status'], $response['body']));
        }

        $decoded = json_decode($response['body'], true);
        if (!is_array($decoded)) {
            throw new RuntimeException('libSQL API response must be JSON.');
        }
        return $decoded;
    }

    private function encodePayload(string $sql, array $bindings): string
    {
        return json_encode([
            'statements' => [[
                'q' => $sql,
                'params' => array_values($bindings),
            ]],
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    private function send(string $payload, array $headers): array
    {
        $endpoint = rtrim($this->url, '/') . $this->apiUrl;
        if ($this->transport !== null) {
            $result = ($this->transport)($endpoint, $payload, $headers, $this->timeoutSeconds);
            if (!is_array($result) || !isset($result['status'], $result['body'])) {
                throw new RuntimeException('libSQL API transport must return status and body.');
            }
            return ['status' => (int)$result['status'], 'body' => (string)$result['body']];
        }

        $curl = curl_init($endpoint);
        if ($curl === false) {
            throw new RuntimeException('Failed to initialize libSQL API curl transport.');
        }
        curl_setopt_array($curl, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $this->timeoutSeconds,
        ]);

        $body = curl_exec($curl);
        $status = curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        $error = curl_error($curl);
        curl_close($curl);

        if ($body === false) {
            return ['status' => 0, 'body' => $error !== '' ? $error : 'transport error'];
        }
        return ['status' => (int)$status, 'body' => (string)$body];
    }

    private function shouldRetry(int $status, int $attempt): bool
    {
        return $attempt <= $this->retries && ($status === 0 || $status === 429 || $status >= 500);
    }

    private function classifyFailure(int $status, string $body): string
    {
        if ($status === 0) {
            return 'transport error: ' . $body;
        }
        if ($status === 401 || $status === 403) {
            return "authentication failed with HTTP {$status}";
        }
        if ($status === 429) {
            return 'rate limited with HTTP 429';
        }
        if ($status >= 500) {
            return "remote server error HTTP {$status}: {$body}";
        }
        if ($status >= 400) {
            return "request rejected HTTP {$status}: {$body}";
        }
        return $body !== '' ? $body : 'unknown libSQL API failure';
    }

    protected function statementFromResponse(array $response): AdlaireStatement
    {
        $result = $response['results'][0] ?? $response['result'] ?? $response;
        $rows = [];
        foreach (($result['rows'] ?? []) as $row) {
            if (is_array($row) && array_is_list($row) && isset($result['columns']) && is_array($result['columns'])) {
                $assoc = [];
                foreach ($result['columns'] as $index => $column) {
                    $name = is_array($column) ? (string)($column['name'] ?? $index) : (string)$column;
                    $assoc[$name] = $row[$index] ?? null;
                }
                $rows[] = $assoc;
            } elseif (is_array($row)) {
                $rows[] = $row;
            }
        }

        return new AdlaireStatement($rows, (int)($result['affected_row_count'] ?? $result['rows_affected'] ?? count($rows)));
    }
}

final class LibSqlWebSocketDriver extends LibSqlApiDriver
{
    public function runtimeProfile(): array
    {
        return array_replace(parent::runtimeProfile(), ['driver' => 'libsql-websocket-fallback']);
    }
}

final class Database
{
    private static array $connections = [];
    private static ?string $defaultConnection = null;
    private static bool $usedConnect = false;
    private DatabaseDriver $driver;
    private ?PDO $pdo = null;
    private int $transactionDepth = 0;
    private bool $queryLogging = false;
    private ?float $slowQueryThresholdMs = null;
    private int $queryLogMaxEntries = 1000;
    private array $queryLog = [];

    public function __construct(string $url, ?string $token = null, array $options = [])
    {
        $this->driver = $this->createDriver($url, $token, $options);
        if ($this->driver instanceof PdoDriver) {
            $this->pdo = $this->driver->pdo();
        }
    }

    public static function addConnection(string $name, string $url, bool $default = false, ?string $token = null, array $options = []): self
    {
        if (self::$usedConnect) {
            throw new RuntimeException('Database::connect() cannot be mixed with addConnection().');
        }
        if ($name === '') {
            throw new InvalidArgumentException('Connection name must not be empty.');
        }

        $database = new self($url, $token, $options);
        self::$connections[$name] = $database;

        if ($default || self::$defaultConnection === null) {
            self::$defaultConnection = $name;
        }

        return $database;
    }

    public static function connection(?string $name = null): self
    {
        $name ??= self::$defaultConnection;
        if ($name === null || !isset(self::$connections[$name])) {
            throw new RuntimeException('Database connection is not configured.');
        }
        return self::$connections[$name];
    }

    public static function default(): self
    {
        return self::connection();
    }

    public static function resetConnectionsForTesting(): void
    {
        self::$connections = [];
        self::$defaultConnection = null;
        self::$usedConnect = false;
    }

    public static function fromConfig(array|object $config, ?string $name = null): self
    {
        $connectionName = $name ?? (string)self::configValue($config, 'name', 'default');
        $url = self::configValue($config, 'url', self::configValue($config, 'database_url', null));
        if (!is_string($url) || $url === '') {
            throw new InvalidArgumentException('Database config requires url or database_url.');
        }

        $token = self::configValue($config, 'token', self::configValue($config, 'database_token', null));
        $default = (bool)self::configValue($config, 'default', false);
        $options = self::configValue($config, 'options', []);
        if (!is_array($options)) {
            throw new InvalidArgumentException('Database config options must be an array.');
        }

        return self::addConnection($connectionName, $url, $default, is_scalar($token) ? (string)$token : null, $options);
    }

    public static function connect(string $path): self
    {
        if (self::$connections !== [] && !self::$usedConnect) {
            throw new RuntimeException('Database::connect() cannot be mixed with addConnection().');
        }
        self::$usedConnect = true;
        $database = new self($path);
        if (self::$defaultConnection === null) {
            self::$connections['default'] = $database;
            self::$defaultConnection = 'default';
        }
        return $database;
    }

    public function pdo(): PDO
    {
        if (!$this->pdo instanceof PDO) {
            throw new RuntimeException('PDO is only available for local SQLite connections.');
        }
        return $this->pdo;
    }

    public function runtimeProfile(): array
    {
        return $this->driver->runtimeProfile();
    }

    public function table(string $table): QueryBuilder
    {
        return new QueryBuilder($this, $table);
    }

    public function statement(string $sql, array $bindings = []): AdlaireStatement
    {
        return $this->execute($sql, $bindings);
    }

    public function execute(string $sql, array $bindings = []): AdlaireStatement
    {
        $start = microtime(true);
        $statement = $this->driver->execute($sql, $bindings);
        $durationMs = (microtime(true) - $start) * 1000;

        if ($this->queryLogging || ($this->slowQueryThresholdMs !== null && $durationMs >= $this->slowQueryThresholdMs)) {
            $this->queryLog[] = [
                'sql' => $sql,
                'bindings' => $bindings,
                'duration_ms' => $durationMs,
                'slow' => $this->slowQueryThresholdMs !== null && $durationMs >= $this->slowQueryThresholdMs,
                'timestamp' => date('c'),
            ];
            if (count($this->queryLog) > $this->queryLogMaxEntries) {
                $this->queryLog = array_slice($this->queryLog, -$this->queryLogMaxEntries);
            }
        }

        return $statement;
    }

    public function enableQueryLog(?float $slowQueryThresholdMs = null, int $maxEntries = 1000): static
    {
        if ($maxEntries < 1) {
            throw new InvalidArgumentException('Query log max entries must be at least 1.');
        }
        $this->queryLogging = true;
        $this->slowQueryThresholdMs = $slowQueryThresholdMs;
        $this->queryLogMaxEntries = $maxEntries;
        return $this;
    }

    public function disableQueryLog(): static
    {
        $this->queryLogging = false;
        $this->slowQueryThresholdMs = null;
        return $this;
    }

    public function queryLog(): array
    {
        return $this->queryLog;
    }

    public function clearQueryLog(): void
    {
        $this->queryLog = [];
    }

    public function transaction(callable $callback): mixed
    {
        $this->beginTransaction();
        try {
            $result = $callback($this);
            $this->commit();
            return $result;
        } catch (Throwable $exception) {
            $this->rollbackTransaction();
            throw $exception;
        }
    }

    private function beginTransaction(): void
    {
        if ($this->transactionDepth === 0) {
            $this->driver->beginTransaction();
        } else {
            $this->driver->exec('SAVEPOINT adlaire_tx_' . $this->transactionDepth);
        }
        $this->transactionDepth++;
    }

    private function commit(): void
    {
        if ($this->transactionDepth <= 0) {
            throw new RuntimeException('No active transaction to commit.');
        }

        if ($this->transactionDepth === 1) {
            $this->driver->commit();
            $this->transactionDepth = 0;
            return;
        }

        $savepoint = $this->transactionDepth - 1;
        $this->driver->exec('RELEASE SAVEPOINT adlaire_tx_' . $savepoint);
        $this->transactionDepth--;
    }

    private function rollbackTransaction(): void
    {
        if ($this->transactionDepth <= 0) {
            return;
        }

        $this->transactionDepth--;
        if ($this->transactionDepth === 0) {
            if ($this->driver->inTransaction()) {
                $this->driver->rollBack();
            }
            return;
        }

        $this->driver->exec('ROLLBACK TO SAVEPOINT adlaire_tx_' . $this->transactionDepth);
        $this->driver->exec('RELEASE SAVEPOINT adlaire_tx_' . $this->transactionDepth);
    }

    public function migrate(string $directory): void
    {
        (new Migrator($this))->migrate($directory);
    }

    public function rollback(string $directory, int $steps = 1): void
    {
        (new Migrator($this))->rollback($directory, $steps);
    }

    private function createDriver(string $url, ?string $token, array $options): DatabaseDriver
    {
        if ($url === ':memory:' || str_starts_with($url, 'file:') || !preg_match('/^[A-Za-z][A-Za-z0-9+.-]*:/', $url)) {
            $path = str_starts_with($url, 'file:') ? substr($url, 5) : $url;
            $this->assertDatabasePath($path);
            return new PdoDriver($path, $options['sqlite'] ?? $options);
        }

        $libSqlOptions = $options['libsql'] ?? $options;
        if (str_starts_with($url, 'https://')) {
            return new LibSqlApiDriver($url, $token, $libSqlOptions);
        }
        if (str_starts_with($url, 'libsql://')) {
            return new LibSqlApiDriver('https://' . substr($url, 9), $token, $libSqlOptions);
        }
        if (str_starts_with($url, 'wss://')) {
            return new LibSqlWebSocketDriver('https://' . substr($url, 6), $token, $libSqlOptions);
        }

        throw new InvalidArgumentException("Unsupported database URL: {$url}");
    }

    private function assertDatabasePath(string $path): void
    {
        if ($path === ':memory:') {
            return;
        }

        $directory = dirname($path);
        if ($directory !== '' && $directory !== '.' && !is_dir($directory)) {
            throw new InvalidArgumentException("Database directory does not exist: {$directory}");
        }
    }

    private static function configValue(array|object $config, string $key, mixed $default = null): mixed
    {
        if (is_array($config)) {
            return $config[$key] ?? $default;
        }
        if (method_exists($config, 'get')) {
            return $config->get($key, $default);
        }
        return property_exists($config, $key) ? $config->{$key} : $default;
    }
}

final class QueryBuilder
{
    private array $columns = ['*'];
    private array $selectBindings = [];
    private array $wheres = [];
    private array $joins = [];
    private array $orders = [];
    private ?int $limit = null;
    private ?int $offset = null;
    private array $bindings = [];
    private array $unions = [];
    private array $eagerLoads = [];
    private bool $allowWithoutWhere = false;

    public function __construct(
        private Database $database,
        private string $table
    ) {
        $this->assertIdentifier($table);
    }

    public function select(array|string $columns = ['*'], string ...$moreColumns): static
    {
        $columns = is_array($columns) ? $columns : [$columns, ...$moreColumns];
        if ($columns === []) {
            throw new InvalidArgumentException('Select columns must not be empty.');
        }
        foreach ($columns as $column) {
            if ($column !== '*') {
                $this->assertIdentifier((string)$column, true);
            }
        }
        $this->columns = $columns;
        return $this;
    }

    public function selectRaw(string $expression, array $bindings = []): static
    {
        if ($expression === '') {
            throw new InvalidArgumentException('Raw select expression must not be empty.');
        }
        $this->columns = [$expression];
        array_push($this->selectBindings, ...array_values($bindings));
        return $this;
    }

    public function where(string $column, mixed $operator, mixed $value = null): static
    {
        if (func_num_args() === 2) {
            $value = $operator;
            $operator = '=';
        }
        return $this->addWhere('AND', $column, (string)$operator, $value);
    }

    public function orWhere(string $column, mixed $operator, mixed $value = null): static
    {
        if (func_num_args() === 2) {
            $value = $operator;
            $operator = '=';
        }
        return $this->addWhere('OR', $column, (string)$operator, $value);
    }

    public function whereIn(string $column, array $values): static
    {
        $this->assertIdentifier($column, true);
        if ($values === []) {
            $this->wheres[] = ['AND', '1 = 0'];
            return $this;
        }

        $placeholders = implode(', ', array_fill(0, count($values), '?'));
        $this->wheres[] = ['AND', "{$column} IN ({$placeholders})"];
        array_push($this->bindings, ...array_values($values));
        return $this;
    }

    public function whereNull(string $column): static
    {
        $this->assertIdentifier($column, true);
        $this->wheres[] = ['AND', "{$column} IS NULL"];
        return $this;
    }

    public function whereNotNull(string $column): static
    {
        $this->assertIdentifier($column, true);
        $this->wheres[] = ['AND', "{$column} IS NOT NULL"];
        return $this;
    }

    public function whereBetween(string $column, mixed $min, mixed $max): static
    {
        $this->assertIdentifier($column, true);
        $this->wheres[] = ['AND', "{$column} BETWEEN ? AND ?"];
        $this->bindings[] = $min;
        $this->bindings[] = $max;
        return $this;
    }

    public function whereRaw(string $expression, array $bindings = []): static
    {
        if ($expression === '') {
            throw new InvalidArgumentException('Raw where expression must not be empty.');
        }
        $this->wheres[] = ['AND', '(' . $expression . ')'];
        array_push($this->bindings, ...array_values($bindings));
        return $this;
    }

    public function whereSub(string $column, string $operator, QueryBuilder $query): static
    {
        $this->assertIdentifier($column, true);
        $operator = strtoupper($operator);
        if (!in_array($operator, ['=', '!=', '<>', '<', '<=', '>', '>=', 'IN', 'NOT IN'], true)) {
            throw new InvalidArgumentException("Unsupported subquery operator: {$operator}");
        }

        [$sql, $bindings] = $query->toSql();
        $this->wheres[] = ['AND', "{$column} {$operator} ({$sql})"];
        array_push($this->bindings, ...$bindings);
        return $this;
    }

    public function join(string $table, string $first, string $operator, string $second): static
    {
        return $this->addJoin('INNER JOIN', $table, $first, $operator, $second);
    }

    public function leftJoin(string $table, string $first, string $operator, string $second): static
    {
        return $this->addJoin('LEFT JOIN', $table, $first, $operator, $second);
    }

    public function orderBy(string $column, string $direction = 'ASC'): static
    {
        $this->assertIdentifier($column, true);
        $direction = strtoupper($direction);
        if (!in_array($direction, ['ASC', 'DESC'], true)) {
            throw new InvalidArgumentException('Order direction must be ASC or DESC.');
        }
        $this->orders[] = "{$column} {$direction}";
        return $this;
    }

    public function limit(int $limit): static
    {
        if ($limit < 0) {
            throw new InvalidArgumentException('Limit must be zero or greater.');
        }
        $this->limit = $limit;
        return $this;
    }

    public function offset(int $offset): static
    {
        if ($offset < 0) {
            throw new InvalidArgumentException('Offset must be zero or greater.');
        }
        $this->offset = $offset;
        return $this;
    }

    public function union(QueryBuilder $query): static
    {
        return $this->addUnion('UNION', $query);
    }

    public function unionAll(QueryBuilder $query): static
    {
        return $this->addUnion('UNION ALL', $query);
    }

    public function with(string $name, string $table, string $localKey, string $foreignKey): static
    {
        $this->assertIdentifier($name);
        $this->assertIdentifier($table);
        $this->assertIdentifier($localKey, true);
        $this->assertIdentifier($foreignKey, true);

        $this->eagerLoads[] = compact('name', 'table', 'localKey', 'foreignKey');
        return $this;
    }

    public function allowWithoutWhere(): static
    {
        $this->allowWithoutWhere = true;
        return $this;
    }

    public function get(): array
    {
        $rows = $this->runSelect()->fetchAll();
        return $this->loadRelations($rows);
    }

    public function first(): ?array
    {
        $row = $this->limit(1)->runSelect()->fetch();
        return $row === false ? null : $row;
    }

    public function pluck(string $column): array
    {
        $this->assertIdentifier($column, true);
        $previousColumns = $this->columns;
        try {
            $this->columns = [$column];
            return array_map(static fn(array $row): mixed => reset($row), $this->runSelect()->fetchAll());
        } finally {
            $this->columns = $previousColumns;
        }
    }

    public function value(string $column): mixed
    {
        $this->assertIdentifier($column, true);
        $previousColumns = $this->columns;
        $previousLimit = $this->limit;
        try {
            $this->columns = [$column];
            $this->limit = 1;
            $row = $this->runSelect()->fetch();
            return is_array($row) ? reset($row) : null;
        } finally {
            $this->columns = $previousColumns;
            $this->limit = $previousLimit;
        }
    }

    public function paginate(int $perPage, int $page = 1): array
    {
        if ($perPage < 1) {
            throw new InvalidArgumentException('Per page must be at least 1.');
        }
        if ($page < 1) {
            throw new InvalidArgumentException('Page must be at least 1.');
        }

        $total = $this->count();
        $previousLimit = $this->limit;
        $previousOffset = $this->offset;
        try {
            $data = $this->limit($perPage)->offset(($page - 1) * $perPage)->get();
        } finally {
            $this->limit = $previousLimit;
            $this->offset = $previousOffset;
        }

        return [
            'data' => $data,
            'total' => $total,
            'per_page' => $perPage,
            'current_page' => $page,
            'last_page' => max(1, (int)ceil($total / $perPage)),
        ];
    }

    public function insert(array $rows): int
    {
        if ($rows === []) {
            throw new InvalidArgumentException('Insert requires a non-empty row or row list.');
        }

        $batch = array_is_list($rows) ? $rows : [$rows];
        if ($batch === [] || !is_array($batch[0]) || $batch[0] === []) {
            throw new InvalidArgumentException('Insert requires a non-empty row or row list.');
        }

        $columns = array_keys($batch[0]);
        foreach ($columns as $column) {
            $this->assertIdentifier((string)$column);
        }

        foreach ($batch as $row) {
            if (!is_array($row)) {
                throw new InvalidArgumentException('Every inserted row must be an array.');
            }
            $rowColumns = array_keys($row);
            sort($rowColumns);
            $expectedColumns = $columns;
            sort($expectedColumns);
            if ($rowColumns !== $expectedColumns) {
                throw new InvalidArgumentException('Every inserted row must contain the same columns.');
            }
        }

        $rowPlaceholder = '(' . implode(', ', array_fill(0, count($columns), '?')) . ')';
        $placeholders = implode(', ', array_fill(0, count($batch), $rowPlaceholder));
        $sql = sprintf(
            'INSERT INTO %s (%s) VALUES %s',
            $this->table,
            implode(', ', $columns),
            $placeholders
        );

        $bindings = [];
        foreach ($batch as $row) {
            foreach ($columns as $column) {
                $bindings[] = $row[$column] ?? null;
            }
        }

        $statement = $this->database->execute($sql, $bindings);
        return $statement->rowCount();
    }

    public function insertGetId(array $row): int
    {
        if (array_is_list($row)) {
            throw new InvalidArgumentException('insertGetId requires a single associative row.');
        }
        $this->insert($row);
        return (int)$this->database->pdo()->lastInsertId();
    }

    public function update(array $values): int
    {
        if ($values === []) {
            throw new InvalidArgumentException('Update values must not be empty.');
        }
        $this->assertWriteWhere('update');

        $sets = [];
        $bindings = [];
        foreach ($values as $column => $value) {
            $this->assertIdentifier((string)$column);
            $sets[] = "{$column} = ?";
            $bindings[] = $value;
        }

        $sql = "UPDATE {$this->table} SET " . implode(', ', $sets) . $this->compileWhere();
        $statement = $this->database->execute($sql, [...$bindings, ...$this->bindings]);
        return $statement->rowCount();
    }

    public function delete(): int
    {
        $this->assertWriteWhere('delete');
        $sql = "DELETE FROM {$this->table}" . $this->compileWhere();
        $statement = $this->database->execute($sql, $this->bindings);
        return $statement->rowCount();
    }

    public function count(string $column = '*'): int
    {
        return (int)$this->aggregate('COUNT', $column);
    }

    public function exists(): bool
    {
        $previousColumns = $this->columns;
        $previousLimit = $this->limit;
        $previousOffset = $this->offset;
        try {
            $this->columns = ['1'];
            $this->limit = 1;
            $this->offset = null;
            return $this->runSelect()->fetch() !== false;
        } finally {
            $this->columns = $previousColumns;
            $this->limit = $previousLimit;
            $this->offset = $previousOffset;
        }
    }

    public function sum(string $column): float|int
    {
        return $this->numericAggregate('SUM', $column) ?? 0;
    }

    public function avg(string $column): float|int|null
    {
        return $this->numericAggregate('AVG', $column);
    }

    public function min(string $column): mixed
    {
        return $this->aggregate('MIN', $column);
    }

    public function max(string $column): mixed
    {
        return $this->aggregate('MAX', $column);
    }

    private function addWhere(string $boolean, string $column, string $operator, mixed $value): static
    {
        $this->assertIdentifier($column, true);
        $operator = strtoupper($operator);
        if (!in_array($operator, ['=', '!=', '<>', '<', '<=', '>', '>=', 'LIKE'], true)) {
            throw new InvalidArgumentException("Unsupported where operator: {$operator}");
        }

        $this->wheres[] = [$boolean, "{$column} {$operator} ?"];
        $this->bindings[] = $value;
        return $this;
    }

    private function addJoin(string $type, string $table, string $first, string $operator, string $second): static
    {
        $this->assertIdentifier($table);
        $this->assertIdentifier($first, true);
        $this->assertIdentifier($second, true);
        if (!in_array($operator, ['=', '!=', '<>', '<', '<=', '>', '>='], true)) {
            throw new InvalidArgumentException("Unsupported join operator: {$operator}");
        }
        $this->joins[] = "{$type} {$table} ON {$first} {$operator} {$second}";
        return $this;
    }

    private function assertWriteWhere(string $operation): void
    {
        if ($this->wheres === [] && !$this->allowWithoutWhere) {
            throw new RuntimeException("Refusing {$operation} without WHERE. Call allowWithoutWhere() to allow it explicitly.");
        }
    }

    public function toSql(): array
    {
        $sql = sprintf(
            'SELECT %s FROM %s%s%s%s%s',
            implode(', ', $this->columns),
            $this->table,
            $this->joins === [] ? '' : ' ' . implode(' ', $this->joins),
            $this->compileWhere(),
            $this->orders === [] ? '' : ' ORDER BY ' . implode(', ', $this->orders),
            $this->compileLimit()
        );

        $bindings = [...$this->selectBindings, ...$this->bindings];
        foreach ($this->unions as $union) {
            [$unionSql, $unionBindings] = $union['query']->toSql();
            $sql .= ' ' . $union['type'] . ' ' . $unionSql;
            array_push($bindings, ...$unionBindings);
        }

        return [$sql, $bindings];
    }

    private function runSelect(): AdlaireStatement
    {
        [$sql, $bindings] = $this->toSql();
        return $this->database->execute($sql, $bindings);
    }

    private function aggregate(string $function, string $column): mixed
    {
        if ($column !== '*') {
            $this->assertIdentifier($column, true);
        }
        $sql = sprintf('SELECT %s(%s) AS aggregate FROM %s%s', $function, $column, $this->table, $this->compileWhere());
        $statement = $this->database->execute($sql, $this->bindings);
        $row = $statement->fetch();
        return is_array($row) ? ($row['aggregate'] ?? null) : null;
    }

    private function addUnion(string $type, QueryBuilder $query): static
    {
        $this->unions[] = ['type' => $type, 'query' => $query];
        return $this;
    }

    private function loadRelations(array $rows): array
    {
        foreach ($this->eagerLoads as $relation) {
            $keys = array_values(array_unique(array_filter(array_column($rows, $relation['localKey']), static fn(mixed $value): bool => $value !== null)));
            if ($keys === []) {
                continue;
            }

            $related = $this->database->table($relation['table'])->whereIn($relation['foreignKey'], $keys)->get();
            $grouped = [];
            foreach ($related as $row) {
                $grouped[$row[$relation['foreignKey']] ?? null][] = $row;
            }

            foreach ($rows as &$row) {
                $row[$relation['name']] = $grouped[$row[$relation['localKey']] ?? null] ?? [];
            }
            unset($row);
        }

        return $rows;
    }

    private function numericAggregate(string $function, string $column): float|int|null
    {
        $value = $this->aggregate($function, $column);
        if ($value === null) {
            return null;
        }
        if (is_int($value) || is_float($value)) {
            return $value;
        }
        if (is_string($value) && preg_match('/^-?\d+$/', $value) === 1) {
            return (int)$value;
        }
        if (is_numeric($value)) {
            return (float)$value;
        }
        throw new RuntimeException("Aggregate {$function} did not return a numeric value.");
    }

    private function compileWhere(): string
    {
        if ($this->wheres === []) {
            return '';
        }

        $sql = ' WHERE ';
        foreach ($this->wheres as $index => [$boolean, $condition]) {
            $sql .= ($index === 0 ? '' : " {$boolean} ") . $condition;
        }
        return $sql;
    }

    private function compileLimit(): string
    {
        $sql = '';
        if ($this->limit !== null) {
            $sql .= ' LIMIT ' . $this->limit;
        } elseif ($this->offset !== null) {
            $sql .= ' LIMIT -1';
        }
        if ($this->offset !== null) {
            $sql .= ' OFFSET ' . $this->offset;
        }
        return $sql;
    }

    private function assertIdentifier(string $identifier, bool $allowDot = false): void
    {
        $name = '[A-Za-z_][A-Za-z0-9_]*';
        $base = $allowDot ? "{$name}(\\.({$name}|\\*))?" : $name;
        $pattern = '/^' . $base . '(\\s+AS\\s+' . $name . ')?$/i';
        if (preg_match($pattern, $identifier) !== 1) {
            throw new InvalidArgumentException("Invalid SQL identifier: {$identifier}");
        }
    }
}

abstract class Migration
{
    abstract public function up(Database $database): void;

    abstract public function down(Database $database): void;
}

final class Migrator
{
    private const TABLE = 'adlaire_migrations';

    public function __construct(private Database $database)
    {
        $this->ensureTable();
    }

    public function migrate(string $directory): void
    {
        foreach ($this->pendingMigrations($directory) as $file) {
            $migration = $this->loadMigration($file);
            $this->database->transaction(function (Database $database) use ($migration, $file): void {
                $migration->up($database);
                $database->statement(
                    'INSERT INTO ' . self::TABLE . ' (migration, created_at) VALUES (?, ?)',
                    [basename($file), date('c')]
                );
            });
        }
    }

    public function rollback(string $directory, int $steps = 1): void
    {
        if ($steps < 1) {
            throw new InvalidArgumentException('Rollback steps must be at least 1.');
        }

        $executedCount = (int)$this->database->statement('SELECT COUNT(*) AS aggregate FROM ' . self::TABLE)->fetch()['aggregate'];
        if ($steps > $executedCount) {
            throw new RuntimeException('Rollback steps exceed executed migration count.');
        }

        $rows = $this->database->statement(
            'SELECT migration FROM ' . self::TABLE . ' ORDER BY id DESC LIMIT ' . $steps
        )->fetchAll();

        foreach ($rows as $row) {
            $file = rtrim($directory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $row['migration'];
            $migration = $this->loadMigration($file);
            $this->database->transaction(function (Database $database) use ($migration, $row): void {
                $migration->down($database);
                $database->statement('DELETE FROM ' . self::TABLE . ' WHERE migration = ?', [$row['migration']]);
            });
        }
    }

    private function ensureTable(): void
    {
        $this->database->statement(
            'CREATE TABLE IF NOT EXISTS ' . self::TABLE . ' (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                migration TEXT NOT NULL UNIQUE,
                created_at TEXT NOT NULL
            )'
        );
    }

    private function pendingMigrations(string $directory): array
    {
        $files = glob(rtrim($directory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . '*.php');
        if ($files === false) {
            return [];
        }

        sort($files, SORT_STRING);
        foreach ($files as $file) {
            $this->assertMigrationName(basename($file));
        }

        $ran = $this->database->statement('SELECT migration FROM ' . self::TABLE)->fetchAll(PDO::FETCH_COLUMN);
        return array_values(array_filter($files, static fn(string $file): bool => !in_array(basename($file), $ran, true)));
    }

    private function loadMigration(string $file): Migration
    {
        if (!is_file($file)) {
            throw new InvalidArgumentException("Migration file not found: {$file}");
        }
        $this->assertMigrationName(basename($file));

        $migration = require $file;
        if (!$migration instanceof Migration) {
            throw new RuntimeException("Migration must return a Migration instance: {$file}");
        }
        return $migration;
    }

    private function assertMigrationName(string $name): void
    {
        if (preg_match('/^\d{8}_\d{6}_[A-Za-z0-9_]+\.php$/', $name) !== 1) {
            throw new InvalidArgumentException("Invalid migration filename: {$name}");
        }
    }
}
