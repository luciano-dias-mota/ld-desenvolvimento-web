<?php

namespace App\Models;

use App\Core\Database;
use App\Core\Model;
use PDO;
use PDOException;

class Certificate extends Model
{
    protected static string $table = 'certificates';

    protected static array $fillable = [
        'user_id',
        'course_id',
        'certificate_code',
        'issued_at',
    ];

    public static function generateCode(): string
    {
        return 'CERT-' . strtoupper(bin2hex(random_bytes(16)));
    }

    public static function getUserCertificate(int $userId, int $courseId): ?array
    {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare(
            'SELECT id, user_id, course_id, certificate_code, issued_at
             FROM certificates
             WHERE user_id = ? AND course_id = ?
             ORDER BY id ASC
             LIMIT 1'
        );
        $stmt->execute([$userId, $courseId]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Idempotência garantida pela UNIQUE(user_id, course_id) do banco.
     * Em corrida concorrente, uma requisição insere e a outra reutiliza o registro.
     */
    public static function createCertificate(int $userId, int $courseId): int
    {
        $db = Database::getInstance()->getConnection();

        $existing = self::getUserCertificate($userId, $courseId);
        if ($existing) {
            return (int) $existing['id'];
        }

        for ($attempt = 1; $attempt <= 3; $attempt++) {
            try {
                $stmt = $db->prepare(
                    'INSERT INTO certificates
                        (user_id, course_id, certificate_code, issued_at)
                     VALUES (?, ?, ?, NOW())'
                );
                $stmt->execute([$userId, $courseId, self::generateCode()]);
                return (int) $db->lastInsertId();
            } catch (PDOException $e) {
                if ($e->getCode() !== '23000') {
                    throw $e;
                }

                // Se foi corrida no UNIQUE(user_id, course_id), reaproveita o existente.
                $existing = self::getUserCertificate($userId, $courseId);
                if ($existing) {
                    return (int) $existing['id'];
                }

                // Caso raríssimo de colisão de certificate_code: tenta um novo código.
                if ($attempt === 3) {
                    throw $e;
                }
            }
        }

        throw new \RuntimeException('Não foi possível emitir o certificado.');
    }
}
