<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Session;
use App\Repositories\AdminRepository;

class AdminController extends Controller
{
    public function dashboard(): void
    {
        if (!Auth::isAdmin()) {
            Session::flash('error', 'Acesso restrito a administradores.');
            $this->redirect('/dashboard');
        }

        $repository = new AdminRepository($this->db());
        $stats = $repository->dashboardStats();
        $courses = $repository->coursesNewestFirst();

        $this->view('admin/dashboard', compact('stats', 'courses'));
    }
}
