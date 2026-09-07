<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Session;
use App\Models\Course;
use App\Models\Exercise;
use App\Models\Lesson;
use App\Models\Module;
use App\Models\UserLessonProgress;

class LessonController extends LearningController
{
    public function show(string $courseSlug, string $moduleSlug, string $lessonSlug): void
    {
        $user = Auth::user();
        if (!$user) {
            $this->redirect('/login');
        }

        [$course, $module, $lesson] = $this->resolveLesson($courseSlug, $moduleSlug, $lessonSlug);

        if (!$this->canAccessModule((int) $user['id'], $module)) {
            Session::flash('error', 'Este módulo ainda está bloqueado.');
            $this->redirect('/dashboard#curso-' . rawurlencode($courseSlug));
        }

        $progress = UserLessonProgress::firstWhereAll([
            'user_id' => $user['id'],
            'lesson_id' => $lesson['id'],
        ]);
        $completed = $progress !== null && (bool) $progress['completed'];

        $exercise = Exercise::firstWhereAll([
            'lesson_id' => $lesson['id'],
            'status' => 'published',
        ]);

        $stmt = $this->db()->prepare(
            "SELECT *
             FROM lessons
             WHERE module_id = ?
               AND status = 'published'
               AND lesson_number > ?
             ORDER BY lesson_number ASC, id ASC
             LIMIT 1"
        );
        $stmt->execute([$module['id'], $lesson['lesson_number']]);
        $next = $stmt->fetch() ?: null;

        $this->view('aulas/show', compact('course', 'module', 'lesson', 'completed', 'exercise', 'next'));
    }

    public function complete(string $courseSlug, string $moduleSlug, string $lessonSlug): void
    {
        $user = Auth::user();
        if (!$user) {
            $this->redirect('/login');
        }

        if (!$this->validateCsrf()) {
            Session::flash('error', 'Sessão expirada. Recarregue a página e tente novamente.');
            $this->redirect('/aulas/' . rawurlencode($courseSlug) . '/' . rawurlencode($moduleSlug) . '/' . rawurlencode($lessonSlug));
        }

        [$course, $module, $lesson] = $this->resolveLesson($courseSlug, $moduleSlug, $lessonSlug);

        if (!$this->canAccessModule((int) $user['id'], $module)) {
            Session::flash('error', 'Este módulo ainda está bloqueado.');
            $this->redirect('/dashboard#curso-' . rawurlencode($courseSlug));
        }

        // Aulas com exercício só podem ser concluídas ao acertar o exercício.
        // Isso evita pular o desafio enviando um POST direto para /concluir.
        $exercise = Exercise::firstWhereAll([
            'lesson_id' => $lesson['id'],
            'status' => 'published',
        ]);
        if ($exercise) {
            Session::flash('error', 'Conclua o exercício de fixação para finalizar esta aula.');
            $this->redirect('/exercicios/' . rawurlencode($courseSlug) . '/' . rawurlencode($moduleSlug) . '/' . rawurlencode($lessonSlug));
        }

        $this->db()->beginTransaction();

        try {
            // Serializa alterações de XP do mesmo usuário e evita duplo clique concorrente.
            $stmt = $this->db()->prepare('SELECT id FROM users WHERE id = ? FOR UPDATE');
            $stmt->execute([$user['id']]);

            $stmt = $this->db()->prepare(
                'SELECT id, completed
                 FROM user_lesson_progress
                 WHERE user_id = ? AND lesson_id = ?
                 LIMIT 1
                 FOR UPDATE'
            );
            $stmt->execute([$user['id'], $lesson['id']]);
            $progress = $stmt->fetch();

            if ($progress && (bool) $progress['completed']) {
                $this->db()->commit();
                Session::flash('success', 'Esta aula já estava concluída.');
                $this->redirect('/aulas/' . rawurlencode($courseSlug) . '/' . rawurlencode($moduleSlug) . '/' . rawurlencode($lessonSlug));
            }

            $xpReward = max(0, (int) ($lesson['xp_reward'] ?? 0));

            if ($progress) {
                $stmt = $this->db()->prepare(
                    'UPDATE user_lesson_progress
                     SET completed = 1, completed_at = NOW(), xp_earned = ?
                     WHERE id = ?'
                );
                $stmt->execute([$xpReward, $progress['id']]);
            } else {
                $stmt = $this->db()->prepare(
                    'INSERT INTO user_lesson_progress
                        (user_id, lesson_id, completed, completed_at, xp_earned)
                     VALUES (?, ?, 1, NOW(), ?)'
                );
                $stmt->execute([$user['id'], $lesson['id'], $xpReward]);
            }

            if ($xpReward > 0) {
                $stmt = $this->db()->prepare('UPDATE users SET xp = xp + ? WHERE id = ?');
                $stmt->execute([$xpReward, $user['id']]);
            }

            $this->db()->commit();
            Session::flash('success', 'Aula concluída! +' . $xpReward . ' XP.');
        } catch (\Throwable $e) {
            if ($this->db()->inTransaction()) {
                $this->db()->rollBack();
            }
            throw $e;
        }

        $this->redirect('/aulas/' . rawurlencode($courseSlug) . '/' . rawurlencode($moduleSlug) . '/' . rawurlencode($lessonSlug));
    }

    private function resolveLesson(string $courseSlug, string $moduleSlug, string $lessonSlug): array
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

        return [$course, $module, $lesson];
    }
}
