<?php

/**
 * Adlaire Ecosystem - Database.php
 *
 * @version 0.3
 * @php     >= 8.3
 */

declare(strict_types=1);

if (PHP_VERSION_ID < 80300) {
    echo json_encode(['error' => 'Adlaire Ecosystem requires PHP 8.3 or higher. Current version: ' . PHP_VERSION]);
    exit(1);
}

final class Database
{
    private static array $connections = [];
    private static ?string $defaultConnection = null;
    private PDO $pdo;
    private int $transactionDepth = 0;
    private bool $queryLogging = false;
    private ?float $slowQueryThresholdMs = null;
    private array $queryLog = [];

    public function __construct(string $path)
    {
        $this->assertDatabasePath($path);
        $this->pdo = new PDO('sqlite:' . $path);
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    }

    public static function addConnection(string $name, string $path, bool $default = false): self
    {
        $database = new self($path);
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

    public static function connect(string $path): self
    {
        $database = new self($path);
        if (self::$defaultConnection === null) {
            self::$connections['default'] = $database;
            self::$defaultConnection = 'default';
        }
        return $database;
    }

    public function pdo(): PDO
    {
        return $this->pdo;
    }

    public function table(string $table): QueryBuilder
    {
        return new QueryBuilder($this, $table);
    }

    public function statement(string $sql, array $bindings = []): PDOStatement
    {
        $statement = $this->pdo->prepare($sql);
        if (!$statement instanceof PDOStatement) {
            throw new RuntimeException('Failed to prepare SQL statement.');
        }
        $this->executeStatement($statement, $sql, $bindings);
        return $statement;
    }

    public function executeStatement(PDOStatement $statement, string $sql, array $bindings = []): void
    {
        $start = microtime(true);
        $statement->execute($bindings);
        $durationMs = (microtime(true) - $start) * 1000;

        if ($this->queryLogging || ($this->slowQueryThresholdMs !== null && $durationMs >= $this->slowQueryThresholdMs)) {
            $this->queryLog[] = [
                'sql' => $sql,
                'bindings' => $bindings,
                'duration_ms' => $durationMs,
                'slow' => $this->slowQueryThresholdMs !== null && $durationMs >= $this->slowQueryThresholdMs,
                'timestamp' => date('c'),
            ];
        }
    }

    public function enableQueryLog(?float $slowQueryThresholdMs = null): static
    {
        $this->queryLogging = true;
        $this->slowQueryThresholdMs = $slowQueryThresholdMs;
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
            $this->rollBack();
            throw $exception;
        }
    }

    private function beginTransaction(): void
    {
        if ($this->transactionDepth === 0) {
            $this->pdo->beginTransaction();
        } else {
            $this->pdo->exec('SAVEPOINT adlaire_tx_' . $this->transactionDepth);
        }
        $this->transactionDepth++;
    }

    private function commit(): void
    {
        if ($this->transactionDepth <= 0) {
            throw new RuntimeException('No active transaction to commit.');
        }

        if ($this->transactionDepth === 1) {
            $this->pdo->commit();
            $this->transactionDepth = 0;
            return;
        }

        $savepoint = $this->transactionDepth - 1;
        $this->pdo->exec('RELEASE SAVEPOINT adlaire_tx_' . $savepoint);
        $this->transactionDepth--;
    }

    private function rollBack(): void
    {
        if ($this->transactionDepth <= 0) {
            return;
        }

        $this->transactionDepth--;
        if ($this->transactionDepth === 0) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            return;
        }

        $this->pdo->exec('ROLLBACK TO SAVEPOINT adlaire_tx_' . $this->transactionDepth);
        $this->pdo->exec('RELEASE SAVEPOINT adlaire_tx_' . $this->transactionDepth);
    }

    public function migrate(string $directory): void
    {
        (new Migrator($this))->migrate($directory);
    }

    public function rollback(string $directory, int $steps = 1): void
    {
        (new Migrator($this))->rollback($directory, $steps);
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
}

