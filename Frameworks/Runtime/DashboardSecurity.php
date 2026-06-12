<?php

declare(strict_types=1);

final class AdlaireDashboardSecurity
{
    private const EXECUTION_TOKEN_TTL = 300;

    public static function authorized(): bool
    {
        if (!Adlaire::dashboardEnabled() || !Adlaire::dashboardTokenConfigured()) {
            return false;
        }

        $expected = (string)getenv('ADLAIRE_DASHBOARD_TOKEN');
        $provided = self::tokenFromHeader();
        if ($provided !== null && hash_equals($expected, $provided)) {
            return true;
        }

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            $posted = is_string($_POST['token'] ?? null) ? (string)$_POST['token'] : '';
            if ($posted !== '' && hash_equals($expected, $posted)) {
                session_start();
                $_SESSION['adlaire_dashboard_token'] = hash('sha256', $posted);
                return true;
            }
        }

        session_start();
        $sessionToken = $_SESSION['adlaire_dashboard_token'] ?? null;
        return is_string($sessionToken) && hash_equals(hash('sha256', $expected), $sessionToken);
    }

    public static function csrfToken(): string
    {
        self::startSession();
        $token = $_SESSION['adlaire_csrf_token'] ?? null;
        if (!is_string($token) || $token === '') {
            $token = bin2hex(random_bytes(32));
            $_SESSION['adlaire_csrf_token'] = $token;
        }

        return $token;
    }

    public static function verifyCsrf(string $token): bool
    {
        self::startSession();
        $expected = $_SESSION['adlaire_csrf_token'] ?? null;
        return is_string($expected) && $token !== '' && hash_equals($expected, $token);
    }

    public static function executionToken(): string
    {
        self::startSession();
        $token = bin2hex(random_bytes(32));
        $_SESSION['adlaire_execution_token'] = [
            'token' => hash('sha256', $token),
            'expires_at' => time() + self::EXECUTION_TOKEN_TTL,
        ];

        return $token;
    }

    public static function verifyExecutionToken(string $token): bool
    {
        self::startSession();
        $record = $_SESSION['adlaire_execution_token'] ?? null;
        if (!is_array($record) || !is_string($record['token'] ?? null) || !is_int($record['expires_at'] ?? null)) {
            return false;
        }
        if ($record['expires_at'] < time() || $token === '') {
            unset($_SESSION['adlaire_execution_token']);
            return false;
        }

        $valid = hash_equals($record['token'], hash('sha256', $token));
        if ($valid) {
            unset($_SESSION['adlaire_execution_token']);
        }

        return $valid;
    }

    private static function tokenFromHeader(): ?string
    {
        $header = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
        if (!is_string($header) || preg_match('/^Bearer\s+(.+)$/i', trim($header), $matches) !== 1) {
            return null;
        }

        return trim($matches[1]);
    }

    private static function startSession(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
    }
}
