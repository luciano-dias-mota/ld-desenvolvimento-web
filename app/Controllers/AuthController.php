<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Auth;
use App\Core\Session;
use App\Models\User;

class AuthController extends Controller
{
    public function showLoginForm(): void
    {
        $this->view('auth/login');
    }

    public function login(): void
    {
        if (!verify_csrf()) {
            Session::flash('error', 'Sessão expirada. Recarregue a página e tente novamente.');
            $this->view('auth/login');
            return;
        }

        $email = $_POST['email'] ?? '';
        $password = $_POST['password'] ?? '';

        if (Auth::attempt($email, $password)) {
            header('Location: /dashboard');
            exit;
        } else {
            Session::flash('error', 'Credenciais inválidas.');
            $this->view('auth/login');
        }
    }

    public function showRegisterForm(): void
    {
        $this->view('auth/register');
    }

    public function register(): void
    {
        if (!verify_csrf()) {
            Session::flash('error', 'Sessão expirada. Recarregue a página e tente novamente.');
            $this->view('auth/register');
            return;
        }

        $name = $_POST['name'] ?? '';
        $email = $_POST['email'] ?? '';
        $password = $_POST['password'] ?? '';

        // Validação básica
        if (empty($name) || empty($email) || empty($password)) {
            Session::flash('error', 'Preencha todos os campos.');
            $this->view('auth/register');
            return;
        }

        // Verifica se email já existe
        if (User::firstWhere('email', $email)) {
            Session::flash('error', 'Email já registrado.');
            $this->view('auth/register');
            return;
        }

        $userId = User::create([
            'name' => $name,
            'email' => $email,
            'password' => password_hash($password, PASSWORD_DEFAULT),
            'role' => 'student',
            'xp' => 0
        ]);

        if ($userId) {
            Auth::attempt($email, $password);
            header('Location: /dashboard');
            exit;
        } else {
            Session::flash('error', 'Erro ao criar conta.');
            $this->view('auth/register');
        }
    }

    public function logout(): void
    {
        if (verify_csrf()) {
            Auth::logout();
        }
        header('Location: /login');
        exit;
    }
}