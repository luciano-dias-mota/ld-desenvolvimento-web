<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Auth;
use App\Core\Session;
use App\Models\Course;
use App\Models\Module;
use App\Models\Lesson;
use App\Models\UserLessonProgress;

class CourseController extends Controller
{
    public function showModule(string $courseSlug, string $moduleSlug): void
    {
        $user = Auth::user();
        if (!$user) {
            // Defesa extra além do middleware 'auth' já aplicado na rota
            $this->redirect('/login');
            return;
        }

        $course = Course::firstWhere('slug', $courseSlug);
        if (!$course) {
            $this->redirect('/dashboard');
            return;
        }

        // Busca o módulo já filtrando pelo curso, evitando pegar um módulo
        // de outro curso quando dois cursos têm módulos com o mesmo slug
        $module = Module::firstWhereAll([
            'course_id' => $course['id'],
            'slug' => $moduleSlug
        ]);
        if (!$module) {
            $this->redirect('/dashboard');
            return;
        }

        // Verifica se o módulo está desbloqueado para o usuário
        // Se status for 'locked', redireciona
        if ($module['status'] === 'locked') {
            Session::flash('error', 'Este módulo está bloqueado. Complete o módulo anterior primeiro.');
            $this->redirect('/dashboard');
            return;
        }

        // Busca as aulas do módulo
        $lessons = Lesson::where('module_id', $module['id']);

        // Para cada aula, verifica se o usuário concluiu
        // (uma consulta indexada por user_id + lesson_id em vez de buscar
        // todo o histórico do usuário e filtrar em PHP)
        foreach ($lessons as &$lesson) {
            $progress = UserLessonProgress::firstWhereAll([
                'user_id' => $user['id'],
                'lesson_id' => $lesson['id']
            ]);
            $lesson['completed'] = $progress !== null && (bool) $progress['completed'];
        }
        unset($lesson); // evita que a referência do último item do foreach vaze para o restante do código

        $this->view('cursos/modulo', [
            'course' => $course,
            'module' => $module,
            'lessons' => $lessons,
            'user' => $user
        ]);
    }
}