<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Auth;
use App\Models\Course;
use App\Models\User;

class AdminController extends Controller
{
    public function dashboard()
    {
        $user = Auth::user();
        if (!$user || $user['role'] !== 'admin') {
            return $this->redirect('/');
        }

        // Estatísticas
        $db = $this->db;
        $stats = [];
        $stats['usuarios'] = $db->query("SELECT COUNT(*) FROM users")->fetchColumn();
        $stats['cursos'] = $db->query("SELECT COUNT(*) FROM courses")->fetchColumn();
        $stats['modulos'] = $db->query("SELECT COUNT(*) FROM modules")->fetchColumn();
        $stats['aulas'] = $db->query("SELECT COUNT(*) FROM lessons")->fetchColumn();

        // Listar cursos
        $courseModel = new Course();
        $courses = $courseModel->all();

        $this->view('admin/dashboard', compact('stats', 'courses'));
    }
}