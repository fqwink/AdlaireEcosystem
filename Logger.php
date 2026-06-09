<?php

/**
 * Adlaire Ecosystem - Logger.php
 *
 * @version v0.50
 * @php     >= 8.3
 */

declare(strict_types=1);

if (PHP_VERSION_ID < 80300) {
    echo json_encode(['error' => 'Adlaire Ecosystem requires PHP 8.3 or higher. Current version: ' . PHP_VERSION]);
    exit(1);
}

final class Logger
{
    private const LEVELS = ['DEBUG' => 0, 'INFO' => 1, 'WARNING' => 2, 'ERROR' => 3];
    private bool $missingHmacWarningWritten = false;

    public function __construct(
        private string $file,
        private string $level = 'INFO',
        private ?string $hmacKey = null,
        private int $maxBytes = 1048576,
        private int $keep = 5,
        private array $maskFields = ['password', 'token', 'secret'],
        private int $bodyMaxBytes = 4096,
        private bool $debugEnabled = false,
        private ?string $requestId = null,
        private string $component = 'logger'
    ) {
        $this->level = strtoupper($level);
        if (!isset(self::LEVELS[$this->level])) {
            throw new InvalidArgumentException('Invalid log level.');
        }
        $this->ensureDirectory(dirname($file));
        $this->verifyHmac();
        if ($this->hmacKey === null || $this->hmacKey === '') {
            $this->writeMissingHmacWarning();
        }
    }

    public static function fromConfig(array $config): ?self
    {
        $file = $config['file'] ?? $config['log_file'] ?? null;
        if (!is_string($file) || $file === '') {
            return null;
        }

        return new self(
            $file,
            (string)($config['level'] ?? $config['log_level'] ?? 'INFO'),
            isset($config['hmac_key']) && is_string($config['hmac_key']) && $config['hmac_key'] !== '' ? $config['hmac_key'] : null,
            (int)($config['max_bytes'] ?? $config['log_max_bytes'] ?? 1048576),
            (int)($config['keep'] ?? $config['log_keep'] ?? 5),
            is_array($config['mask_fields'] ?? null) ? $config['mask_fields'] : ['password', 'token', 'secret'],
            (int)($config['body_max_bytes'] ?? 4096),
            getenv('APP_ENV') === 'development',
            isset($config['request_id']) && is_string($config['request_id']) && $config['request_id'] !== '' ? $config['request_id'] : null,
            isset($config['component']) && is_string($config['component']) && $config['component'] !== '' ? $config['component'] : 'core'
        );
    }

    public function debug(string $message, array $context = []): void
    {
        $this->write('DEBUG', $message, $context);
    }

    public function info(string $message, array $context = []): void
    {
        $this->write('INFO', $message, $context);
    }

    public function warning(string $message, array $context = []): void
    {
        $this->write('WARNING', $message, $context);
    }

    public function error(string $message, array $context = []): void
    {
        $this->write('ERROR', $message, $context);
    }

    public function withComponent(string $component): self
    {
        if ($component === '') {
            throw new InvalidArgumentException('Logger component must not be empty.');
        }

        return new self(
            $this->file,
            $this->level,
            $this->hmacKey,
            $this->maxBytes,
            $this->keep,
            $this->maskFields,
            $this->bodyMaxBytes,
            $this->debugEnabled,
            $this->requestId,
            $component
        );
    }

    public function debugRequest(Request $request, ?Response $response, float $startedAt, array $queryLog = []): void
    {
        if (!$this->debugEnabled) {
            return;
        }

        $body = $request->body();
        $bodyJson = json_encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $truncated = false;
        if (is_string($bodyJson) && strlen($bodyJson) > $this->bodyMaxBytes) {
            $bodyJson = substr($bodyJson, 0, $this->bodyMaxBytes);
            $truncated = true;
        }

        $this->debug('Request debug.', [
            'request' => [
                'method' => $request->method(),
                'uri' => $request->uri(),
                'headers' => $this->maskHeaders($request->headers()),
                'body' => $bodyJson === false ? null : $bodyJson,
                'body_truncated' => $truncated,
                'query' => $this->maskArray($request->query()),
                'ip' => $request->ip(),
            ],
            'response' => [
                'status_code' => $response?->statusCode() ?? http_response_code(),
                'headers' => $response?->headers() ?? [],
                'duration_ms' => (microtime(true) - $startedAt) * 1000,
                'peak_memory' => memory_get_peak_usage(true),
            ],
            'queries' => $queryLog,
            'routing' => $request->routeInfo(),
        ]);
    }

    public function queryLog(array $entries): void
    {
        if (!$this->debugEnabled) {
            return;
        }
        $this->debug('Query debug.', ['queries' => $entries]);
    }

