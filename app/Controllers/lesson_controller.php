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
        $courseModel = new Course();
        $course = $courseModel->where('slug', $courseSlug)->first();
        if (!$course) {
            return $this->view('errors/404');
        }

        // Buscar módulo
        $moduleModel = new Module();
        $module = $moduleModel->where('course_id', $course->id)->where('slug', $moduleSlug)->first();
        if (!$module) {
            return $this->view('errors/404');
        }

        // Verificar se o módulo está desbloqueado para o usuário
        if (!$module->isUnlockedForUser($user->id) && $module->status != 'active') {
            // Se não estiver desbloqueado e não for o primeiro módulo ativo, redirecionar
            $this->redirect('/dashboard?curso=' . $courseSlug);
        }

        // Buscar aula
        $lessonModel = new Lesson();
        $lesson = $lessonModel->where('module_id', $module->id)->where('slug', $lessonSlug)->first();
        if (!$lesson) {
            return $this->view('errors/404');
        }

        // Verificar progresso
        $completed = $lesson->isCompletedByUser($user->id);

        // Buscar exercício associado (se houver)
        $exerciseModel = new Exercise();
        $exercise = $exerciseModel->where('lesson_id', $lesson->id)->first();

        // Buscar próxima aula
        $next = $lessonModel->where('module_id', $module->id)
                           ->where('lesson_number', '>', $lesson->lesson_number)
                           ->orderBy('lesson_number', 'ASC')
                           ->first();

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
        $courseModel = new Course();
        $course = $courseModel->where('slug', $courseSlug)->first();
        if (!$course) {
            return $this->json(['error' => 'Curso não encontrado'], 404);
        }

        $moduleModel = new Module();
        $module = $moduleModel->where('course_id', $course->id)->where('slug', $moduleSlug)->first();
        if (!$module) {
            return $this->json(['error' => 'Módulo não encontrado'], 404);
        }

        $lessonModel = new Lesson();
        $lesson = $lessonModel->where('module_id', $module->id)->where('slug', $lessonSlug)->first();
        if (!$lesson) {
            return $this->json(['error' => 'Aula não encontrada'], 404);
        }

        // Marcar como concluída
        $lesson->completeForUser($user->id);

        // Atualizar XP (a aula já tem xp_reward, mas podemos adicionar)
        $xp = $lesson->xp_reward;
        $db = $this->db;
        $stmt = $db->prepare("UPDATE users SET xp = xp + ? WHERE id = ?");
        $stmt->execute([$xp, $user->id]);

        // Atualizar progresso da aula com o XP ganho
        $stmt = $db->prepare("UPDATE user_lesson_progress SET xp_earned = ? WHERE user_id = ? AND lesson_id = ?");
        $stmt->execute([$xp, $user->id, $lesson->id]);

        return $this->redirect('/aulas/' . $courseSlug . '/' . $moduleSlug . '/' . $lessonSlug);
    }
}