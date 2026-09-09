<?php

namespace App\Repositories;

use PDO;

class AdminRepository
{
    public function __construct(private PDO $db)
    {
    }

    public function dashboardStats(): array
    {
        return $this->db->query(
            'SELECT
                (SELECT COUNT(*) FROM users) AS usuarios,
                (SELECT COUNT(*) FROM courses) AS cursos,
                (SELECT COUNT(*) FROM modules) AS modulos,
                (SELECT COUNT(*) FROM lessons) AS aulas'
        )->fetch() ?: [
            'usuarios' => 0,
            'cursos' => 0,
            'modulos' => 0,
            'aulas' => 0,
        ];
    }

    public function coursesNewestFirst(): array
    {
        return $this->db
            ->query('SELECT * FROM courses ORDER BY id DESC')
            ->fetchAll();
    }
}
