<?php

declare(strict_types=1);

trait AdlaireAuthStorage
{
    private static function nextId(string $prefix, int $sequence): string
    {
        return $prefix . '_' . str_pad((string)$sequence, 6, '0', STR_PAD_LEFT);
    }

    private static function stableData(array $data): array
    {
        ksort($data);
        return $data;
    }

    private static function fingerprint(array $payload): string
    {
        return hash('sha256', self::encodeJson(self::stableData($payload)));
    }

    private static function encodeJson(array $payload): string
    {
        $encoded = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if (!is_string($encoded)) {
            throw new RuntimeException('Failed to encode Auth JSON payload.');
        }

        return $encoded;
    }

    private static function hashSecret(string $secret): string
    {
        return hash('sha256', $secret);
    }

    private static function recordAuthEvent(string $domain, string $type, string $recordId, array $payload, array $metadata = []): array
    {
        $event = AdlaireEventLog::recordDomainEvent(
            self::$events,
            $domain,
            'auth',
            'system',
            $recordId,
            $type,
            count(self::$events) + 1,
            self::stableData($payload),
            null,
            $metadata
        );
        self::$events[] = $event;

        return $event;
    }
}
