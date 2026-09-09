<?php

namespace App\Repositories;

use PDO;

class CertificateRepository
{
    public function __construct(private PDO $db)
    {
    }

    public function findByCode(string $code): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT id, user_id, course_id, certificate_code, issued_at
             FROM certificates
             WHERE certificate_code = ?
             LIMIT 1'
        );
        $stmt->execute([$code]);

        return $stmt->fetch() ?: null;
    }
}
