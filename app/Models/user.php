<?php

namespace App\Models;

use App\Core\Database;
use App\Core\Model;
use PDO;

class User extends Model
{
    protected static string $table = 'users';

    protected static array $fillable = [
        'name',
        'email',
        'password',
        'google_sub',
        'email_verified_at',
        'role',
        'xp',
    ];

    public static function findForAuthByEmail(string $email): ?array
    {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare(
            'SELECT id, name, email, password, google_sub, email_verified_at, role, xp, created_at, updated_at
             FROM users
             WHERE email = ?
             LIMIT 1'
        );
        $stmt->execute([strtolower(trim($email))]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public static function findByGoogleSub(string $googleSub): ?array
    {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare(
            'SELECT id, name, email, password, google_sub, email_verified_at, role, xp, created_at, updated_at
             FROM users
             WHERE google_sub = ?
             LIMIT 1'
        );
        $stmt->execute([$googleSub]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public static function findPublicById(int $id): ?array
    {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare(
            'SELECT id, name, email, email_verified_at, role, xp, created_at, updated_at
             FROM users
             WHERE id = ?
             LIMIT 1'
        );
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public static function emailExists(string $email): bool
    {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare('SELECT 1 FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([strtolower(trim($email))]);
        return (bool) $stmt->fetchColumn();
    }

    public static function linkGoogleIdentity(int $userId, string $googleSub, bool $markEmailVerified): bool
    {
        $db = Database::getInstance()->getConnection();
        $sql = 'UPDATE users SET google_sub = ?';
        $params = [$googleSub];

        if ($markEmailVerified) {
            $sql .= ', email_verified_at = COALESCE(email_verified_at, NOW())';
        }

        $sql .= ' WHERE id = ?';
        $params[] = $userId;

        $stmt = $db->prepare($sql);
        return $stmt->execute($params);
    }

    public static function markEmailVerified(int $userId): bool
    {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare('UPDATE users SET email_verified_at = COALESCE(email_verified_at, NOW()) WHERE id = ?');
        return $stmt->execute([$userId]);
    }
}
