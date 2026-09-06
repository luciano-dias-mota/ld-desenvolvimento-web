<?php

namespace App\Models;

use App\Core\Database;
use App\Core\Model;
use PDO;

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
            'SELECT *
             FROM certificates
             WHERE user_id = ? AND course_id = ?
             ORDER BY id ASC
             LIMIT 1'
        );
        $stmt->execute([$userId, $courseId]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Criação idempotente: para o mesmo usuário/curso, retorna o certificado
     * já existente em vez de criar outro. A constraint UNIQUE no schema ainda
     * é recomendada como proteção definitiva no banco.
     */
    public static function createCertificate(int $userId, int $courseId): int
    {
        $db = Database::getInstance()->getConnection();
        $ownsTransaction = !$db->inTransaction();

        if ($ownsTransaction) {
            $db->beginTransaction();
        }

        try {
            // Serializa emissões concorrentes do mesmo usuário.
            $stmt = $db->prepare('SELECT id FROM users WHERE id = ? FOR UPDATE');
            $stmt->execute([$userId]);
            if (!$stmt->fetchColumn()) {
                throw new \RuntimeException('Usuário não encontrado para emissão do certificado.');
            }

            $existing = self::getUserCertificate($userId, $courseId);
            if ($existing) {
                if ($ownsTransaction) {
                    $db->commit();
                }
                return (int) $existing['id'];
            }

            $code = self::generateCode();
            $stmt = $db->prepare(
                'INSERT INTO certificates
                    (user_id, course_id, certificate_code, issued_at)
                 VALUES (?, ?, ?, NOW())'
            );
            $stmt->execute([$userId, $courseId, $code]);
            $id = (int) $db->lastInsertId();

            if ($ownsTransaction) {
                $db->commit();
            }

            return $id;
        } catch (\Throwable $e) {
            if ($ownsTransaction && $db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }
}
