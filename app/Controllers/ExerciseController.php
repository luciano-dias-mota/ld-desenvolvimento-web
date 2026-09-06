<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Session;
use App\Models\Course;
use App\Models\Exercise;
use App\Models\Lesson;
use App\Models\Module;

class ExerciseController extends Controller
{
    public function show(string $courseSlug, string $moduleSlug, string $lessonSlug): void
    {
        $user = Auth::user();
        if (!$user) {
            $this->redirect('/login');
        }

        [$course, $module, $lesson, $exercise] = $this->resolveExercise($courseSlug, $moduleSlug, $lessonSlug);

        if (!$this->canAccessModule((int) $user['id'], $module)) {
            Session::flash('error', 'Este módulo ainda está bloqueado.');
            $this->redirect('/dashboard?curso=' . rawurlencode($courseSlug));
        }

        $stmt = $this->db->prepare(
            'SELECT answer, is_correct, xp_earned
             FROM user_exercise_submissions
             WHERE user_id = ? AND exercise_id = ?
             ORDER BY id DESC
             LIMIT 1'
        );
        $stmt->execute([$user['id'], $exercise['id']]);
        $submission = $stmt->fetch();

        $answer = $submission['answer'] ?? null;
        $isCorrect = array_key_exists('is_correct', $submission ?: [])
            ? (bool) $submission['is_correct']
            : null;

        // A view antiga exibe exercise.xp_reward no resultado. Ajustamos para mostrar
        // o XP efetivamente recebido nesta tentativa, evitando informar recompensa duplicada.
        if ($submission !== false && $submission !== null) {
            $exercise['xp_reward'] = (int) ($submission['xp_earned'] ?? 0);
        }

        $options = json_decode((string) ($exercise['options'] ?? ''), true);
        if (!is_array($options)) {
            $options = [];
        }

        $this->view(
            'exercicios/show',
            compact('course', 'module', 'lesson', 'exercise', 'options', 'answer', 'isCorrect')
        );
    }

    public function submit(string $courseSlug, string $moduleSlug, string $lessonSlug): void
    {
        $user = Auth::user();
        if (!$user) {
            $this->redirect('/login');
        }

        if (!$this->validateCsrf()) {
            Session::flash('error', 'Sessão expirada. Recarregue a página e tente novamente.');
            $this->redirect('/exercicios/' . rawurlencode($courseSlug) . '/' . rawurlencode($moduleSlug) . '/' . rawurlencode($lessonSlug));
        }

        [$course, $module, $lesson, $exercise] = $this->resolveExercise($courseSlug, $moduleSlug, $lessonSlug);

        if (!$this->canAccessModule((int) $user['id'], $module)) {
            Session::flash('error', 'Este módulo ainda está bloqueado.');
            $this->redirect('/dashboard?curso=' . rawurlencode($courseSlug));
        }

        $answer = trim((string) ($_POST['resposta'] ?? ''));
        if ($answer === '' || strlen($answer) > 10000) {
            Session::flash('error', 'Envie uma resposta válida.');
            $this->redirect('/exercicios/' . rawurlencode($courseSlug) . '/' . rawurlencode($moduleSlug) . '/' . rawurlencode($lessonSlug));
        }

        $correctAnswer = trim((string) ($exercise['correct_answer'] ?? ''));
        $type = (string) ($exercise['exercise_type'] ?? '');

        if (in_array($type, ['multiple_choice', 'true_false'], true)) {
            $isCorrect = $answer === $correctAnswer;
        } else {
            // Para exercícios textuais/código, mantém comparação determinística exata,
            // normalizando apenas espaços externos e finais de linha.
            $normalizedAnswer = str_replace(["\r\n", "\r"], "\n", $answer);
            $normalizedCorrect = str_replace(["\r\n", "\r"], "\n", $correctAnswer);
            $isCorrect = $normalizedAnswer === $normalizedCorrect;
        }

        $this->db->beginTransaction();

        try {
            $stmt = $this->db->prepare('SELECT id FROM users WHERE id = ? FOR UPDATE');
            $stmt->execute([$user['id']]);

            $stmt = $this->db->prepare(
                'SELECT 1
                 FROM user_exercise_submissions
                 WHERE user_id = ? AND exercise_id = ? AND is_correct = 1
                 LIMIT 1'
            );
            $stmt->execute([$user['id'], $exercise['id']]);
            $alreadyRewarded = (bool) $stmt->fetchColumn();

            $xpReward = max(0, (int) ($exercise['xp_reward'] ?? 0));
            $xpEarned = ($isCorrect && !$alreadyRewarded) ? $xpReward : 0;

            $stmt = $this->db->prepare(
                'INSERT INTO user_exercise_submissions
                    (user_id, exercise_id, answer, is_correct, xp_earned, submitted_at)
                 VALUES (?, ?, ?, ?, ?, NOW())'
            );
            $stmt->execute([
                $user['id'],
                $exercise['id'],
                $answer,
                (int) $isCorrect,
                $xpEarned,
            ]);

            if ($isCorrect) {
                if ($xpEarned > 0) {
                    $stmt = $this->db->prepare('UPDATE users SET xp = xp + ? WHERE id = ?');
                    $stmt->execute([$xpEarned, $user['id']]);
                }

                $stmt = $this->db->prepare(
                    'SELECT id, completed
                     FROM user_lesson_progress
                     WHERE user_id = ? AND lesson_id = ?
                     LIMIT 1
                     FOR UPDATE'
                );
                $stmt->execute([$user['id'], $lesson['id']]);
                $progress = $stmt->fetch();

                if ($progress) {
                    if (!(bool) $progress['completed']) {
                        $stmt = $this->db->prepare(
                            'UPDATE user_lesson_progress
                             SET completed = 1, completed_at = NOW()
                             WHERE id = ?'
                        );
                        $stmt->execute([$progress['id']]);
                    }
                } else {
                    $stmt = $this->db->prepare(
                        'INSERT INTO user_lesson_progress
                            (user_id, lesson_id, completed, completed_at, xp_earned)
                         VALUES (?, ?, 1, NOW(), ?)'
                    );
                    $stmt->execute([$user['id'], $lesson['id'], $xpEarned]);
                }
            }

            $this->db->commit();
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }

        $this->redirect('/exercicios/' . rawurlencode($courseSlug) . '/' . rawurlencode($moduleSlug) . '/' . rawurlencode($lessonSlug));
    }

    private function resolveExercise(string $courseSlug, string $moduleSlug, string $lessonSlug): array
    {
        $course = Course::firstWhere('slug', $courseSlug);
        if (!$course || ($course['status'] ?? '') !== 'published') {
            $this->notFound();
        }

        $module = Module::firstWhereAll([
            'course_id' => $course['id'],
            'slug' => $moduleSlug,
        ]);
        if (!$module) {
            $this->notFound();
        }

        $lesson = Lesson::firstWhereAll([
            'module_id' => $module['id'],
            'slug' => $lessonSlug,
        ]);
        if (!$lesson || ($lesson['status'] ?? '') !== 'published') {
            $this->notFound();
        }

        $exercise = Exercise::firstWhere('lesson_id', $lesson['id']);
        if (!$exercise || ($exercise['status'] ?? '') !== 'published') {
            $this->redirect('/aulas/' . rawurlencode($courseSlug) . '/' . rawurlencode($moduleSlug) . '/' . rawurlencode($lessonSlug));
        }

        return [$course, $module, $lesson, $exercise];
    }
}
