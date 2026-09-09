<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Session;
use App\Models\Certificate;

class ModuleTestController extends LearningController
{
    public function show(string $courseSlug, string $moduleSlug): void
    {
        $user = Auth::user();
        $isGuest = Auth::isGuest();

        if (!$user && !$isGuest) {
            $this->redirect('/register');
        }

        [$course, $module, $test] = $this->resolveAvailableTest($courseSlug, $moduleSlug);
        $userId = $user ? (int) $user['id'] : null;

        if (!$this->canAccessModule($userId, $module)) {
            Session::flash('error', 'Este módulo ainda está bloqueado.');
            $this->redirect('/dashboard#curso-' . rawurlencode($courseSlug));
        }

        if (!$this->hasCompletedAllLessons($userId, (int) $module['id'])) {
            Session::flash(
                'error',
                'Conclua todas as aulas deste módulo e acerte seus exercícios antes de fazer a prova.'
            );
            $this->redirect($this->moduleUrl($courseSlug, $moduleSlug));
        }

        if (!$isGuest && $this->hasPassedModule($userId, (int) $module['id'])) {
            Session::flash('success', 'Você já foi aprovado neste módulo.');
            $this->redirect('/dashboard#curso-' . rawurlencode($courseSlug));
        }

        $maxAttempts = $isGuest ? 0 : $this->maxAttempts($test);
        $attemptCount = $isGuest
            ? 0
            : $this->learningRepository()->countTestAttempts(
                (int) $userId,
                (int) $test['id']
            );

        $testResult = Session::flash('test_result');
        $resultado = null;
        $passed = false;
        $score = 0.0;

        if (is_array($testResult) && (int) ($testResult['test_id'] ?? 0) === (int) $test['id']) {
            $resultado = $testResult;
            $passed = (bool) ($testResult['passed'] ?? false);
            $score = (float) ($testResult['score'] ?? 0);
        }

        $canRetry = $isGuest || $maxAttempts === 0 || $attemptCount < $maxAttempts;

        if (!$isGuest && !$canRetry && $resultado === null) {
            Session::flash('error', 'Você atingiu o limite de tentativas desta prova.');
            $this->redirect($this->moduleUrl($courseSlug, $moduleSlug));
        }

        $questions = $this->learningRepository()->testQuestions((int) $test['id']);

        if ($questions === []) {
            Session::flash('error', 'A prova deste módulo ainda não possui questões.');
            $this->redirect($this->moduleUrl($courseSlug, $moduleSlug));
        }

        $this->view(
            'cursos/prova',
            compact(
                'course',
                'module',
                'test',
                'questions',
                'resultado',
                'passed',
                'score',
                'attemptCount',
                'maxAttempts',
                'canRetry',
                'isGuest'
            )
        );
    }

