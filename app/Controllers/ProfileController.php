<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Models\User;
use App\Services\EmailVerificationService;
use App\Services\ProfileService;

final class ProfileController extends Controller
{
    public function index(): void
    {
        $sessionUser = Auth::user();
        if (!$sessionUser) {
            $this->redirect('/login');
        }

        $profile = User::findProfileById((int) $sessionUser['id']);
        if (!$profile) {
            Auth::logout();
            $this->redirect('/login');
        }

        $summary = (new ProfileService($this->db()))
            ->summary((int) $profile['id']);

        $verification = new EmailVerificationService($this->db());

        $this->view('perfil/index', [
            'title' => 'Meu perfil — LD Desenvolvimento Web',
            'profile' => $profile,
            'summary' => $summary,
            'emailVerificationEnabled' => $verification->isEnabled(),
        ]);
    }
}