final class QueryBuilder
{
    private array $columns = ['*'];
    private array $wheres = [];
    private array $joins = [];
    private array $orders = [];
    private ?int $limit = null;
    private ?int $offset = null;
    private array $bindings = [];
    private array $unions = [];
    private array $eagerLoads = [];

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

        $statement = $this->database->pdo()->prepare($sql);
        if (!$statement instanceof PDOStatement) {
            throw new RuntimeException('Failed to prepare insert statement.');
        }
        $this->database->executeStatement($statement, $sql, $bindings);
        return $statement->rowCount();
    }

    public function update(array $values): int
    {
        if ($values === []) {
            throw new InvalidArgumentException('Update values must not be empty.');
        }

        $sets = [];
        $bindings = [];
        foreach ($values as $column => $value) {
            $this->assertIdentifier((string)$column);
            $sets[] = "{$column} = ?";
            $bindings[] = $value;
        }

        $sql = "UPDATE {$this->table} SET " . implode(', ', $sets) . $this->compileWhere();
        $statement = $this->database->pdo()->prepare($sql);
        if (!$statement instanceof PDOStatement) {
            throw new RuntimeException('Failed to prepare update statement.');
        }
        $this->database->executeStatement($statement, $sql, [...$bindings, ...$this->bindings]);
        return $statement->rowCount();
    }

    public function delete(): int
    {
        $sql = "DELETE FROM {$this->table}" . $this->compileWhere();
        $statement = $this->database->pdo()->prepare($sql);
        if (!$statement instanceof PDOStatement) {
            throw new RuntimeException('Failed to prepare delete statement.');
        }
        $this->database->executeStatement($statement, $sql, $this->bindings);
        return $statement->rowCount();
    }

    public function count(string $column = '*'): int
    {
        return (int)$this->aggregate('COUNT', $column);
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

        $bindings = $this->bindings;
        foreach ($this->unions as $union) {
            [$unionSql, $unionBindings] = $union['query']->toSql();
            $sql .= ' ' . $union['type'] . ' ' . $unionSql;
            array_push($bindings, ...$unionBindings);
        }

        return [$sql, $bindings];
    }

    private function runSelect(): PDOStatement
    {
        [$sql, $bindings] = $this->toSql();

        $statement = $this->database->pdo()->prepare($sql);
        if (!$statement instanceof PDOStatement) {
            throw new RuntimeException('Failed to prepare select statement.');
        }
        $this->database->executeStatement($statement, $sql, $bindings);
        return $statement;
    }

    private function aggregate(string $function, string $column): mixed
    {
        if ($column !== '*') {
            $this->assertIdentifier($column, true);
        }
        $sql = sprintf('SELECT %s(%s) AS aggregate FROM %s%s', $function, $column, $this->table, $this->compileWhere());
        $statement = $this->database->pdo()->prepare($sql);
        if (!$statement instanceof PDOStatement) {
            throw new RuntimeException('Failed to prepare aggregate statement.');
        }
        $this->database->executeStatement($statement, $sql, $this->bindings);
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
        $pattern = $allowDot ? '/^[A-Za-z_][A-Za-z0-9_]*(\.([A-Za-z_][A-Za-z0-9_]*|\*))?$/' : '/^[A-Za-z_][A-Za-z0-9_]*$/';
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
        $ran = $this->database->statement('SELECT migration FROM ' . self::TABLE)->fetchAll(PDO::FETCH_COLUMN);
        return array_values(array_filter($files, static fn(string $file): bool => !in_array(basename($file), $ran, true)));
    }

    private function loadMigration(string $file): Migration
    {
        if (!is_file($file)) {
            throw new InvalidArgumentException("Migration file not found: {$file}");
        }

        $migration = require $file;
        if (!$migration instanceof Migration) {
            throw new RuntimeException("Migration must return a Migration instance: {$file}");
        }
        return $migration;
    }
}
