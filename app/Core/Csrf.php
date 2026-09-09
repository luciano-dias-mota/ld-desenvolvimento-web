<?php

namespace App\Core;

final class Csrf
{
    private const SESSION_KEY = 'csrf_token';
    private const FIELD_NAME = 'csrf_token';

    public static function token(): string
    {
        Session::start();

        $token = Session::get(self::SESSION_KEY);
        if (!is_string($token) || $token === '') {
            $token = bin2hex(random_bytes(32));
            Session::set(self::SESSION_KEY, $token);
        }

        return $token;
    }

    public static function field(): string
    {
        return '<input type="hidden" name="' . self::FIELD_NAME . '" value="'
            . htmlspecialchars(self::token(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
            . '">';
    }

    public static function validate(mixed $token): bool
    {
        Session::start();

        $sessionToken = Session::get(self::SESSION_KEY);

        if (!is_string($token) || !is_string($sessionToken) || $token === '' || $sessionToken === '') {
            return false;
        }

        return hash_equals($sessionToken, $token);
    }
}
