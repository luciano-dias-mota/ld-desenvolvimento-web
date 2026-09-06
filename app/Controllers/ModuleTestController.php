<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Session;
use App\Models\Certificate;
use App\Models\Course;
use App\Models\Module;
use App\Models\ModuleTest;

class ModuleTestController extends Controller
{
    public function show(string $courseSlug, string $moduleSlug): void
    {
        $user = Auth::user();
        if (!$user) {
            $this->redirect('/login');
        }

        [$course, $module, $test] = $this->resolveTest($courseSlug, $moduleSlug);

        if (!$this->canAccessModule((int) $user['id'], $module)) {
            Session::flash('error', 'Este módulo ainda está bloqueado.');
            $this->redirect('/dashboard?curso=' . rawurlencode($courseSlug));
        }

        if (!$this->hasCompletedAllLessons((int) $user['id'], (int) $module['id'])) {
            Session::flash('error', 'Conclua todas as aulas deste módulo antes de fazer a prova.');
            $this->redirect('/cursos/' . rawurlencode($courseSlug) . '/' . rawurlencode($moduleSlug));
        }

        if ($this->hasPassedModule((int) $user['id'], (int) $module['id'])) {
            Session::flash('success', 'Você já foi aprovado neste módulo.');
            $this->redirect('/dashboard?curso=' . rawurlencode($courseSlug));
        }

        $stmt = $this->db->prepare(
            'SELECT *
             FROM test_questions
             WHERE module_test_id = ?
             ORDER BY question_number ASC, id ASC'
        );
        $stmt->execute([$test['id']]);
        $questions = $stmt->fetchAll();

        if ($questions === []) {
            Session::flash('error', 'A prova deste módulo ainda não possui questões.');
            $this->redirect('/cursos/' . rawurlencode($courseSlug) . '/' . rawurlencode($moduleSlug));
        }

        // Resultado é flash: aparece uma vez após a tentativa reprovada.
        // Ao clicar em "Tentar novamente", a mesma URL volta a exibir o formulário.
        $testResult = Session::flash('test_result');
        $resultado = null;
        $passed = false;
        $score = 0.0;

        if (is_array($testResult)
            && (int) ($testResult['test_id'] ?? 0) === (int) $test['id']) {
            $resultado = $testResult;
            $passed = (bool) ($testResult['passed'] ?? false);
            $score = (float) ($testResult['score'] ?? 0);
        }

        $this->view(
            'cursos/prova',
            compact('course', 'module', 'test', 'questions', 'resultado', 'passed', 'score')
        );
    }

    public function submit(string $courseSlug, string $moduleSlug): void
    {
        $user = Auth::user();
        if (!$user) {
            $this->redirect('/login');
        }

        if (!$this->validateCsrf()) {
            Session::flash('error', 'Sessão expirada. Recarregue a página e tente novamente.');
            $this->redirect('/cursos/' . rawurlencode($courseSlug) . '/' . rawurlencode($moduleSlug) . '/prova');
        }

        [$course, $module, $test] = $this->resolveTest($courseSlug, $moduleSlug);

        if (!$this->canAccessModule((int) $user['id'], $module)) {
            Session::flash('error', 'Este módulo ainda está bloqueado.');
            $this->redirect('/dashboard?curso=' . rawurlencode($courseSlug));
        }

        if (!$this->hasCompletedAllLessons((int) $user['id'], (int) $module['id'])) {
            Session::flash('error', 'Conclua todas as aulas deste módulo antes de enviar a prova.');
            $this->redirect('/cursos/' . rawurlencode($courseSlug) . '/' . rawurlencode($moduleSlug));
        }

        $answers = $_POST['respostas'] ?? [];
        if (!is_array($answers)) {
            $answers = [];
        }

        $stmt = $this->db->prepare(
            'SELECT *
             FROM test_questions
             WHERE module_test_id = ?
             ORDER BY question_number ASC, id ASC'
        );
        $stmt->execute([$test['id']]);
        $questions = $stmt->fetchAll();

        if ($questions === []) {
            Session::flash('error', 'A prova deste módulo ainda não possui questões.');
            $this->redirect('/cursos/' . rawurlencode($courseSlug) . '/' . rawurlencode($moduleSlug));
        }

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
            Session::flash('error', 'A prova está configurada incorretamente. Informe o administrador.');
            $this->redirect('/cursos/' . rawurlencode($courseSlug) . '/' . rawurlencode($moduleSlug));
        }

