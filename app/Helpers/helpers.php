<?php

use App\Core\Session;

if (!function_exists('url')) {
    function url(string $path = ''): string
    {
        $base = rtrim((string) ($_ENV['APP_URL'] ?? 'http://localhost:8000'), '/');
        return $base . '/' . ltrim($path, '/');
    }
}

if (!function_exists('asset')) {
    function asset(string $path): string
    {
        return url($path);
    }
}

if (!function_exists('e')) {
    function e(?string $string): string
    {
        return htmlspecialchars($string ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('csrf_field')) {
    function csrf_field(): string
    {
        Session::start();

        $token = Session::get('csrf_token');
        if (!is_string($token) || $token === '') {
            $token = bin2hex(random_bytes(32));
            Session::set('csrf_token', $token);
        }

        return '<input type="hidden" name="csrf_token" value="' . e($token) . '">';
    }
}

if (!function_exists('verify_csrf')) {
    function verify_csrf(): bool
    {
        Session::start();

        $token = $_POST['csrf_token'] ?? null;
        $sessionToken = Session::get('csrf_token');

        if (!is_string($token) || !is_string($sessionToken) || $token === '' || $sessionToken === '') {
            return false;
        }

        return hash_equals($sessionToken, $token);
    }
}

if (!function_exists('old')) {
    function old(string $key, mixed $default = ''): mixed
    {
        return $_POST[$key] ?? $default;
    }
}
