<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Session;
use App\Models\Certificate;
use App\Models\Course;
use App\Models\User;

class CertificateController extends Controller
{
    public function show(string $courseSlug): void
    {
        $user = Auth::user();
        if (!$user) {
            $this->redirect('/login');
        }

        $course = Course::firstWhere('slug', $courseSlug);
        if (!$course || ($course['status'] ?? '') !== 'published') {
            $this->notFound();
        }

        if (!$this->isEligibleForCertificate((int) $user['id'], (int) $course['id'])) {
            Session::flash('error', 'Conclua todas as aulas e provas do curso antes de emitir o certificado.');
            $this->redirect('/dashboard?curso=' . rawurlencode($courseSlug));
        }

        $certificate = Certificate::getUserCertificate((int) $user['id'], (int) $course['id']);

        if (!$certificate) {
            $this->db->beginTransaction();

            try {
                $stmt = $this->db->prepare('SELECT id FROM users WHERE id = ? FOR UPDATE');
                $stmt->execute([$user['id']]);

                $certificate = Certificate::getUserCertificate((int) $user['id'], (int) $course['id']);
                if (!$certificate) {
                    Certificate::createCertificate((int) $user['id'], (int) $course['id']);
                    $certificate = Certificate::getUserCertificate((int) $user['id'], (int) $course['id']);
                }

                $this->db->commit();
            } catch (\Throwable $e) {
                if ($this->db->inTransaction()) {
                    $this->db->rollBack();
                }
                throw $e;
            }
        }

        if (!$certificate) {
            throw new \RuntimeException('Não foi possível gerar o certificado.');
        }

        $this->view('certificado/show', compact('course', 'user', 'certificate'));
    }

    public function validar(string $code): void
    {
        $code = trim($code);
        if ($code === '' || strlen($code) > 128) {
            $this->view('certificado/validar', ['certificate' => null]);
            return;
        }

        $stmt = $this->db->prepare(
            'SELECT * FROM certificates WHERE certificate_code = ? LIMIT 1'
        );
        $stmt->execute([$code]);
        $certificate = $stmt->fetch();

        if (!$certificate) {
            $this->view('certificado/validar', ['certificate' => null]);
            return;
        }

        $user = User::find((int) $certificate['user_id']);
        $course = Course::find((int) $certificate['course_id']);

        if (!$user || !$course || !$this->isEligibleForCertificate((int) $user['id'], (int) $course['id'])) {
            $this->view('certificado/validar', ['certificate' => null]);
            return;
        }

        // Compatibilidade com a view antiga, que usava $certificate['code'].
        $certificate['code'] = $certificate['certificate_code'];

        $this->view('certificado/validar', compact('certificate', 'user', 'course'));
    }

    private function isEligibleForCertificate(int $userId, int $courseId): bool
    {
        $stmt = $this->db->prepare(
            'SELECT id FROM modules WHERE course_id = ? ORDER BY module_number ASC, id ASC'
        );
        $stmt->execute([$courseId]);
        $moduleIds = array_map('intval', $stmt->fetchAll(\PDO::FETCH_COLUMN));

        if ($moduleIds === []) {
            return false;
        }

        foreach ($moduleIds as $moduleId) {
            if (!$this->hasCompletedAllLessons($userId, $moduleId)
                || !$this->hasPassedModule($userId, $moduleId)) {
                return false;
            }
        }

        return true;
    }
}
