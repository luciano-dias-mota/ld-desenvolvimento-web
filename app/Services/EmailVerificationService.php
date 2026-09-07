<?php

namespace App\Services;

use App\Core\Database;
use App\Core\Url;
use App\Models\User;
use PDO;

final class EmailVerificationService
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::getInstance()->getConnection();
    }

    public function isEnabled(): bool
    {
        return filter_var(
            $_ENV['EMAIL_VERIFICATION_ENABLED'] ?? getenv('EMAIL_VERIFICATION_ENABLED') ?: false,
            FILTER_VALIDATE_BOOLEAN
        );
    }

    public function certificateVerificationRequired(): bool
    {
        return filter_var(
            $_ENV['REQUIRE_EMAIL_VERIFICATION_FOR_CERTIFICATE'] ?? getenv('REQUIRE_EMAIL_VERIFICATION_FOR_CERTIFICATE') ?: false,
            FILTER_VALIDATE_BOOLEAN
        );
    }

    public function canIssueCertificate(array $user): bool
    {
        return !$this->certificateVerificationRequired() || !empty($user['email_verified_at']);
    }

    public function sendForUser(array $user): bool
    {
        if (!$this->isEnabled() || !empty($user['email_verified_at'])) {
            return false;
        }

        $userId = (int) ($user['id'] ?? 0);
        $email = strtolower(trim((string) ($user['email'] ?? '')));
        $name = trim((string) ($user['name'] ?? 'Aluno'));
        if ($userId <= 0 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        $token = $this->issueToken($userId);
        $verificationUrl = Url::to('/verificar-email/' . rawurlencode($token));

        return (new BrevoMailer())->sendEmailVerification($email, $name, $verificationUrl);
    }

    public function issueToken(int $userId): string
    {
        $ttl = max(10, min(1440, (int) ($_ENV['EMAIL_VERIFICATION_TTL_MINUTES'] ?? getenv('EMAIL_VERIFICATION_TTL_MINUTES') ?: 60)));
        $rawToken = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $rawToken);
        $expiresAt = date('Y-m-d H:i:s', time() + ($ttl * 60));

        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare('DELETE FROM email_verification_tokens WHERE user_id = ? AND used_at IS NULL');
            $stmt->execute([$userId]);

            $stmt = $this->db->prepare(
                'INSERT INTO email_verification_tokens (user_id, token_hash, expires_at)
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

        return $rawToken;
    }

    public function verifyToken(string $rawToken): ?array
    {
        if (!preg_match('/^[a-f0-9]{64}$/', $rawToken)) {
            return null;
        }

        $hash = hash('sha256', $rawToken);
        $this->db->beginTransaction();

        try {
            $stmt = $this->db->prepare(
                'SELECT evt.id, evt.user_id, evt.expires_at, evt.used_at
                 FROM email_verification_tokens evt
                 WHERE evt.token_hash = ?
                 LIMIT 1
                 FOR UPDATE'
            );
            $stmt->execute([$hash]);
            $token = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

            if (!$token || $token['used_at'] !== null || strtotime((string) $token['expires_at']) < time()) {
                $this->db->rollBack();
                return null;
            }

            User::markEmailVerified((int) $token['user_id']);
            $stmt = $this->db->prepare('UPDATE email_verification_tokens SET used_at = NOW() WHERE id = ?');
            $stmt->execute([(int) $token['id']]);

            $this->db->commit();
            return User::findPublicById((int) $token['user_id']);
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }
}
