<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Session;
use App\Models\Certificate;
use App\Models\Course;
use App\Models\User;

class CertificateController extends LearningController
{
    public function show(string $courseSlug): void
    {
        $user = Auth::user();
        if (!$user) {
            $this->redirect('/login');
        }

        $course = Course::firstWhere('slug', $courseSlug);
        if (!$course) {
            $this->notFound();
        }

        // GET é somente leitura. Emissão acontece em POST (prova ou /emitir).
        $certificate = Certificate::getUserCertificate((int) $user['id'], (int) $course['id']);
        if (!$certificate) {
            Session::flash('error', 'Este certificado ainda não foi emitido.');
            $this->redirect('/dashboard#curso-' . rawurlencode($courseSlug));
        }

        $this->view('certificado/show', compact('course', 'user', 'certificate'));
    }

    public function issue(string $courseSlug): void
    {
        $user = Auth::user();
        if (!$user) {
            $this->redirect('/login');
        }

        if (!$this->validateCsrf()) {
            Session::flash('error', 'Sessão expirada. Recarregue a página e tente novamente.');
            $this->redirect('/dashboard#curso-' . rawurlencode($courseSlug));
        }

        $course = Course::firstWhere('slug', $courseSlug);
        if (!$course || ($course['status'] ?? '') !== 'published') {
            $this->notFound();
        }

        $existing = Certificate::getUserCertificate((int) $user['id'], (int) $course['id']);
        if ($existing) {
            $this->redirect('/certificado/' . rawurlencode($courseSlug));
        }

        if (!$this->progress()->isCourseComplete((int) $user['id'], (int) $course['id'])) {
            Session::flash('error', 'Conclua todas as aulas e provas do curso antes de emitir o certificado.');
            $this->redirect('/dashboard#curso-' . rawurlencode($courseSlug));
        }

        if (!$this->canIssueCertificateFor($user)) {
            Session::flash('error', 'Confirme seu e-mail antes de emitir o certificado.');
            $this->redirect('/dashboard#curso-' . rawurlencode($courseSlug));
        }

        Certificate::createCertificate((int) $user['id'], (int) $course['id']);
        Session::flash('success', 'Certificado emitido com sucesso.');
        $this->redirect('/certificado/' . rawurlencode($courseSlug));
    }

    public function validar(string $code): void
    {
        $code = trim($code);
        if ($code === '' || strlen($code) > 128) {
            $this->view('certificado/validar', ['certificate' => null]);
            return;
        }

        $stmt = $this->db()->prepare(
            'SELECT id, user_id, course_id, certificate_code, issued_at
             FROM certificates
             WHERE certificate_code = ?
             LIMIT 1'
        );
        $stmt->execute([$code]);
        $certificate = $stmt->fetch();

        if (!$certificate) {
            $this->view('certificado/validar', ['certificate' => null]);
            return;
        }

        $user = User::findPublicById((int) $certificate['user_id']);
        $course = Course::find((int) $certificate['course_id']);

        // Certificado emitido é fato histórico; não revalida progresso atual.
        if (!$user || !$course) {
            $this->view('certificado/validar', ['certificate' => null]);
            return;
        }

        $this->view('certificado/validar', compact('certificate', 'user', 'course'));
    }
}