    public function auditEvent(string $event, array $context = []): void
    {
        if ($event === '') {
            throw new InvalidArgumentException('Audit event must not be empty.');
        }
        $this->info('Audit event.', [
            'component' => 'audit',
            'event' => $event,
            'audit' => $context,
        ]);
    }

    private function write(string $level, string $message, array $context): void
    {
        if ((self::LEVELS[$level] ?? 99) < self::LEVELS[$this->level]) {
            return;
        }

        $this->rotateIfNeeded();
        $line = json_encode([
            'time' => date('c'),
            'level' => $level,
            'message' => $message,
            'component' => $context['component'] ?? $this->component,
            'request_id' => $context['request_id'] ?? $this->requestId ?? $this->generateRequestId(),
            'context' => $this->maskArray($context),
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        if ($line === false) {
            $line = '{"time":"' . date('c') . '","level":"ERROR","message":"log encoding failed","context":[]}';
        }

        if (file_put_contents($this->file, $line . PHP_EOL, FILE_APPEND | LOCK_EX) === false) {
            throw new RuntimeException('Failed to write log.');
        }
        $this->writeHmac();
    }

    private function maskHeaders(array $headers): array
    {
        foreach ($headers as $key => $value) {
            if (strtolower((string)$key) === 'authorization') {
                $headers[$key] = '[masked]';
            }
        }
        return $headers;
    }

    private function maskArray(array $value): array
    {
        foreach ($value as $key => $item) {
            if (is_string($key) && $this->isSensitiveKey($key)) {
                $value[$key] = '[masked]';
                continue;
            }
            if (is_array($item)) {
                $value[$key] = $this->maskArray($item);
            }
        }
        return $value;
    }

    private function isSensitiveKey(string $key): bool
    {
        $normalized = strtolower($key);
        foreach ($this->maskFields as $field) {
            $field = strtolower((string)$field);
            if ($field !== '' && str_contains($normalized, $field)) {
                return true;
            }
        }
        return false;
    }

    private function verifyHmac(): void
    {
        if ($this->hmacKey === null || !is_file($this->file) || !is_file($this->file . '.hmac')) {
            return;
        }

        $actual = hash_hmac_file('sha256', $this->file, $this->hmacKey);
        if ($actual === false) {
            throw new RuntimeException('Failed to calculate log HMAC.');
        }
        $expected = trim((string)file_get_contents($this->file . '.hmac'));
        if (!hash_equals($expected, $actual)) {
            throw new RuntimeException('Log HMAC verification failed.');
        }
    }

    private function writeHmac(): void
    {
        if ($this->hmacKey === null || !is_file($this->file)) {
            return;
        }

        $hmac = hash_hmac_file('sha256', $this->file, $this->hmacKey);
        if ($hmac === false) {
            throw new RuntimeException('Failed to calculate log HMAC.');
        }

        if (file_put_contents($this->file . '.hmac', $hmac) === false) {
            throw new RuntimeException('Failed to write log HMAC.');
        }
    }

    private function writeMissingHmacWarning(): void
    {
        if ($this->missingHmacWarningWritten) {
            return;
        }
        $this->missingHmacWarningWritten = true;
        $this->warning('Log HMAC key is not configured.');
    }

    private function generateRequestId(): string
    {
        $this->requestId ??= bin2hex(random_bytes(8));
        return $this->requestId;
    }

    private function rotateIfNeeded(): void
    {
        if (!is_file($this->file) || filesize($this->file) < $this->maxBytes) {
            return;
        }

        if ($this->keep < 1) {
            if (!unlink($this->file)) {
                throw new RuntimeException("Failed to rotate log file: {$this->file}");
            }
            return;
        }

        $oldest = $this->file . '.' . $this->keep;
        if (is_file($oldest) && !unlink($oldest)) {
            throw new RuntimeException("Failed to remove old log file: {$oldest}");
        }

        for ($i = $this->keep - 1; $i >= 1; $i--) {
            $from = $this->file . '.' . $i;
            $to = $this->file . '.' . ($i + 1);
            if (is_file($from) && !rename($from, $to)) {
                throw new RuntimeException("Failed to rotate log file: {$from}");
            }
        }

        if (!rename($this->file, $this->file . '.1')) {
            throw new RuntimeException("Failed to rotate log file: {$this->file}");
        }
    }

    private function ensureDirectory(string $directory): void
    {
        if ($directory !== '' && !is_dir($directory)) {
            if (!mkdir($directory, 0775, true) && !is_dir($directory)) {
                throw new RuntimeException("Failed to create log directory: {$directory}");
            }
        }
    }
}
