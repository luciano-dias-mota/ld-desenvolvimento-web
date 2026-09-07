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
            password_verify($password, self::DUMMY_PASSWORD_HASH);
            return false;
        }

        $hash = $user['password'] ?? null;
        if (!is_string($hash) || $hash === '') {
            password_verify($password, self::DUMMY_PASSWORD_HASH);
            return false;
        }

        if (!password_verify($password, $hash)) {
            return false;
        }

        self::loginUser((int) $user['id']);

        if (password_needs_rehash($hash, PASSWORD_DEFAULT)) {
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

    public static function loginUser(int $userId): bool
    {
        if ($userId <= 0 || !User::findPublicById($userId)) {
            return false;
        }

        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }

        Session::remove('guest_mode');
        Session::set('user_id', $userId);
        Session::set('csrf_token', bin2hex(random_bytes(32)));
        Session::set('_session_started_at', time());
        Session::set('_session_last_activity', time());

        self::$resolved = false;
        self::$cachedUser = null;
        self::user();

        return true;
    }

    public static function enterGuestMode(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }

        Session::remove('user_id');
        Session::set('guest_mode', true);
        Session::set('csrf_token', bin2hex(random_bytes(32)));
        Session::set('_session_started_at', time());
        Session::set('_session_last_activity', time());

        self::$cachedUser = null;
        self::$resolved = true;
    }

    public static function exitGuestMode(): void
    {
        Session::remove('guest_mode');
        self::$cachedUser = null;
        self::$resolved = false;
    }

    public static function isGuest(): bool
    {
        return !self::check() && Session::get('guest_mode') === true;
    }

    public static function hasLearningAccess(): bool
    {
        return self::check() || self::isGuest();
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