    public function submit(string $courseSlug, string $moduleSlug): void
    {
        $user = Auth::user();
        $isGuest = Auth::isGuest();

        if (!$user && !$isGuest) {
            $this->redirect('/register');
        }

        if (!$this->validateCsrf()) {
            Session::flash('error', 'Sessão expirada. Recarregue a página e tente novamente.');
            $this->redirect($this->testUrl($courseSlug, $moduleSlug));
        }

        [$course, $module, $test] = $this->resolveAvailableTest($courseSlug, $moduleSlug);
        $userId = $user ? (int) $user['id'] : null;

        if (!$this->canAccessModule($userId, $module)) {
            Session::flash('error', 'Este módulo ainda está bloqueado.');
            $this->redirect('/dashboard#curso-' . rawurlencode($courseSlug));
        }

        if (!$this->hasCompletedAllLessons($userId, (int) $module['id'])) {
            Session::flash(
                'error',
                'Conclua todas as aulas deste módulo e acerte seus exercícios antes de enviar a prova.'
            );
            $this->redirect($this->moduleUrl($courseSlug, $moduleSlug));
        }

        $answers = $_POST['respostas'] ?? [];
        if (!is_array($answers)) {
            $answers = [];
        }

        $questions = $this->learningRepository()->testQuestions((int) $test['id'], true);

        if ($questions === []) {
            Session::flash('error', 'A prova deste módulo ainda não possui questões.');
            $this->redirect($this->moduleUrl($courseSlug, $moduleSlug));
        }

        $calculatedResult = $this->calculateResult(
            $questions,
            $answers,
            (float) $test['passing_score']
        );

        if ($calculatedResult === null) {
            Session::flash('error', 'A prova está configurada incorretamente. Informe o administrador.');
            $this->redirect($this->moduleUrl($courseSlug, $moduleSlug));
        }

        [$score, $passed] = $calculatedResult;

        if ($isGuest) {
            if ($passed) {
                $this->guestProgress()->markModulePassed((int) $module['id']);
            }

            Session::flash('test_result', [
                'test_id' => (int) $test['id'],
                'score' => $score,
                'passed' => $passed,
                'guest' => true,
            ]);

            $this->redirect($this->testUrl($courseSlug, $moduleSlug));
        }

        $db = $this->db();
        $repository = $this->learningRepository();

        $db->beginTransaction();

        try {
            if (!$repository->lockUser((int) $userId)) {
                throw new \RuntimeException('Usuário não encontrado.');
            }

            if ($repository->hasPassedTest((int) $userId, (int) $test['id'])) {
                $db->commit();
                Session::flash('success', 'Você já havia sido aprovado neste módulo.');
                $this->redirect('/dashboard#curso-' . rawurlencode($courseSlug));
            }

            $attemptCount = $repository->countTestAttempts(
                (int) $userId,
                (int) $test['id']
            );
            $maxAttempts = $this->maxAttempts($test);

            if ($maxAttempts > 0 && $attemptCount >= $maxAttempts) {
                $db->commit();
                Session::flash('error', 'Você atingiu o limite de tentativas desta prova.');
                $this->redirect($this->moduleUrl($courseSlug, $moduleSlug));
            }

            $attemptNumber = $attemptCount + 1;
            $xpEarned = $passed ? max(0, (int) ($test['xp_reward'] ?? 0)) : 0;

            $repository->createTestAttempt(
                (int) $userId,
                (int) $test['id'],
                $score,
                $passed,
                $xpEarned,
                $attemptNumber
            );

            $repository->addUserXp((int) $userId, $xpEarned);

            if (
                $passed
                && $this->progress()->isCourseComplete((int) $userId, (int) $course['id'])
                && $this->canIssueCertificateFor($user)
                && !Certificate::getUserCertificate((int) $userId, (int) $course['id'])
            ) {
                Certificate::createCertificate((int) $userId, (int) $course['id']);
            }

            $db->commit();
        } catch (\Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }

            throw $e;
        }

        if ($passed) {
            Session::flash('success', 'Aprovado com ' . $score . '%! Próxima fase liberada.');
            $this->redirect('/dashboard#curso-' . rawurlencode($courseSlug));
        }

        Session::flash('test_result', [
            'test_id' => (int) $test['id'],
            'score' => $score,
            'passed' => false,
            'guest' => false,
        ]);

        $this->redirect($this->testUrl($courseSlug, $moduleSlug));
    }

    private function resolveAvailableTest(string $courseSlug, string $moduleSlug): array
    {
        $resolved = $this->contentResolver()->resolveTest($courseSlug, $moduleSlug);

        if ($resolved === null) {
            $this->notFound();
        }

        [$course, $module, $test] = $resolved;

        if (!$test) {
            Session::flash('error', 'A prova deste módulo ainda não está disponível.');
            $this->redirect($this->moduleUrl($courseSlug, $moduleSlug));
        }

        return [$course, $module, $test];
    }

    private function calculateResult(array $questions, array $answers, float $passingScore): ?array
    {
        $totalPoints = 0.0;
        $earnedPoints = 0.0;

        foreach ($questions as $question) {
            $points = max(0, (float) ($question['points'] ?? 0));
            $totalPoints += $points;

            $questionId = (int) $question['id'];
            $userAnswer = trim((string) ($answers[$questionId] ?? ''));
            $correctAnswer = trim((string) ($question['correct_answer'] ?? ''));

            if ($userAnswer !== '' && $userAnswer === $correctAnswer) {
                $earnedPoints += $points;
            }
        }

        if ($totalPoints <= 0) {
            return null;
        }

        $score = round(($earnedPoints / $totalPoints) * 100, 2);

        return [$score, $score >= $passingScore];
    }

    private function maxAttempts(array $test): int
    {
        if (!isset($test['max_attempts']) || $test['max_attempts'] === null) {
            return 0;
        }

        return max(0, (int) $test['max_attempts']);
    }

    private function moduleUrl(string $courseSlug, string $moduleSlug): string
    {
        return '/cursos/' . rawurlencode($courseSlug) . '/' . rawurlencode($moduleSlug);
    }

    private function testUrl(string $courseSlug, string $moduleSlug): string
    {
        return $this->moduleUrl($courseSlug, $moduleSlug) . '/prova';
    }
}
