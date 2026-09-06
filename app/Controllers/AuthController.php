<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Session;
use App\Models\User;

class AuthController extends Controller
{
    private const LOGIN_MAX_ATTEMPTS = 5;
    private const LOGIN_WINDOW_SECONDS = 300;
    private const LOGIN_LOCK_SECONDS = 300;

    public function showLoginForm(): void
    {
        $this->view('auth/login');
    }

    public function login(): void
    {
        if (!$this->validateCsrf()) {
            Session::flash('error', 'Sessão expirada. Recarregue a página e tente novamente.');
            $this->redirect('/login');
        }

        if ($this->isLoginLocked()) {
            Session::flash('error', 'Muitas tentativas de acesso. Aguarde alguns minutos e tente novamente.');
            $this->redirect('/login');
        }

        $email = strtolower(trim((string) ($_POST['email'] ?? '')));
        $password = (string) ($_POST['password'] ?? '');

        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $password === '') {
            $this->recordFailedLogin();
            Session::flash('error', 'Credenciais inválidas.');
            $this->redirect('/login');
        }

        if (!Auth::attempt($email, $password)) {
            $this->recordFailedLogin();
            Session::flash('error', 'Credenciais inválidas.');
            $this->redirect('/login');
        }

        $this->clearLoginAttempts();
        $this->redirect('/dashboard');
    }

    public function showRegisterForm(): void
    {
        $this->view('auth/register');
    }

    public function register(): void
    {
        if (!$this->validateCsrf()) {
            Session::flash('error', 'Sessão expirada. Recarregue a página e tente novamente.');
            $this->redirect('/register');
        }

        $name = trim((string) ($_POST['name'] ?? ''));
        $email = strtolower(trim((string) ($_POST['email'] ?? '')));
        $password = (string) ($_POST['password'] ?? '');

        $nameLength = function_exists('mb_strlen') ? mb_strlen($name) : strlen($name);

        if ($nameLength < 2 || $nameLength > 100) {
            Session::flash('error', 'Informe um nome entre 2 e 100 caracteres.');
            $this->redirect('/register');
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 190) {
            Session::flash('error', 'Informe um email válido.');
            $this->redirect('/register');
        }

        if (strlen($password) < 8 || strlen($password) > 128) {
            Session::flash('error', 'A senha deve ter entre 8 e 128 caracteres.');
            $this->redirect('/register');
        }

        if (User::firstWhere('email', $email)) {
            Session::flash('error', 'Não foi possível criar a conta com os dados informados.');
            $this->redirect('/register');
        }

        try {
            $userId = User::create([
                'name' => $name,
                'email' => $email,
                'password' => password_hash($password, PASSWORD_DEFAULT),
                'role' => 'student',
                'xp' => 0,
            ]);
        } catch (\PDOException $e) {
            error_log('Erro ao cadastrar usuário: ' . $e->getMessage());
            Session::flash('error', 'Não foi possível criar a conta. Verifique os dados e tente novamente.');
            $this->redirect('/register');
        }

        if ($userId <= 0 || !Auth::attempt($email, $password)) {
            Session::flash('error', 'Conta criada, mas não foi possível iniciar a sessão. Faça login.');
            $this->redirect('/login');
        }

        Session::flash('success', 'Conta criada com sucesso.');
        $this->redirect('/dashboard');
    }

    public function logout(): void
    {
        if (!$this->validateCsrf()) {
            Session::flash('error', 'Não foi possível validar a solicitação de saída.');
            $this->redirect('/dashboard');
        }

        Auth::logout();
        $this->redirect('/login');
    }

    private function isLoginLocked(): bool
    {
        $state = Session::get('login_attempts', []);
        if (!is_array($state)) {
            return false;
        }

        $lockedUntil = (int) ($state['locked_until'] ?? 0);
        if ($lockedUntil > time()) {
            return true;
        }

        if ($lockedUntil > 0) {
            $this->clearLoginAttempts();
        }

        return false;
    }

    private function recordFailedLogin(): void
    {
        $now = time();
        $state = Session::get('login_attempts', []);

        if (!is_array($state) || ($now - (int) ($state['first_at'] ?? 0)) > self::LOGIN_WINDOW_SECONDS) {
            $state = [
                'count' => 0,
                'first_at' => $now,
                'locked_until' => 0,
            ];
        }

        $state['count'] = (int) ($state['count'] ?? 0) + 1;

        if ($state['count'] >= self::LOGIN_MAX_ATTEMPTS) {
            $state['locked_until'] = $now + self::LOGIN_LOCK_SECONDS;
        }

        Session::set('login_attempts', $state);
    }

    private function clearLoginAttempts(): void
    {
        Session::remove('login_attempts');
    }
}
