<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Session;

class ExerciseController extends LearningController
{
    public function show(string $courseSlug, string $moduleSlug, string $lessonSlug): void
    {
        $user = Auth::user();
        $isGuest = Auth::isGuest();

        if (!$user && !$isGuest) {
            $this->redirect('/register');
        }

        $resolved = $this->contentResolver()->resolveExercise(
            $courseSlug,
            $moduleSlug,
            $lessonSlug
        );
        if ($resolved === null) {
            $this->notFound();
        }

        [$course, $module, $lesson, $exercise] = $resolved;
        if (!$exercise) {
            $this->redirect($this->lessonUrl($courseSlug, $moduleSlug, $lessonSlug));
        }

        $userId = $user ? (int) $user['id'] : null;

        if (!$this->canAccessModule($userId, $module)) {
            Session::flash('error', 'Este módulo ainda está bloqueado.');
            $this->redirect('/dashboard#curso-' . rawurlencode($courseSlug));
        }

        if (!$this->canAccessLesson($userId, $module, $lesson)) {
            Session::flash(
                'error',
                'Este exercício ainda está bloqueado. Conclua as aulas anteriores primeiro.'
            );
            $this->redirect(
                '/cursos/' . rawurlencode($courseSlug) . '/' . rawurlencode($moduleSlug)
            );
        }

        $submission = null;
        $answer = null;
        $isCorrect = null;

        if ($isGuest) {
            $isCorrect = $this->guestProgress()->isLessonCompleted((int) $lesson['id'])
                ? true
                : null;
        } else {
            $submission = $this->learningRepository()->latestExerciseSubmission(
                (int) $userId,
                (int) $exercise['id']
            );
            $answer = $submission['answer'] ?? null;
            $isCorrect = is_array($submission) && array_key_exists('is_correct', $submission)
                ? (bool) $submission['is_correct']
                : null;
        }

        $result = Session::flash('exercise_result');
        if (!is_array($result) || (int) ($result['exercise_id'] ?? 0) !== (int) $exercise['id']) {
            $result = null;
        }

        if ($isGuest && is_array($result)) {
            $answer = $result['answer'] ?? null;
        }

        $options = json_decode((string) ($exercise['options'] ?? ''), true);
        if (!is_array($options)) {
            $options = [];
        }

        $lessonCompleted = $this->isLessonCompleted($userId, (int) $lesson['id']);
        $next = $lessonCompleted
            ? $this->learningRepository()->nextPublishedLesson(
                (int) $module['id'],
                (int) $lesson['lesson_number']
            )
            : null;

        $this->view(
            'exercicios/show',
            compact(
                'course',
                'module',
                'lesson',
                'exercise',
                'options',
                'answer',
                'isCorrect',
                'result',
                'submission',
                'isGuest',
                'lessonCompleted',
                'next'
            )
        );
    }

