<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Session;

class AdminController extends Controller
{
    public function dashboard(): void
    {
        if (!Auth::isAdmin()) {
            Session::flash('error', 'Acesso restrito a administradores.');
            $this->redirect('/dashboard');
        }

        $stats = $this->db()->query(
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

        $courses = $this->db()
            ->query('SELECT * FROM courses ORDER BY id DESC')
            ->fetchAll();

        $this->view('admin/dashboard', compact('stats', 'courses'));
    }
}
