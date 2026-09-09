<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Session;

class LessonController extends LearningController
{
    public function show(string $courseSlug, string $moduleSlug, string $lessonSlug): void
    {
        $user = Auth::user();
        $isGuest = Auth::isGuest();

        if (!$user && !$isGuest) {
            $this->redirect('/register');
        }

        $resolved = $this->contentResolver()->resolveLesson(
            $courseSlug,
            $moduleSlug,
            $lessonSlug
        );
        if ($resolved === null) {
            $this->notFound();
        }

        [$course, $module, $lesson] = $resolved;
        $userId = $user ? (int) $user['id'] : null;

        if (!$this->canAccessModule($userId, $module)) {
            Session::flash('error', 'Este módulo ainda está bloqueado.');
            $this->redirect('/dashboard#curso-' . rawurlencode($courseSlug));
        }

        if (!$this->canAccessLesson($userId, $module, $lesson)) {
            Session::flash(
                'error',
                'Esta aula ainda está bloqueada. Conclua corretamente o exercício da aula anterior.'
            );
            $this->redirect(
                '/cursos/' . rawurlencode($courseSlug) . '/' . rawurlencode($moduleSlug)
            );
        }

        $completed = $this->isLessonCompleted($userId, (int) $lesson['id']);
        $exercise = $this->learningRepository()->firstPublishedExercise((int) $lesson['id']);

        $next = $this->learningRepository()->nextPublishedLesson(
            (int) $module['id'],
            (int) $lesson['lesson_number']
        );

        $this->view(
            'aulas/show',
            compact('course', 'module', 'lesson', 'completed', 'exercise', 'next', 'isGuest')
        );
    }

    public function complete(string $courseSlug, string $moduleSlug, string $lessonSlug): void
    {
        $user = Auth::user();
        $isGuest = Auth::isGuest();

        if (!$user && !$isGuest) {
            $this->redirect('/register');
        }

        $lessonUrl = '/aulas/'
            . rawurlencode($courseSlug)
            . '/'
            . rawurlencode($moduleSlug)
            . '/'
            . rawurlencode($lessonSlug);

        if (!$this->validateCsrf()) {
            Session::flash('error', 'Sessão expirada. Recarregue a página e tente novamente.');
            $this->redirect($lessonUrl);
        }

        $resolved = $this->contentResolver()->resolveLesson(
            $courseSlug,
            $moduleSlug,
            $lessonSlug
        );
        if ($resolved === null) {
            $this->notFound();
        }

        [, $module, $lesson] = $resolved;
        $userId = $user ? (int) $user['id'] : null;

        if (!$this->canAccessModule($userId, $module)) {
            Session::flash('error', 'Este módulo ainda está bloqueado.');
            $this->redirect('/dashboard#curso-' . rawurlencode($courseSlug));
        }

        if (!$this->canAccessLesson($userId, $module, $lesson)) {
            Session::flash(
                'error',
                'Esta aula ainda está bloqueada. Conclua corretamente o exercício da aula anterior.'
            );
            $this->redirect(
                '/cursos/' . rawurlencode($courseSlug) . '/' . rawurlencode($moduleSlug)
            );
        }

        $exercise = $this->learningRepository()->firstPublishedExercise((int) $lesson['id']);
        if ($exercise) {
            Session::flash('error', 'A aula só é concluída após você acertar o exercício de fixação.');
            $this->redirect(
                '/exercicios/'
                . rawurlencode($courseSlug)
                . '/'
                . rawurlencode($moduleSlug)
                . '/'
                . rawurlencode($lessonSlug)
            );
        }

        if ($isGuest) {
            $this->guestProgress()->markLessonCompleted((int) $lesson['id']);
            Session::flash(
                'success',
                'Aula concluída nesta sessão de visitante. Esse avanço não será salvo na sua conta.'
            );
            $this->redirect($lessonUrl);
        }

        $db = $this->db();
        $repository = $this->learningRepository();
        $db->beginTransaction();

        try {
            if (!$repository->lockUser((int) $userId)) {
                throw new \RuntimeException('Usuário não encontrado.');
            }

            $progress = $repository->lockLessonProgress(
                (int) $userId,
                (int) $lesson['id']
            );

            if ($progress && (bool) $progress['completed']) {
                $db->commit();
                Session::flash('success', 'Esta aula já estava concluída.');
                $this->redirect($lessonUrl);
            }

            $xpReward = max(0, (int) ($lesson['xp_reward'] ?? 0));

            if ($progress) {
                $repository->completeLessonProgress((int) $progress['id'], $xpReward);
            } else {
                $repository->createCompletedLessonProgress(
                    (int) $userId,
                    (int) $lesson['id'],
                    $xpReward
                );
            }

            $repository->addUserXp((int) $userId, $xpReward);
            $db->commit();

            Session::flash('success', 'Aula concluída! +' . $xpReward . ' XP.');
        } catch (\Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }

            throw $e;
        }

        $this->redirect($lessonUrl);
    }
}
