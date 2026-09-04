<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Auth;
use App\Models\Course;
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
        $courseModel = new Course();
        $course = $courseModel->where('slug', $courseSlug)->first();
        if (!$course) {
            return $this->view('errors/404');
        }

        // Verificar se o usuário tem certificado
        $certificate = Certificate::getUserCertificate($user->id, $course->id);
        if (!$certificate) {
            // Verificar se completou todos os módulos
            $moduleModel = new \App\Models\Module();
            $modules = $moduleModel->where('course_id', $course->id)->get();
            $allCompleted = true;
            foreach ($modules as $mod) {
                if (!$mod->isCompletedByUser($user->id) && $mod->status != 'completed') {
                    $allCompleted = false;
                    break;
                }
            }
            if ($allCompleted) {
                // Gerar certificado
                Certificate::createCertificate($user->id, $course->id);
                $certificate = Certificate::getUserCertificate($user->id, $course->id);
            } else {
                // Não completou todos os módulos
                $this->redirect('/dashboard?curso=' . $courseSlug);
            }
        }

        // Exibir certificado
        $this->view('certificado/show', compact('course', 'user', 'certificate'));
    }

    // Método para validar certificado via código (se tiver rota)
    public function validar($code)
    {
        $db = $this->db;
        $stmt = $db->prepare("SELECT * FROM certificates WHERE certificate_code = ?");
        $stmt->execute([$code]);
        $certificate = $stmt->fetch(\PDO::FETCH_ASSOC);
        if ($certificate) {
            // Buscar dados do usuário e curso
            $userModel = new \App\Models\User();
            $user = $userModel->find($certificate['user_id']);
            $courseModel = new Course();
            $course = $courseModel->find($certificate['course_id']);
            $this->view('certificado/validar', compact('certificate', 'user', 'course'));
        } else {
            $this->view('certificado/validar', ['certificate' => null]);
        }
    }
}