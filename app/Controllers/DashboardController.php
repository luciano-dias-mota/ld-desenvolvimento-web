<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Auth;
use App\Models\Course;
use App\Models\Module;
use App\Models\Lesson;
use App\Models\UserLessonProgress;

class DashboardController extends Controller
{
    public function index(): void
    {
        $user = Auth::user();
        if (!$user) {
            header('Location: /login');
            exit;
        }

        // Pega todos os cursos
        $courses = Course::all();

        // Para cada curso, pega os módulos e calcula progresso
        $courseData = [];
        foreach ($courses as $course) {
            $modules = Module::where('course_id', $course['id']);
            $totalLessons = 0;
            $completedLessons = 0;

            foreach ($modules as &$module) {
                // Contar aulas do módulo
                $lessons = Lesson::where('module_id', $module['id']);
                $module['lessons_count'] = count($lessons);
                $module['lessons_completed'] = 0;

                // Progresso do usuário nas aulas deste módulo
                foreach ($lessons as $lesson) {
                    $progress = UserLessonProgress::firstWhereAll([
                        'user_id' => $user['id'],
                        'lesson_id' => $lesson['id']
                    ]);
                    if ($progress && $progress['completed']) {
                        $module['lessons_completed']++;
                        $completedLessons++;
                    }
                    $totalLessons++;
                }
            }
            unset($module); // evita que a referência do último item do foreach vaze para o restante do código

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