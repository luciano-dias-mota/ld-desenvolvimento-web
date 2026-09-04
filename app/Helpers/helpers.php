<?php

use App\Core\Session;

if (!function_exists('url')) {
    function url(string $path = ''): string
    {
        $base = rtrim($_ENV['APP_URL'] ?? 'http://localhost:8000', '/');
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
        return htmlspecialchars($string ?? '', ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('csrf_field')) {
    function csrf_field(): string
    {
        $token = bin2hex(random_bytes(32));
        Session::set('csrf_token', $token);
        return '<input type="hidden" name="csrf_token" value="' . $token . '">';
    }
}

if (!function_exists('old')) {
    function old(string $key, $default = '')
    {
        return $_POST[$key] ?? $default;
    }
}