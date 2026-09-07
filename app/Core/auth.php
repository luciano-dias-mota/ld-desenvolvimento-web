<?php

namespace App\Core;

use App\Models\User;

class Auth
{
    private const DUMMY_PASSWORD_HASH = '$2y$10$90sDne/xrqAJX5Cf4D.et.yBngoRy6lWAqZxEfxbibzwysG8qLswe';

    private static ?array $cachedUser = null;
    private static bool $resolved = false;

    public static function attempt(string $email, string $password): bool
    {
        $email = strtolower(trim($email));
        $user = User::findForAuthByEmail($email);

        if (!$user) {
            // Mantém custo semelhante ao caso de e-mail existente para reduzir
            // enumeração de contas por diferença de tempo de resposta.
            password_verify($password, self::DUMMY_PASSWORD_HASH);
            return false;
        }

        if (!password_verify($password, (string) $user['password'])) {
            return false;
        }

        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }

        Session::set('user_id', (int) $user['id']);
        Session::set('csrf_token', bin2hex(random_bytes(32)));
        Session::set('_session_started_at', time());
        Session::set('_session_last_activity', time());

        if (password_needs_rehash((string) $user['password'], PASSWORD_DEFAULT)) {
            try {
                User::update((int) $user['id'], [
                    'password' => password_hash($password, PASSWORD_DEFAULT),
                ]);
            } catch (\Throwable $e) {
                error_log('Falha ao rehash da senha do usuário ' . (int) $user['id'] . ': ' . $e->getMessage());
            }
        }

        self::$resolved = false;
        self::$cachedUser = null;
        self::user();

        return true;
    }

    public static function user(): ?array
    {
        if (self::$resolved) {
            return self::$cachedUser;
        }

        self::$resolved = true;
        $userId = Session::get('user_id');
        if (!$userId) {
            self::$cachedUser = null;
            return null;
        }

        $user = User::findPublicById((int) $userId);
        if (!$user) {
            Session::remove('user_id');
            self::$cachedUser = null;
            return null;
        }

        self::$cachedUser = $user;
        return self::$cachedUser;
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
        self::$cachedUser = null;
        self::$resolved = false;
        Session::destroy();
    }
}
