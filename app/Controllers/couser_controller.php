<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Auth;
use App\Models\Course;
use App\Models\Module;
use App\Models\Lesson;
use App\Models\UserLessonProgress;

class CourseController extends Controller
{
    public function showModule(string $courseSlug, string $moduleSlug): void
    {
        $user = Auth::user();
        $course = Course::firstWhere('slug', $courseSlug);
        if (!$course) {
            $this->redirect('/dashboard');
        }

        $module = Module::firstWhere('slug', $moduleSlug);
        if (!$module || $module['course_id'] != $course['id']) {
            $this->redirect('/dashboard');
        }

        // Verifica se o módulo está desbloqueado para o usuário
        // Se status for 'locked', redireciona
        if ($module['status'] === 'locked') {
            Session::flash('error', 'Este módulo está bloqueado. Complete o módulo anterior primeiro.');
            $this->redirect('/dashboard');
        }

        // Busca as aulas do módulo
        $lessons = Lesson::where('module_id', $module['id']);

        // Para cada aula, verifica se o usuário concluiu
        foreach ($lessons as &$lesson) {
            $progress = UserLessonProgress::firstWhere('user_id', $user['id']);
            // Precisa filtrar por lesson_id também
            $progress = UserLessonProgress::where('user_id', $user['id']);
            $progress = array_filter($progress, function($p) use ($lesson) {
                return $p['lesson_id'] == $lesson['id'];
            });
            $lesson['completed'] = !empty($progress) && $progress[0]['completed'];
        }

        $this->view('cursos/modulo', [
            'course' => $course,
            'module' => $module,
            'lessons' => $lessons,
            'user' => $user
        ]);
    }
}