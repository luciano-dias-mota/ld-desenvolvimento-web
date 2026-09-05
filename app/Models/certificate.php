<?php

namespace App\Models;

use App\Core\Model;
use App\Core\Database;

class Certificate extends Model
{
    protected static string $table = 'certificates';

    /**
     * Gera um código único para o certificado.
     *
     * @return string
     */
    public static function generateCode()
    {
        return 'CERT-' . strtoupper(uniqid());
    }

    /**
     * Verifica se o usuário possui certificado para um curso.
     *
     * @param int $userId
     * @param int $courseId
     * @return array|null
     */
    public static function getUserCertificate($userId, $courseId)
    {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare(
            "SELECT * FROM certificates 
             WHERE user_id = ? AND course_id = ?"
        );
        $stmt->execute([$userId, $courseId]);
        return $stmt->fetch() ?: null;
    }

    /**
     * Cria um certificado para o usuário e curso.
     *
     * @param int $userId
     * @param int $courseId
     * @return int
     */
    public static function createCertificate($userId, $courseId)
    {
        $code = self::generateCode();
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare(
            "INSERT INTO certificates 
             (user_id, course_id, certificate_code, issued_at) 
             VALUES (?, ?, ?, NOW())"
        );
        $stmt->execute([$userId, $courseId, $code]);
        return (int) $db->lastInsertId();
    }
}