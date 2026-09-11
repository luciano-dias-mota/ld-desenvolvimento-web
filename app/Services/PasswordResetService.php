<?php

namespace App\Services;

use App\Core\Database;
use App\Core\Url;
use App\Models\User;
use PDO;

final class PasswordResetService
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::getInstance()->getConnection();
    }

    public function ttlMinutes(): int
    {
        return max(
            10,
            min(
                1440,
                (int) ($_ENV['PASSWORD_RESET_TTL_MINUTES']
                    ?? getenv('PASSWORD_RESET_TTL_MINUTES')
                    ?: 60)
            )
        );
    }

    /**
     * Processa a solicitação sem revelar se o e-mail existe.
     *
     * O retorno indica apenas se houve falha operacional inesperada para log
     * interno. A interface sempre deve exibir a mesma mensagem genérica.
     */
    public function requestForEmail(string $email): bool
    {
        $email = strtolower(trim($email));

        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 190) {
            return true;
        }

        $user = User::findForAuthByEmail($email);
        if (!$user) {
            return true;
        }

        $userId = (int) ($user['id'] ?? 0);
        if ($userId <= 0) {
            return true;
        }

        if ($this->wasRecentlyRequested($userId)) {
            return true;
        }

        $ttl = $this->ttlMinutes();
        $rawToken = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $rawToken);
        $expiresAt = date('Y-m-d H:i:s', time() + ($ttl * 60));

        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare(
                'UPDATE password_reset_tokens
                 SET used_at = NOW()
                 WHERE user_id = ? AND used_at IS NULL'
            );
            $stmt->execute([$userId]);

            $stmt = $this->db->prepare(
                'INSERT INTO password_reset_tokens (user_id, token_hash, expires_at)
                 VALUES (?, ?, ?)'
            );
            $stmt->execute([$userId, $tokenHash, $expiresAt]);
            $this->db->commit();
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }

        $resetUrl = Url::to('/redefinir-senha/' . rawurlencode($rawToken));
        $sent = (new BrevoMailer())->sendPasswordReset(
            $email,
            trim((string) ($user['name'] ?? 'Aluno')),
            $resetUrl,
            $ttl
        );

        if (!$sent) {
            $stmt = $this->db->prepare(
                'UPDATE password_reset_tokens
                 SET used_at = NOW()
                 WHERE token_hash = ? AND used_at IS NULL'
            );
            $stmt->execute([$tokenHash]);
        }

        return $sent;
    }

    public function tokenIsValid(string $rawToken): bool
    {
        if (!preg_match('/^[a-f0-9]{64}$/', $rawToken)) {
            return false;
        }

        $stmt = $this->db->prepare(
            'SELECT 1
             FROM password_reset_tokens
             WHERE token_hash = ?
               AND used_at IS NULL
               AND expires_at >= NOW()
             LIMIT 1'
        );
        $stmt->execute([hash('sha256', $rawToken)]);

        return (bool) $stmt->fetchColumn();
    }

    public function resetPassword(string $rawToken, string $newPassword): bool
    {
        if (!preg_match('/^[a-f0-9]{64}$/', $rawToken)) {
            return false;
        }

        if (strlen($newPassword) < 8 || strlen($newPassword) > 128) {
            return false;
        }

        $tokenHash = hash('sha256', $rawToken);

        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare(
                'SELECT id, user_id, expires_at, used_at
                 FROM password_reset_tokens
                 WHERE token_hash = ?
                 LIMIT 1
                 FOR UPDATE'
            );
            $stmt->execute([$tokenHash]);
            $token = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

            if (!$token
                || $token['used_at'] !== null
                || strtotime((string) $token['expires_at']) < time()
            ) {
                $this->db->rollBack();
                return false;
            }

            $userId = (int) $token['user_id'];
            $stmt = $this->db->prepare(
                'UPDATE users
                 SET password = ?, updated_at = NOW()
                 WHERE id = ?'
            );
            $stmt->execute([password_hash($newPassword, PASSWORD_DEFAULT), $userId]);

            if ($stmt->rowCount() < 1 && !User::findPublicById($userId)) {
                throw new \RuntimeException('Usuário da redefinição não encontrado.');
            }

            $stmt = $this->db->prepare(
                'UPDATE password_reset_tokens
                 SET used_at = NOW()
                 WHERE user_id = ? AND used_at IS NULL'
            );
            $stmt->execute([$userId]);

            $this->db->commit();

            return true;
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }

    private function wasRecentlyRequested(int $userId): bool
    {
        $cooldown = max(
            30,
            min(
                900,
                (int) ($_ENV['PASSWORD_RESET_COOLDOWN_SECONDS']
                    ?? getenv('PASSWORD_RESET_COOLDOWN_SECONDS')
                    ?: 60)
            )
        );

        $stmt = $this->db->prepare(
            'SELECT created_at
             FROM password_reset_tokens
             WHERE user_id = ?
             ORDER BY id DESC
             LIMIT 1'
        );
        $stmt->execute([$userId]);
        $createdAt = $stmt->fetchColumn();

        if (!is_string($createdAt) || $createdAt === '') {
            return false;
        }

        $createdAtTimestamp = strtotime($createdAt);
        if ($createdAtTimestamp === false) {
            return false;
        }

        return (time() - $createdAtTimestamp) < $cooldown;
    }
}
