<?php

namespace App\Core;

class Session
{
    public static function start(): void
    {
        if (session_status() !== PHP_SESSION_NONE) {
            return;
        }

        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.cookie_httponly', '1');

        $sessionName = (string) ($_ENV['SESSION_NAME'] ?? getenv('SESSION_NAME') ?: 'ldweb_session');
        if (!preg_match('/^[A-Za-z0-9_-]{1,64}$/', $sessionName)) {
            $sessionName = 'ldweb_session';
        }
        session_name($sessionName);

        $idleMinutes = max(1, (int) ($_ENV['SESSION_LIFETIME'] ?? getenv('SESSION_LIFETIME') ?: 120));
        $absoluteMinutes = max($idleMinutes, (int) ($_ENV['SESSION_ABSOLUTE_LIFETIME'] ?? getenv('SESSION_ABSOLUTE_LIFETIME') ?: 480));
        $regenerateMinutes = max(5, (int) ($_ENV['SESSION_REGENERATE_MINUTES'] ?? getenv('SESSION_REGENERATE_MINUTES') ?: 15));
        ini_set('session.gc_maxlifetime', (string) ($absoluteMinutes * 60));

        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || ((int) ($_SERVER['SERVER_PORT'] ?? 0) === 443);

        $envSecure = $_ENV['SESSION_SECURE'] ?? getenv('SESSION_SECURE') ?: null;
        $secure = $envSecure !== null
            ? filter_var($envSecure, FILTER_VALIDATE_BOOLEAN)
            : $https;

        $cookiePath = Url::basePath();
        if ($cookiePath === '') {
            $cookiePath = '/';
        }

        session_set_cookie_params([
            'lifetime' => 0,
            'path' => $cookiePath,
            'secure' => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);

        session_start();

        $now = time();
        $startedAt = (int) ($_SESSION['_session_started_at'] ?? $now);
        $lastActivity = (int) ($_SESSION['_session_last_activity'] ?? $now);

        $idleExpired = ($now - $lastActivity) > ($idleMinutes * 60);
        $absoluteExpired = ($now - $startedAt) > ($absoluteMinutes * 60);

        if ($idleExpired || $absoluteExpired) {
            self::destroy();
            session_start();
            $_SESSION['_session_started_at'] = $now;
            $_SESSION['_session_last_activity'] = $now;
            $_SESSION['_session_last_regenerated'] = $now;
            return;
        }

        $_SESSION['_session_started_at'] = $startedAt;
        $_SESSION['_session_last_activity'] = $now;

        $lastRegenerated = (int) ($_SESSION['_session_last_regenerated'] ?? $startedAt);
        if (($now - $lastRegenerated) >= ($regenerateMinutes * 60)) {
            session_regenerate_id(true);
            $_SESSION['_session_last_regenerated'] = $now;
        }
    }

    public static function set(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    public static function has(string $key): bool
    {
        return array_key_exists($key, $_SESSION);
    }

    public static function remove(string $key): void
    {
        unset($_SESSION[$key]);
    }

    public static function destroy(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            $_SESSION = [];
            return;
        }

        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                [
                    'expires' => time() - 42000,
                    'path' => $params['path'] ?: '/',
                    'domain' => $params['domain'] ?? '',
                    'secure' => (bool) ($params['secure'] ?? false),
                    'httponly' => (bool) ($params['httponly'] ?? true),
                    'samesite' => $params['samesite'] ?? 'Lax',
                ]
            );
        }

        session_destroy();
    }

    public static function flash(string $key, mixed $value = null): mixed
    {
        if (func_num_args() === 2) {
            self::set('flash_' . $key, $value);
            return null;
        }

        $flashKey = 'flash_' . $key;
        if (!array_key_exists($flashKey, $_SESSION)) {
            return null;
        }

        $flash = $_SESSION[$flashKey];
        unset($_SESSION[$flashKey]);
        return $flash;
    }
}
