<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Session;
use App\Services\PasswordResetService;

final class PasswordResetController extends Controller
{
    public function showRequestForm(): void
    {
        $this->view('auth/forgot-password', [
            'title' => 'Recuperar senha — LD Desenvolvimento Web',
        ]);
    }

    public function requestLink(): void
    {
        if (!$this->validateCsrf()) {
            Session::flash('error', 'Sessão expirada. Recarregue a página e tente novamente.');
            $this->redirect('/esqueci-senha');
        }

        $email = strtolower(trim((string) ($_POST['email'] ?? '')));

        try {
            (new PasswordResetService($this->db()))->requestForEmail($email);
        } catch (\Throwable $e) {
            error_log('Falha ao solicitar redefinição de senha: ' . $e->getMessage());
        }

        // Mensagem propositalmente genérica para não revelar contas cadastradas.
        Session::flash(
            'success',
            'Se existir uma conta com esse e-mail, enviaremos um link para redefinir a senha.'
        );
        $this->redirect('/login');
    }

    public function showResetForm(string $token): void
    {
        $service = new PasswordResetService($this->db());
        if (!$service->tokenIsValid($token)) {
            Session::flash('error', 'O link de redefinição é inválido, expirou ou já foi utilizado.');
            $this->redirect('/esqueci-senha');
        }

        $this->view('auth/reset-password', [
            'title' => 'Criar nova senha — LD Desenvolvimento Web',
            'resetToken' => $token,
        ]);
    }

    public function reset(string $token): void
    {
        if (!$this->validateCsrf()) {
            Session::flash('error', 'Sessão expirada. Abra novamente o link recebido por e-mail.');
            $this->redirect('/esqueci-senha');
        }

        $password = (string) ($_POST['password'] ?? '');
        $confirmation = (string) ($_POST['password_confirmation'] ?? '');

        if (strlen($password) < 8 || strlen($password) > 128) {
            Session::flash('error', 'A senha deve ter entre 8 e 128 caracteres.');
            $this->redirect('/redefinir-senha/' . rawurlencode($token));
        }

        if (!hash_equals($password, $confirmation)) {
            Session::flash('error', 'A confirmação da senha não confere.');
            $this->redirect('/redefinir-senha/' . rawurlencode($token));
        }

        try {
            $changed = (new PasswordResetService($this->db()))
                ->resetPassword($token, $password);
        } catch (\Throwable $e) {
            error_log('Falha ao redefinir senha: ' . $e->getMessage());
            $changed = false;
        }

        if (!$changed) {
            Session::flash('error', 'O link de redefinição é inválido, expirou ou já foi utilizado.');
            $this->redirect('/esqueci-senha');
        }

        Session::flash('success', 'Senha redefinida com sucesso. Você já pode entrar com a nova senha.');
        $this->redirect('/login');
    }
}
