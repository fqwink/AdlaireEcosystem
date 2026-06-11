<?php

declare(strict_types=1);

final class AdlaireDashboardSecurity
{
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

    private static function tokenFromHeader(): ?string
    {
        $header = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
        if (!is_string($header) || preg_match('/^Bearer\s+(.+)$/i', trim($header), $matches) !== 1) {
            return null;
        }

        return trim($matches[1]);
    }
}
