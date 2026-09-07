<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Session;
use App\Models\User;
use App\Services\EmailVerificationService;
use App\Services\GoogleIdentityService;

final class GoogleAuthController extends Controller
{
    public function login(): void
    {
        if (!$this->validateCsrf()) {
            Session::flash('error', 'Sessão expirada. Recarregue a página e tente novamente.');
            $this->redirect('/login');
        }

        $credential = trim((string) ($_POST['credential'] ?? ''));
        if ($credential === '' || strlen($credential) > 10000) {
            Session::flash('error', 'Não foi possível validar o login com Google.');
            $this->redirect('/login');
        }

        try {
            $identity = (new GoogleIdentityService())->verify($credential);
            $user = User::findByGoogleSub($identity['sub']);

            if (!$user) {
                $existing = User::findForAuthByEmail($identity['email']);

                if ($existing) {
                    if (!$identity['authoritative_email']) {
                        Session::flash('error', 'Já existe uma conta com este e-mail. Entre com sua senha para continuar.');
                        $this->redirect('/login');
                    }

                    User::linkGoogleIdentity(
                        (int) $existing['id'],
                        $identity['sub'],
                        (bool) $identity['authoritative_email']
                    );
                    $user = User::findForAuthByEmail($identity['email']);
                } else {
                    $userId = User::create([
                        'name' => $identity['name'],
                        'email' => $identity['email'],
                        'password' => null,
                        'google_sub' => $identity['sub'],
                        'email_verified_at' => $identity['authoritative_email'] ? date('Y-m-d H:i:s') : null,
                        'role' => 'student',
                        'xp' => 0,
                    ]);
                    $user = User::findForAuthByEmail($identity['email']);

                    if ($userId > 0 && $user && empty($user['email_verified_at'])) {
                        $verification = new EmailVerificationService($this->db());
                        if ($verification->isEnabled()) {
                            try {
                                $verification->sendForUser(User::findPublicById($userId) ?: []);
                            } catch (\Throwable $mailError) {
                                error_log('Falha ao enviar verificação para conta Google: ' . $mailError->getMessage());
                            }
                        }
                    }
                }
            }

            if (!$user || !Auth::loginUser((int) $user['id'])) {
                throw new \RuntimeException('Não foi possível iniciar a sessão.');
            }

            Session::flash('success', 'Login com Google realizado com sucesso.');
            $this->redirect('/dashboard');
        } catch (\PDOException $e) {
            error_log('Erro de banco no login Google: ' . $e->getMessage());
            Session::flash('error', 'Não foi possível concluir o login com Google.');
            $this->redirect('/login');
        } catch (\Throwable $e) {
            error_log('Erro no login Google: ' . $e->getMessage());
            Session::flash('error', 'Não foi possível concluir o login com Google.');
            $this->redirect('/login');
        }
    }
}
