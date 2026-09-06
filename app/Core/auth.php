<?php

namespace App\Core;

use App\Models\User;

class Auth
{
    public static function attempt(string $email, string $password): bool
    {
        $email = strtolower(trim($email));
        $user = User::firstWhere('email', $email);

        if (!$user || !password_verify($password, $user['password'])) {
            return false;
        }

        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }

        Session::set('user_id', (int) $user['id']);

        if (password_needs_rehash($user['password'], PASSWORD_DEFAULT)) {
            try {
                User::update((int) $user['id'], [
                    'password' => password_hash($password, PASSWORD_DEFAULT),
                ]);
            } catch (\Throwable $e) {
                error_log('Falha ao rehash da senha do usuário ' . (int) $user['id'] . ': ' . $e->getMessage());
            }
        }

        return true;
    }

    public static function user(): ?array
    {
        $userId = Session::get('user_id');
        if (!$userId) {
            return null;
        }

        $user = User::find((int) $userId);
        if (!$user) {
            Session::remove('user_id');
            return null;
        }

        return $user;
    }

    public static function check(): bool
    {
        return self::user() !== null;
    }

    public static function isAdmin(): bool
    {
        $user = self::user();
        return $user !== null && ($user['role'] ?? null) === 'admin';
    }

    public static function logout(): void
    {
        Session::destroy();
    }
}
