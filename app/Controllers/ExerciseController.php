<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Session;
use App\Models\Course;
use App\Models\Exercise;
use App\Models\Lesson;
use App\Models\Module;

class ExerciseController extends LearningController
{
    public function show(string $courseSlug, string $moduleSlug, string $lessonSlug): void
    {
        $user = Auth::user();
        if (!$user) {
            $this->redirect('/login');
        }

        [$course, $module, $lesson, $exercise] = $this->resolveExercise($courseSlug, $moduleSlug, $lessonSlug, false);

        if (!$this->canAccessModule((int) $user['id'], $module)) {
            Session::flash('error', 'Este módulo ainda está bloqueado.');
            $this->redirect('/dashboard#curso-' . rawurlencode($courseSlug));
        }

        $stmt = $this->db()->prepare(
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

        $result = Session::flash('exercise_result');
        if (!is_array($result) || (int) ($result['exercise_id'] ?? 0) !== (int) $exercise['id']) {
            $result = null;
        }

        $options = json_decode((string) ($exercise['options'] ?? ''), true);
        if (!is_array($options)) {
            $options = [];
        }

        $this->view(
            'exercicios/show',
            compact('course', 'module', 'lesson', 'exercise', 'options', 'answer', 'isCorrect', 'result', 'submission')
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

        [$course, $module, $lesson, $exercise] = $this->resolveExercise($courseSlug, $moduleSlug, $lessonSlug, true);

        if (!$this->canAccessModule((int) $user['id'], $module)) {
            Session::flash('error', 'Este módulo ainda está bloqueado.');
            $this->redirect('/dashboard#curso-' . rawurlencode($courseSlug));
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
            $normalizedAnswer = str_replace(["\r\n", "\r"], "\n", $answer);
            $normalizedCorrect = str_replace(["\r\n", "\r"], "\n", $correctAnswer);
            $isCorrect = $normalizedAnswer === $normalizedCorrect;
        }

        $exerciseXpEarned = 0;
        $lessonXpEarned = 0;

        $this->db()->beginTransaction();

        try {
            // Serializa os ganhos de XP do usuário e evita premiações duplicadas.
            $stmt = $this->db()->prepare('SELECT id FROM users WHERE id = ? FOR UPDATE');
            $stmt->execute([$user['id']]);
            if (!$stmt->fetchColumn()) {
                throw new \RuntimeException('Usuário não encontrado.');
            }

            $stmt = $this->db()->prepare(
                'SELECT 1
                 FROM user_exercise_submissions
                 WHERE user_id = ? AND exercise_id = ? AND is_correct = 1
                 LIMIT 1'
            );
            $stmt->execute([$user['id'], $exercise['id']]);
            $alreadyRewardedExercise = (bool) $stmt->fetchColumn();

            $exerciseXpReward = max(0, (int) ($exercise['xp_reward'] ?? 0));
            $exerciseXpEarned = ($isCorrect && !$alreadyRewardedExercise) ? $exerciseXpReward : 0;

            $stmt = $this->db()->prepare(
                'INSERT INTO user_exercise_submissions
                    (user_id, exercise_id, answer, is_correct, xp_earned, submitted_at)
                 VALUES (?, ?, ?, ?, ?, NOW())'
            );
            $stmt->execute([
                $user['id'],
                $exercise['id'],
                $answer,
                (int) $isCorrect,
                $exerciseXpEarned,
            ]);

            if ($isCorrect) {
                $stmt = $this->db()->prepare(
                    'SELECT id, completed
                     FROM user_lesson_progress
                     WHERE user_id = ? AND lesson_id = ?
                     LIMIT 1
                     FOR UPDATE'
                );
                $stmt->execute([$user['id'], $lesson['id']]);
                $progress = $stmt->fetch();

                if (!$progress || !(bool) $progress['completed']) {
                    $lessonXpEarned = max(0, (int) ($lesson['xp_reward'] ?? 0));

                    if ($progress) {
                        $stmt = $this->db()->prepare(
                            'UPDATE user_lesson_progress
                             SET completed = 1, completed_at = NOW(), xp_earned = ?
                             WHERE id = ?'
                        );
                        $stmt->execute([$lessonXpEarned, $progress['id']]);
                    } else {
                        $stmt = $this->db()->prepare(
                            'INSERT INTO user_lesson_progress
                                (user_id, lesson_id, completed, completed_at, xp_earned)
                             VALUES (?, ?, 1, NOW(), ?)'
                        );
                        $stmt->execute([$user['id'], $lesson['id'], $lessonXpEarned]);
                    }
                }

                $totalXpEarned = $exerciseXpEarned + $lessonXpEarned;
                if ($totalXpEarned > 0) {
                    $stmt = $this->db()->prepare('UPDATE users SET xp = xp + ? WHERE id = ?');
                    $stmt->execute([$totalXpEarned, $user['id']]);
                }
            }

            $this->db()->commit();
        } catch (\Throwable $e) {
            if ($this->db()->inTransaction()) {
                $this->db()->rollBack();
            }
            throw $e;
        }

        Session::flash('exercise_result', [
            'exercise_id' => (int) $exercise['id'],
            'correct' => $isCorrect,
            'exercise_xp' => $exerciseXpEarned,
            'lesson_xp' => $lessonXpEarned,
            'total_xp' => $exerciseXpEarned + $lessonXpEarned,
        ]);

        $this->redirect('/exercicios/' . rawurlencode($courseSlug) . '/' . rawurlencode($moduleSlug) . '/' . rawurlencode($lessonSlug));
    }

    private function resolveExercise(string $courseSlug, string $moduleSlug, string $lessonSlug, bool $includeAnswer): array
    {
        $course = Course::firstWhere('slug', $courseSlug);
        if (!$course || ($course['status'] ?? '') !== 'published') {
            $this->notFound();
        }

        $module = Module::firstWhereAll([
            'course_id' => $course['id'],
            'slug' => $moduleSlug,
            'status' => 'published',
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

        $columns = $includeAnswer
            ? 'id, lesson_id, title, exercise_type, question, options, correct_answer, xp_reward, exercise_number, status'
            : 'id, lesson_id, title, exercise_type, question, options, xp_reward, exercise_number, status';

        $stmt = $this->db()->prepare(
            "SELECT {$columns}
             FROM exercises
             WHERE lesson_id = ? AND status = 'published'
             ORDER BY exercise_number ASC, id ASC
             LIMIT 1"
        );
        $stmt->execute([$lesson['id']]);
        $exercise = $stmt->fetch() ?: null;

        if (!$exercise) {
            $this->redirect('/aulas/' . rawurlencode($courseSlug) . '/' . rawurlencode($moduleSlug) . '/' . rawurlencode($lessonSlug));
        }

        return [$course, $module, $lesson, $exercise];
    }
}
