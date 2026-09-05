<?php

namespace App\Core;

use App\Models\User;

class Auth
{
    public static function attempt(string $email, string $password): bool
    {
        $user = User::firstWhere('email', $email);
        if ($user && password_verify($password, $user['password'])) {
            Session::set('user_id', $user['id']);
            Session::set('user_role', $user['role']);
            return true;
        }
        return false;
    }

    public static function user(): ?array
    {
        $userId = Session::get('user_id');
        if ($userId) {
            return User::find($userId);
        }
        return null;
    }

    public static function check(): bool
    {
        return Session::has('user_id');
    }

    public static function isAdmin(): bool
    {
        return Session::get('user_role') === 'admin';
    }

    public static function logout(): void
    {
        Session::destroy();
    }
}