        $score = round(($earnedPoints / $totalPoints) * 100, 2);
        $passingScore = (float) $test['passing_score'];
        $passed = $score >= $passingScore;

        $this->db->beginTransaction();

        try {
            $stmt = $this->db->prepare('SELECT id FROM users WHERE id = ? FOR UPDATE');
            $stmt->execute([$user['id']]);

            // Revalida dentro da transação para impedir XP duplicado em envios simultâneos.
            $stmt = $this->db->prepare(
                'SELECT 1
                 FROM user_module_tests
                 WHERE user_id = ? AND module_test_id = ? AND passed = 1
                 LIMIT 1'
            );
            $stmt->execute([$user['id'], $test['id']]);
            if ($stmt->fetchColumn()) {
                $this->db->commit();
                Session::flash('success', 'Você já havia sido aprovado neste módulo.');
                $this->redirect('/dashboard?curso=' . rawurlencode($courseSlug));
            }

            $stmt = $this->db->prepare(
                'SELECT COUNT(*)
                 FROM user_module_tests
                 WHERE user_id = ? AND module_test_id = ?'
            );
            $stmt->execute([$user['id'], $test['id']]);
            $attemptNumber = (int) $stmt->fetchColumn() + 1;

            $xpEarned = $passed ? max(0, (int) ($test['xp_reward'] ?? 0)) : 0;

            $stmt = $this->db->prepare(
                'INSERT INTO user_module_tests
                    (user_id, module_test_id, score, passed, xp_earned, attempt_number, started_at, completed_at)
                 VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())'
            );
            $stmt->execute([
                $user['id'],
                $test['id'],
                $score,
                (int) $passed,
                $xpEarned,
                $attemptNumber,
            ]);

            if ($passed && $xpEarned > 0) {
                $stmt = $this->db->prepare('UPDATE users SET xp = xp + ? WHERE id = ?');
                $stmt->execute([$xpEarned, $user['id']]);
            }

            // Não altera modules.status. O próximo módulo é liberado individualmente
            // pela aprovação registrada em user_module_tests.
            if ($passed && $this->allCourseModulesPassed((int) $user['id'], (int) $course['id'])) {
                $certificate = Certificate::getUserCertificate((int) $user['id'], (int) $course['id']);
                if (!$certificate) {
                    Certificate::createCertificate((int) $user['id'], (int) $course['id']);
                }
            }

            $this->db->commit();
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }

        if ($passed) {
            Session::flash('success', 'Aprovado com ' . $score . '%! Próxima fase liberada.');
            $this->redirect('/dashboard?curso=' . rawurlencode($courseSlug));
        }

        Session::flash('test_result', [
            'test_id' => (int) $test['id'],
            'score' => $score,
            'passed' => false,
        ]);

        $this->redirect('/cursos/' . rawurlencode($courseSlug) . '/' . rawurlencode($moduleSlug) . '/prova');
    }

    private function resolveTest(string $courseSlug, string $moduleSlug): array
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

        $test = ModuleTest::firstWhere('module_id', $module['id']);
        if (!$test || ($test['status'] ?? '') !== 'published') {
            Session::flash('error', 'A prova deste módulo ainda não está disponível.');
            $this->redirect('/cursos/' . rawurlencode($courseSlug) . '/' . rawurlencode($moduleSlug));
        }

        return [$course, $module, $test];
    }

    private function allCourseModulesPassed(int $userId, int $courseId): bool
    {
        $stmt = $this->db->prepare(
            'SELECT id FROM modules WHERE course_id = ? ORDER BY module_number ASC, id ASC'
        );
        $stmt->execute([$courseId]);
        $moduleIds = array_map('intval', $stmt->fetchAll(\PDO::FETCH_COLUMN));

        if ($moduleIds === []) {
            return false;
        }

        foreach ($moduleIds as $moduleId) {
            if (!$this->hasPassedModule($userId, $moduleId)) {
                return false;
            }
        }

        return true;
    }
}
