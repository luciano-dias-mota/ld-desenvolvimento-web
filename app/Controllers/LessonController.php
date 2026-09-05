<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Auth;
use App\Models\Course;
use App\Models\Module;
use App\Models\Lesson;
use App\Models\Exercise;

class LessonController extends Controller
{
    public function show($courseSlug, $moduleSlug, $lessonSlug)
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

        // Buscar módulo
        $module = Module::firstWhereAll([
            'course_id' => $course['id'],
            'slug' => $moduleSlug
        ]);
        if (!$module) {
            return $this->view('errors/404');
        }

        // Verificar se o módulo está desbloqueado para o usuário
        if ($module['status'] === 'locked') {
            return $this->redirect('/dashboard?curso=' . $courseSlug);
        }

        // Buscar aula
        $lesson = Lesson::firstWhereAll([
            'module_id' => $module['id'],
            'slug' => $lessonSlug
        ]);
        if (!$lesson) {
            return $this->view('errors/404');
        }

        // Verificar progresso
        $progress = \App\Models\UserLessonProgress::firstWhereAll([
            'user_id' => $user['id'],
            'lesson_id' => $lesson['id']
        ]);
        $completed = $progress && $progress['completed'];

        // Buscar exercício associado (se houver)
        $exercise = Exercise::firstWhere('lesson_id', $lesson['id']);

        // Buscar próxima aula
        $next = Lesson::findNextLesson($module['id'], $lesson['lesson_number']);

        $this->view('aulas/show', compact('course', 'module', 'lesson', 'completed', 'exercise', 'next'));
    }

    public function complete($courseSlug, $moduleSlug, $lessonSlug)
    {
        $user = Auth::user();
        if (!$user) {
            return $this->json(['error' => 'Não autorizado'], 401);
        }

        // Verificar CSRF
        if (!$this->validateCsrf()) {
            return $this->json(['error' => 'Token inválido'], 403);
        }

        // Buscar aula
        $course = Course::firstWhere('slug', $courseSlug);
        if (!$course) {
            return $this->json(['error' => 'Curso não encontrado'], 404);
        }

        $module = Module::firstWhereAll([
            'course_id' => $course['id'],
            'slug' => $moduleSlug
        ]);
        if (!$module) {
            return $this->json(['error' => 'Módulo não encontrado'], 404);
        }

        // A mesma checagem de show() estava faltando aqui: sem ela, um POST
        // direto pra rota de conclusão marcava a aula como concluída (e dava XP)
        // mesmo com o módulo bloqueado.
        if ($module['status'] === 'locked') {
            return $this->json(['error' => 'Módulo bloqueado'], 403);
        }

        $lesson = Lesson::firstWhereAll([
            'module_id' => $module['id'],
            'slug' => $lessonSlug
        ]);
        if (!$lesson) {
            return $this->json(['error' => 'Aula não encontrada'], 404);
        }

        // Marcar como concluída
        $progress = \App\Models\UserLessonProgress::firstWhereAll([
            'user_id' => $user['id'],
            'lesson_id' => $lesson['id']
        ]);

        if ($progress) {
            $this->db->prepare("UPDATE user_lesson_progress SET completed = 1, completed_at = NOW(), xp_earned = ? WHERE id = ?")
                ->execute([$lesson['xp_reward'], $progress['id']]);
        } else {
            $this->db->prepare("INSERT INTO user_lesson_progress (user_id, lesson_id, completed, completed_at, xp_earned) VALUES (?, ?, 1, NOW(), ?)")
                ->execute([$user['id'], $lesson['id'], $lesson['xp_reward']]);
        }

        // Atualizar XP do usuário
        $this->db->prepare("UPDATE users SET xp = xp + ? WHERE id = ?")
            ->execute([$lesson['xp_reward'], $user['id']]);

        return $this->redirect('/aulas/' . $courseSlug . '/' . $moduleSlug . '/' . $lessonSlug);
    }
}