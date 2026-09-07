<?php

namespace App\Services;

use App\Core\Database;
use PDO;

final class LoginThrottle
{
    private PDO $db;
    private int $maxAttempts;
    private int $windowSeconds;
    private int $lockSeconds;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::getInstance()->getConnection();
        $this->maxAttempts = max(2, (int) ($_ENV['LOGIN_MAX_ATTEMPTS'] ?? getenv('LOGIN_MAX_ATTEMPTS') ?: 5));
        $this->windowSeconds = max(60, (int) ($_ENV['LOGIN_WINDOW_SECONDS'] ?? getenv('LOGIN_WINDOW_SECONDS') ?: 300));
        $this->lockSeconds = max(60, (int) ($_ENV['LOGIN_LOCK_SECONDS'] ?? getenv('LOGIN_LOCK_SECONDS') ?: 300));
    }

    public function isBlocked(string $email): bool
    {
        [$emailHash, $ipHash] = $this->keys($email);
        $stmt = $this->db->prepare(
            'SELECT locked_until
             FROM login_attempts
             WHERE email_hash = ? AND ip_hash = ?
             LIMIT 1'
        );
        $stmt->execute([$emailHash, $ipHash]);
        $lockedUntil = $stmt->fetchColumn();

        if (!is_string($lockedUntil) || $lockedUntil === '') {
            return false;
        }

        return strtotime($lockedUntil) > time();
    }

    public function recordFailure(string $email): void
    {
        [$emailHash, $ipHash] = $this->keys($email);
        $now = time();

        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare(
                'INSERT IGNORE INTO login_attempts
                    (email_hash, ip_hash, attempts, window_started_at, locked_until, updated_at)
                 VALUES (?, ?, 0, NOW(), NULL, NOW())'
            );
            $stmt->execute([$emailHash, $ipHash]);

            $stmt = $this->db->prepare(
                'SELECT id, attempts, window_started_at, locked_until
                 FROM login_attempts
                 WHERE email_hash = ? AND ip_hash = ?
                 LIMIT 1
                 FOR UPDATE'
            );
            $stmt->execute([$emailHash, $ipHash]);
            $state = $stmt->fetch();

            if (!$state) {
                throw new \RuntimeException('Não foi possível registrar a tentativa de login.');
            }

            $windowStarted = strtotime((string) $state['window_started_at']) ?: $now;
            $attempts = (int) $state['attempts'];

            if (($now - $windowStarted) > $this->windowSeconds) {
                $attempts = 0;
                $windowStarted = $now;
            }

            $attempts++;
            $lockedUntil = null;
            if ($attempts >= $this->maxAttempts) {
                $lockedUntil = date('Y-m-d H:i:s', $now + $this->lockSeconds);
            }

            $stmt = $this->db->prepare(
                'UPDATE login_attempts
                 SET attempts = ?, window_started_at = ?, locked_until = ?, updated_at = NOW()
                 WHERE id = ?'
            );
            $stmt->execute([
                $attempts,
                date('Y-m-d H:i:s', $windowStarted),
                $lockedUntil,
                $state['id'],
            ]);

            $this->db->commit();
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }

        $this->maybeCleanup();
    }

    public function clear(string $email): void
    {
        [$emailHash, $ipHash] = $this->keys($email);
        $stmt = $this->db->prepare(
            'DELETE FROM login_attempts WHERE email_hash = ? AND ip_hash = ?'
        );
        $stmt->execute([$emailHash, $ipHash]);
    }

    private function keys(string $email): array
    {
        $normalizedEmail = strtolower(trim($email));
        $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown');

        return [
            hash('sha256', $normalizedEmail),
            hash('sha256', $ip),
        ];
    }

    private function maybeCleanup(): void
    {
        try {
            if (random_int(1, 100) !== 1) {
                return;
            }

            $this->db->exec(
                "DELETE FROM login_attempts
                 WHERE updated_at < (NOW() - INTERVAL 7 DAY)"
            );
        } catch (\Throwable $e) {
            error_log('Falha ao limpar tentativas antigas de login: ' . $e->getMessage());
        }
    }
}
