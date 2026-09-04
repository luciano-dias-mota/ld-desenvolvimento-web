<?php

namespace App\Models;

use App\Core\Model;

class Certificate extends Model
{
    protected $table = 'certificates';
    protected $fillable = ['user_id', 'course_id', 'certificate_code', 'issued_at'];

    // Gera um código único
    public static function generateCode()
    {
        return 'CERT-' . strtoupper(uniqid());
    }

    // Verifica se o usuário tem certificado para o curso
    public static function getUserCertificate($userId, $courseId)
    {
        $db = (new self())->db;
        $stmt = $db->prepare("SELECT * FROM certificates WHERE user_id = ? AND course_id = ?");
        $stmt->execute([$userId, $courseId]);
        return $stmt->fetch(\PDO::FETCH_ASSOC);
    }

    // Cria certificado
    public static function createCertificate($userId, $courseId)
    {
        $code = self::generateCode();
        $db = (new self())->db;
        $stmt = $db->prepare("INSERT INTO certificates (user_id, course_id, certificate_code, issued_at) VALUES (?, ?, ?, NOW())");
        $stmt->execute([$userId, $courseId, $code]);
        return $db->lastInsertId();
    }
}