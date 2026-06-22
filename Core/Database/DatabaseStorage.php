<?php

declare(strict_types=1);

trait AdlaireDatabaseStorage
{
public static function enableSQLite(string $path): array
    {
        if ($path === '') {
            throw new InvalidArgumentException('SQLite path is required.');
        }

        $directory = dirname($path);
        if (!is_dir($directory)) {
            throw new InvalidArgumentException('SQLite directory does not exist.');
        }

        self::$sqlitePath = $path;
        self::$pdo = new PDO('sqlite:' . $path);
        self::$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        self::$pdo->exec('PRAGMA journal_mode = WAL');
        self::$pdo->exec('PRAGMA foreign_keys = ON');
        self::initializeSQLite();
        self::loadSQLite();
        self::persistDefaultCollections();
        self::writeMeta('schema_version', '1');
        self::writeMeta('selected_database', 'sqlite');

        return self::storageStatus();
    }

public static function disableSQLite(): void
    {
        self::$pdo = null;
        self::$sqlitePath = null;
    }

public static function storageStatus(): array
    {
        $tables = ['collections', 'records', 'events', 'schema_versions', 'database_meta'];
        $status = [
            'selected_database' => 'sqlite',
            'runtime_execution' => self::$pdo === null ? 'in_memory' : 'sqlite_persistent',
            'enabled' => self::$pdo !== null,
            'path' => self::$sqlitePath,
            'tables' => $tables,
            'wal_mode' => false,
            'integrity_check' => 'not_checked',
        ];

        if (self::$pdo !== null) {
            $journalMode = self::$pdo->query('PRAGMA journal_mode')->fetchColumn();
            $integrity = self::$pdo->query('PRAGMA integrity_check')->fetchColumn();
            $status['wal_mode'] = strtolower((string)$journalMode) === 'wal';
            $status['journal_mode'] = strtolower((string)$journalMode);
            $status['integrity_check'] = (string)$integrity;
            $status['file_exists'] = self::$sqlitePath !== null && is_file(self::$sqlitePath);
            $status['file_size'] = $status['file_exists'] ? filesize((string)self::$sqlitePath) : 0;
        }

        return $status;
    }

private static function initializeSQLite(): void
    {
        if (self::$pdo === null) {
            return;
        }

        self::$pdo->exec('CREATE TABLE IF NOT EXISTS collections (
            name TEXT PRIMARY KEY,
            channel TEXT NOT NULL,
            storage TEXT NOT NULL,
            schema_json TEXT NOT NULL,
            indexes_json TEXT NOT NULL,
            delete_mode TEXT NOT NULL
        )');
        self::$pdo->exec('CREATE TABLE IF NOT EXISTS records (
            id TEXT NOT NULL,
            collection TEXT NOT NULL,
            channel TEXT NOT NULL,
            data_json TEXT NOT NULL,
            meta_json TEXT NOT NULL,
            version INTEGER NOT NULL,
            PRIMARY KEY (collection, id)
        )');
        self::$pdo->exec('CREATE TABLE IF NOT EXISTS events (
            id TEXT PRIMARY KEY,
            sequence INTEGER NOT NULL,
            collection TEXT NOT NULL,
            channel TEXT NOT NULL,
            record_id TEXT NOT NULL,
            type TEXT NOT NULL,
            version INTEGER NOT NULL,
            payload_hash TEXT NOT NULL,
            before_hash TEXT,
            after_hash TEXT,
            changed_fields_json TEXT NOT NULL,
            payload_json TEXT NOT NULL
        )');
        self::$pdo->exec('CREATE TABLE IF NOT EXISTS schema_versions (
            version INTEGER PRIMARY KEY,
            applied_sequence INTEGER NOT NULL
        )');
        self::$pdo->exec('CREATE TABLE IF NOT EXISTS database_meta (
            key TEXT PRIMARY KEY,
            value TEXT NOT NULL
        )');
        self::$pdo->exec('CREATE INDEX IF NOT EXISTS idx_records_collection ON records (collection)');
        self::$pdo->exec('CREATE INDEX IF NOT EXISTS idx_events_sequence ON events (sequence)');
        self::$pdo->exec('CREATE INDEX IF NOT EXISTS idx_events_collection_record ON events (collection, record_id)');
        self::$pdo->exec('INSERT OR IGNORE INTO schema_versions (version, applied_sequence) VALUES (1, 0)');
    }

private static function loadSQLite(): void
    {
        if (self::$pdo === null) {
            return;
        }

        self::$collections = [];
        self::$records = [];
        self::$events = [];

        $collectionRows = self::$pdo->query('SELECT * FROM collections ORDER BY name')->fetchAll(PDO::FETCH_ASSOC);
        foreach ($collectionRows as $row) {
            self::$collections[(string)$row['name']] = [
                'name' => (string)$row['name'],
                'channel' => (string)$row['channel'],
                'storage' => (string)$row['storage'],
                'schema' => self::decodeJson((string)$row['schema_json']),
                'indexes' => self::decodeJson((string)$row['indexes_json']),
                'delete_mode' => (string)$row['delete_mode'],
            ];
        }

        $recordRows = self::$pdo->query('SELECT * FROM records ORDER BY collection, id')->fetchAll(PDO::FETCH_ASSOC);
        foreach ($recordRows as $row) {
            $collection = (string)$row['collection'];
            self::$records[$collection][(string)$row['id']] = [
                'id' => (string)$row['id'],
                'collection' => $collection,
                'channel' => (string)$row['channel'],
                'data' => self::decodeJson((string)$row['data_json']),
                'meta' => self::decodeJson((string)$row['meta_json']),
                'version' => (int)$row['version'],
            ];
        }

        $eventRows = self::$pdo->query('SELECT * FROM events ORDER BY sequence')->fetchAll(PDO::FETCH_ASSOC);
        foreach ($eventRows as $row) {
            self::$events[] = [
                'id' => (string)$row['id'],
                'sequence' => (int)$row['sequence'],
                'collection' => (string)$row['collection'],
                'channel' => (string)$row['channel'],
                'record_id' => (string)$row['record_id'],
                'type' => (string)$row['type'],
                'version' => (int)$row['version'],
                'payload_hash' => (string)$row['payload_hash'],
                'before_hash' => $row['before_hash'] === null ? null : (string)$row['before_hash'],
                'after_hash' => $row['after_hash'] === null ? null : (string)$row['after_hash'],
                'changed_fields' => self::decodeJson((string)$row['changed_fields_json']),
                'payload' => self::decodeJson((string)$row['payload_json']),
            ];
        }

        self::syncSequences();
    }

private static function persistCollection(array $definition): void
    {
        if (self::$pdo === null) {
            return;
        }

        $statement = self::$pdo->prepare('INSERT OR REPLACE INTO collections
            (name, channel, storage, schema_json, indexes_json, delete_mode)
            VALUES (:name, :channel, :storage, :schema_json, :indexes_json, :delete_mode)');
        $statement->execute([
            ':name' => $definition['name'],
            ':channel' => $definition['channel'],
            ':storage' => 'sqlite',
            ':schema_json' => self::encodeJson($definition['schema']),
            ':indexes_json' => self::encodeJson($definition['indexes']),
            ':delete_mode' => $definition['delete_mode'],
        ]);
    }

private static function persistDefaultCollections(): void
    {
        if (self::$pdo === null) {
            return;
        }

        foreach (self::defaultCollections() as $collection => $definition) {
            if (!isset(self::$collections[$collection])) {
                self::persistCollection($definition);
            }
        }
    }

private static function persistRecord(array $record): void
    {
        if (self::$pdo === null) {
            return;
        }

        $statement = self::$pdo->prepare('INSERT OR REPLACE INTO records
            (id, collection, channel, data_json, meta_json, version)
            VALUES (:id, :collection, :channel, :data_json, :meta_json, :version)');
        $statement->execute([
            ':id' => $record['id'],
            ':collection' => $record['collection'],
            ':channel' => $record['channel'],
            ':data_json' => self::encodeJson($record['data']),
            ':meta_json' => self::encodeJson($record['meta']),
            ':version' => $record['version'],
        ]);
    }

private static function deletePersistedRecord(string $collection, string $id): void
    {
        if (self::$pdo === null) {
            return;
        }

        $statement = self::$pdo->prepare('DELETE FROM records WHERE collection = :collection AND id = :id');
        $statement->execute([':collection' => $collection, ':id' => $id]);
    }

private static function clearPersistedCollectionRecords(string $collection): void
    {
        if (self::$pdo === null) {
            return;
        }

        $statement = self::$pdo->prepare('DELETE FROM records WHERE collection = :collection');
        $statement->execute([':collection' => $collection]);
    }

private static function persistEvent(array $event): void
    {
        if (self::$pdo === null) {
            return;
        }

        $statement = self::$pdo->prepare('INSERT OR REPLACE INTO events
            (id, sequence, collection, channel, record_id, type, version, payload_hash, before_hash, after_hash, changed_fields_json, payload_json)
            VALUES (:id, :sequence, :collection, :channel, :record_id, :type, :version, :payload_hash, :before_hash, :after_hash, :changed_fields_json, :payload_json)');
        $statement->execute([
            ':id' => $event['id'],
            ':sequence' => $event['sequence'],
            ':collection' => $event['collection'],
            ':channel' => $event['channel'],
            ':record_id' => $event['record_id'],
            ':type' => $event['type'],
            ':version' => $event['version'],
            ':payload_hash' => $event['payload_hash'],
            ':before_hash' => $event['before_hash'],
            ':after_hash' => $event['after_hash'],
            ':changed_fields_json' => self::encodeJson($event['changed_fields']),
            ':payload_json' => self::encodeJson($event['payload']),
        ]);
    }

private static function writeMeta(string $key, string $value): void
    {
        if (self::$pdo === null) {
            return;
        }

        $statement = self::$pdo->prepare('INSERT OR REPLACE INTO database_meta (key, value) VALUES (:key, :value)');
        $statement->execute([':key' => $key, ':value' => $value]);
    }

private static function syncSequences(): void
    {
        self::$recordSequence = 0;
        foreach (self::$records as $records) {
            foreach ($records as $record) {
                self::$recordSequence = max(self::$recordSequence, self::sequenceFromId((string)$record['id']));
            }
        }

        self::$eventSequence = 0;
        foreach (self::$events as $event) {
            self::$eventSequence = max(self::$eventSequence, (int)$event['sequence'], self::sequenceFromId((string)$event['id']));
        }
    }

private static function sequenceFromId(string $id): int
    {
        if (preg_match('/_(\d+)$/', $id, $matches) !== 1) {
            return 0;
        }

        return (int)$matches[1];
    }

private static function encodeJson(array $payload): string
    {
        $encoded = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if (!is_string($encoded)) {
            throw new RuntimeException('Failed to encode JSON payload.');
        }

        return $encoded;
    }

private static function decodeJson(string $payload): array
    {
        $decoded = json_decode($payload, true);
        return is_array($decoded) ? $decoded : [];
    }

private static function databaseFingerprint(array $payload): string
    {
        return hash('sha256', self::encodeJson($payload));
    }
}