    public function submit(string $courseSlug, string $moduleSlug, string $lessonSlug): void
    {
        $user = Auth::user();
        $isGuest = Auth::isGuest();

        if (!$user && !$isGuest) {
            $this->redirect('/register');
        }

        $exerciseUrl = $this->exerciseUrl($courseSlug, $moduleSlug, $lessonSlug);

        if (!$this->validateCsrf()) {
            Session::flash('error', 'Sessão expirada. Recarregue a página e tente novamente.');
            $this->redirect($exerciseUrl);
        }

        $resolved = $this->contentResolver()->resolveExercise(
            $courseSlug,
            $moduleSlug,
            $lessonSlug,
            true
        );
        if ($resolved === null) {
            $this->notFound();
        }

        [, $module, $lesson, $exercise] = $resolved;
        if (!$exercise) {
            $this->redirect($this->lessonUrl($courseSlug, $moduleSlug, $lessonSlug));
        }

        $userId = $user ? (int) $user['id'] : null;

        if (!$this->canAccessModule($userId, $module)) {
            Session::flash('error', 'Este módulo ainda está bloqueado.');
            $this->redirect('/dashboard#curso-' . rawurlencode($courseSlug));
        }

        if (!$this->canAccessLesson($userId, $module, $lesson)) {
            Session::flash(
                'error',
                'Este exercício ainda está bloqueado. Conclua as aulas anteriores primeiro.'
            );
            $this->redirect(
                '/cursos/' . rawurlencode($courseSlug) . '/' . rawurlencode($moduleSlug)
            );
        }

        $answer = trim((string) ($_POST['resposta'] ?? ''));
        if ($answer === '' || strlen($answer) > 10000) {
            Session::flash('error', 'Envie uma resposta válida.');
            $this->redirect($exerciseUrl);
        }

        $isCorrect = $this->isCorrectAnswer($exercise, $answer);

        if ($isGuest) {
            if ($isCorrect) {
                $this->guestProgress()->markLessonCompleted((int) $lesson['id']);
            }

            Session::flash('exercise_result', [
                'exercise_id' => (int) $exercise['id'],
                'correct' => $isCorrect,
                'exercise_xp' => 0,
                'lesson_xp' => 0,
                'total_xp' => 0,
                'guest' => true,
                'answer' => $answer,
            ]);

            $this->redirect($exerciseUrl);
        }

        $db = $this->db();
        $repository = $this->learningRepository();
        $exerciseXpEarned = 0;
        $lessonXpEarned = 0;

        $db->beginTransaction();

        try {
            if (!$repository->lockUser((int) $userId)) {
                throw new \RuntimeException('Usuário não encontrado.');
            }

            $alreadyRewardedExercise = $repository->hasCorrectExerciseSubmission(
                (int) $userId,
                (int) $exercise['id']
            );

            $exerciseXpEarned = $isCorrect && !$alreadyRewardedExercise
                ? max(0, (int) ($exercise['xp_reward'] ?? 0))
                : 0;

            $repository->createExerciseSubmission(
                (int) $userId,
                (int) $exercise['id'],
                $answer,
                $isCorrect,
                $exerciseXpEarned
            );

            if ($isCorrect) {
                $progress = $repository->lockLessonProgress(
                    (int) $userId,
                    (int) $lesson['id']
                );

                if (!$progress || !(bool) $progress['completed']) {
                    $lessonXpEarned = max(0, (int) ($lesson['xp_reward'] ?? 0));

                    if ($progress) {
                        $repository->completeLessonProgress(
                            (int) $progress['id'],
                            $lessonXpEarned
                        );
                    } else {
                        $repository->createCompletedLessonProgress(
                            (int) $userId,
                            (int) $lesson['id'],
                            $lessonXpEarned
                        );
                    }
                }

                $repository->addUserXp(
                    (int) $userId,
                    $exerciseXpEarned + $lessonXpEarned
                );
            }

            $db->commit();
        } catch (\Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }

            throw $e;
        }

        Session::flash('exercise_result', [
            'exercise_id' => (int) $exercise['id'],
            'correct' => $isCorrect,
            'exercise_xp' => $exerciseXpEarned,
            'lesson_xp' => $lessonXpEarned,
            'total_xp' => $exerciseXpEarned + $lessonXpEarned,
            'guest' => false,
        ]);

        $this->redirect($exerciseUrl);
    }

    private function isCorrectAnswer(array $exercise, string $answer): bool
    {
        $correctAnswer = trim((string) ($exercise['correct_answer'] ?? ''));
        $type = (string) ($exercise['exercise_type'] ?? '');

        if (in_array($type, ['multiple_choice', 'true_false'], true)) {
            return $answer === $correctAnswer;
        }

        return $this->normalizeLineEndings($answer)
            === $this->normalizeLineEndings($correctAnswer);
    }

    private function normalizeLineEndings(string $value): string
    {
        return str_replace(["\r\n", "\r"], "\n", $value);
    }

    private function lessonUrl(string $courseSlug, string $moduleSlug, string $lessonSlug): string
    {
        return '/aulas/'
            . rawurlencode($courseSlug)
            . '/'
            . rawurlencode($moduleSlug)
            . '/'
            . rawurlencode($lessonSlug);
    }

    private function exerciseUrl(string $courseSlug, string $moduleSlug, string $lessonSlug): string
    {
        return '/exercicios/'
            . rawurlencode($courseSlug)
            . '/'
            . rawurlencode($moduleSlug)
            . '/'
            . rawurlencode($lessonSlug);
    }
}
