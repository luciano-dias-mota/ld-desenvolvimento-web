<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Auth;
use App\Models\Course;
use App\Models\Module;
use App\Models\Certificate;

class CertificateController extends Controller
{
    public function show($courseSlug)
    {
        $user = Auth::user();
        if (!$user) {
            return $this->redirect('/login');
        }

        // Buscar curso
        $course = Course::firstWhere('slug', $courseSlug);
        if (!$course) {
            return $this->view('errors/404');
        }

        // Verificar se o usuário tem certificado
        $certificate = Certificate::getUserCertificate($user['id'], $course['id']);
        if (!$certificate) {
            // Verificar se completou todos os módulos
            $modules = Module::where('course_id', $course['id']);
            $allCompleted = true;
            foreach ($modules as $mod) {
                // Verificar se o usuário passou na prova do módulo
                $stmt = $this->db->prepare("SELECT COUNT(*) FROM user_module_tests WHERE user_id = ? AND module_test_id = (SELECT id FROM module_tests WHERE module_id = ?) AND passed = 1");
                $stmt->execute([$user['id'], $mod['id']]);
                $passedTest = $stmt->fetchColumn() > 0;
                if (!$passedTest) {
                    $allCompleted = false;
                    break;
                }
            }

            if ($allCompleted) {
                // Gerar certificado
                Certificate::createCertificate($user['id'], $course['id']);
                $certificate = Certificate::getUserCertificate($user['id'], $course['id']);
            } else {
                // Não completou todos os módulos
                return $this->redirect('/dashboard?curso=' . $courseSlug);
            }
        }

        // Exibir certificado
        $this->view('certificado/show', compact('course', 'user', 'certificate'));
    }

    public function validar($code)
    {
        $stmt = $this->db->prepare("SELECT * FROM certificates WHERE certificate_code = ?");
        $stmt->execute([$code]);
        $certificate = $stmt->fetch(\PDO::FETCH_ASSOC);
        if ($certificate) {
            // Buscar dados do usuário e curso
            $user = \App\Models\User::find($certificate['user_id']);
            $course = Course::find($certificate['course_id']);
            $this->view('certificado/validar', compact('certificate', 'user', 'course'));
        } else {
            $this->view('certificado/validar', ['certificate' => null]);
        }
    }
}