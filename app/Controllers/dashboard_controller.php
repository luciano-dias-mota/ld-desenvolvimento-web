<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Auth;
use App\Models\Course;
use App\Models\Module;
use App\Models\UserLessonProgress;
use App\Models\UserModuleTest;

class DashboardController extends Controller
{
    public function index(): void
    {
        $user = Auth::user();
        if (!$user) {
            header('Location: /login');
            exit;
        }

        // Pega todos os cursos (apenas um por enquanto)
        $courses = Course::all();

        // Para cada curso, pega os módulos e calcula progresso
        $courseData = [];
        foreach ($courses as $course) {
            $modules = Module::where('course_id', $course['id']);
            $totalLessons = 0;
            $completedLessons = 0;

            foreach ($modules as &$module) {
                // Contar aulas do módulo (usando Lesson model)
                $lessons = \App\Models\Lesson::where('module_id', $module['id']);
                $module['lessons_count'] = count($lessons);
                $module['lessons_completed'] = 0;

                // Progresso do usuário nas aulas deste módulo
                foreach ($lessons as $lesson) {
                    $progress = UserLessonProgress::firstWhere('user_id', $user['id']);
                    // Na verdade precisa do lesson_id também
                    // Vamos fazer uma consulta mais direta depois
                    // Simplificando: contar via model
                }

                // Para simplificar, vamos calcular depois
            }

            $courseData[] = [
                'course' => $course,
                'modules' => $modules
            ];
        }

        $this->view('dashboard/index', [
            'courses' => $courseData,
            'user' => $user
        ]);
    }
}