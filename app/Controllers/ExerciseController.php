<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Auth;
use App\Models\Course;
use App\Models\Module;
use App\Models\Lesson;
use App\Models\Exercise;

class ExerciseController extends Controller
{
    public function show($courseSlug, $moduleSlug, $lessonSlug)
    {
        $user = Auth::user();
        if (!$user) {
            return $this->redirect('/login');
        }

        // Buscar curso, módulo, aula, exercício
        $course = Course::firstWhere('slug', $courseSlug);
        if (!$course) {
            return $this->view('errors/404');
        }

        $module = Module::firstWhereAll([
            'course_id' => $course['id'],
            'slug' => $moduleSlug
        ]);
        if (!$module) {
            return $this->view('errors/404');
        }

        // Módulo bloqueado não deve ser acessível diretamente pela URL
        if ($module['status'] === 'locked') {
            return $this->redirect('/dashboard?curso=' . $courseSlug);
        }

        $lesson = Lesson::firstWhereAll([
            'module_id' => $module['id'],
            'slug' => $lessonSlug
        ]);
        if (!$lesson) {
            return $this->view('errors/404');
        }

        $exercise = Exercise::firstWhere('lesson_id', $lesson['id']);
        if (!$exercise) {
            return $this->redirect('/aulas/' . $courseSlug . '/' . $moduleSlug . '/' . $lessonSlug);
        }

        // Verificar submissão anterior
        $stmt = $this->db->prepare("SELECT answer, is_correct FROM user_exercise_submissions WHERE user_id = ? AND exercise_id = ? ORDER BY id DESC LIMIT 1");
        $stmt->execute([$user['id'], $exercise['id']]);
        $submission = $stmt->fetch(\PDO::FETCH_ASSOC);

        $answer = $submission['answer'] ?? null;
        $isCorrect = $submission['is_correct'] ?? null;

        // Para múltipla escolha, decodificar options
        $options = json_decode($exercise['options'], true);
        if (!is_array($options)) {
            $options = [];
        }

        $this->view('exercicios/show', compact('course', 'module', 'lesson', 'exercise', 'options', 'answer', 'isCorrect'));
    }

    public function submit($courseSlug, $moduleSlug, $lessonSlug)
    {
        $user = Auth::user();
        if (!$user) {
            return $this->redirect('/login');
        }

        if (!$this->validateCsrf()) {
            return $this->json(['error' => 'Token inválido'], 403);
        }

        // Buscar dados
        $course = Course::firstWhere('slug', $courseSlug);
        if (!$course) {
            return $this->view('errors/404');
        }

        $module = Module::firstWhereAll([
            'course_id' => $course['id'],
            'slug' => $moduleSlug
        ]);
        if (!$module) {
            return $this->view('errors/404');
        }

        // Módulo bloqueado não deve aceitar submissões via POST direto
        if ($module['status'] === 'locked') {
            return $this->redirect('/dashboard?curso=' . $courseSlug);
        }

        $lesson = Lesson::firstWhereAll([
            'module_id' => $module['id'],
            'slug' => $lessonSlug
        ]);
        if (!$lesson) {
            return $this->view('errors/404');
        }

        $exercise = Exercise::firstWhere('lesson_id', $lesson['id']);
        if (!$exercise) {
            return $this->redirect('/aulas/' . $courseSlug . '/' . $moduleSlug . '/' . $lessonSlug);
        }

        // Processar resposta
        $answer = $_POST['resposta'] ?? '';

        // Removido o if/else redundante que tinha a mesma lógica nos dois ramos
        // (multiple_choice/true_false vs. demais tipos). Se no futuro cada tipo
        // de exercício precisar de uma comparação diferente, é aqui que entra.
        $correct = $exercise['correct_answer'];
        $isCorrect = (trim($answer) == trim($correct));

        // Registrar submissão
        $xpEarned = $isCorrect ? $exercise['xp_reward'] : 0;
        $stmt = $this->db->prepare("INSERT INTO user_exercise_submissions (user_id, exercise_id, answer, is_correct, xp_earned, submitted_at) VALUES (?, ?, ?, ?, ?, NOW())");
        $stmt->execute([$user['id'], $exercise['id'], $answer, (int) $isCorrect, $xpEarned]);

        // Atualizar XP do usuário se correto
        if ($isCorrect) {
            $this->db->prepare("UPDATE users SET xp = xp + ? WHERE id = ?")
                ->execute([$xpEarned, $user['id']]);

            // Marcar aula como concluída automaticamente
            $progress = \App\Models\UserLessonProgress::firstWhereAll([
                'user_id' => $user['id'],
                'lesson_id' => $lesson['id']
            ]);
            if ($progress) {
                $this->db->prepare("UPDATE user_lesson_progress SET completed = 1, completed_at = NOW() WHERE id = ?")
                    ->execute([$progress['id']]);
            } else {
                $this->db->prepare("INSERT INTO user_lesson_progress (user_id, lesson_id, completed, completed_at, xp_earned) VALUES (?, ?, 1, NOW(), ?)")
                    ->execute([$user['id'], $lesson['id'], $xpEarned]);
            }
        }

        // Redirecionar com mensagem
        return $this->redirect('/exercicios/' . $courseSlug . '/' . $moduleSlug . '/' . $lessonSlug . '?result=' . ($isCorrect ? 'correct' : 'wrong'));
    }
}