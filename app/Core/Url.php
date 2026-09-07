<?php

namespace App\Core;

final class Url
{
    public static function base(): string
    {
        $base = (string) ($_ENV['APP_URL'] ?? getenv('APP_URL') ?: 'http://localhost/ld-desenvolvimento-web/public');
        return rtrim($base, '/');
    }

    public static function to(string $path = ''): string
    {
        if ($path === '') {
            return self::base() . '/';
        }

        if (preg_match('#^https?://#i', $path)) {
            return $path;
        }

        return self::base() . '/' . ltrim($path, '/');
    }

    public static function basePath(): string
    {
        $path = parse_url(self::base(), PHP_URL_PATH);
        if (!is_string($path) || $path === '' || $path === '/') {
            return '';
        }

        return '/' . trim($path, '/');
    }
}
