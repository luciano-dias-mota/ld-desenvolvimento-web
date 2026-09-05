<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Auth;
use App\Core\Session;
use App\Models\Course;
use App\Models\Module;
use App\Models\ModuleTest;
use App\Models\TestQuestion;
use App\Models\UserModuleTest;

class ModuleTestController extends Controller
{
    public function show($courseSlug, $moduleSlug)
    {
        $user = Auth::user();
        if (!$user) {
            return $this->redirect('/login');
        }

        // Buscar curso, módulo, prova
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

        // Verificar se o módulo está ativo ou concluído
        if ($module['status'] == 'locked') {
            return $this->redirect('/dashboard?curso=' . $courseSlug);
        }

        // Buscar prova
        $test = ModuleTest::firstWhere('module_id', $module['id']);
        if (!$test) {
            return $this->redirect('/dashboard?curso=' . $courseSlug);
        }

        // Verificar se o usuário já fez a prova e passou
        $stmt = $this->db->prepare("SELECT * FROM user_module_tests WHERE user_id = ? AND module_test_id = ? ORDER BY id DESC LIMIT 1");
        $stmt->execute([$user['id'], $test['id']]);
        $lastAttempt = $stmt->fetch(\PDO::FETCH_ASSOC);

        if ($lastAttempt && $lastAttempt['passed']) {
            // Já passou, redirecionar para o mapa
            return $this->redirect('/dashboard?curso=' . $courseSlug);
        }

        // Buscar questões
        $questions = TestQuestion::where('module_test_id', $test['id']);

        // Se já tentou mas não passou, mostrar resultado anterior?
        $resultado = null;
        $passed = false;
        $score = 0;
        if ($lastAttempt && !$lastAttempt['passed']) {
            $resultado = $lastAttempt;
            $passed = false;
            $score = $lastAttempt['score'];
        }

        $this->view('cursos/prova', compact('course', 'module', 'test', 'questions', 'resultado', 'passed', 'score'));
    }

    public function submit($courseSlug, $moduleSlug)
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

        // Mesma checagem que já existe em show(): impede que a prova seja
        // enviada via POST direto para um módulo ainda bloqueado
        if ($module['status'] == 'locked') {
            return $this->redirect('/dashboard?curso=' . $courseSlug);
        }

        $test = ModuleTest::firstWhere('module_id', $module['id']);
        if (!$test) {
            return $this->redirect('/dashboard?curso=' . $courseSlug);
        }

        // Verificar se o usuário já passou
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM user_module_tests WHERE user_id = ? AND module_test_id = ? AND passed = 1");
        $stmt->execute([$user['id'], $test['id']]);
        if ($stmt->fetchColumn() > 0) {
            return $this->redirect('/dashboard?curso=' . $courseSlug);
        }

        // Pegar respostas
        $answers = $_POST['respostas'] ?? [];

        // Buscar questões
        $questions = TestQuestion::where('module_test_id', $test['id']);

        $totalPoints = 0;
        $earnedPoints = 0;
        foreach ($questions as $q) {
            $totalPoints += $q['points'];
            $userAnswer = $answers[$q['id']] ?? '';
            if ($userAnswer == $q['correct_answer']) {
                $earnedPoints += $q['points'];
            }
        }

        $score = ($totalPoints > 0) ? ($earnedPoints / $totalPoints) * 100 : 0;
        $passed = $score >= $test['passing_score'];

        // Registrar tentativa
        $xpEarned = $passed ? $test['xp_reward'] : 0;
        $attemptNumber = $this->countAttempts($user['id'], $test['id']) + 1;
        $stmt = $this->db->prepare("INSERT INTO user_module_tests (user_id, module_test_id, score, passed, xp_earned, attempt_number, started_at, completed_at) VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())");
        $stmt->execute([$user['id'], $test['id'], $score, (int) $passed, $xpEarned, $attemptNumber]);

        // Atualizar XP do usuário se passou
        if ($passed) {
            $this->db->prepare("UPDATE users SET xp = xp + ? WHERE id = ?")
                ->execute([$xpEarned, $user['id']]);

            // Desbloquear próximo módulo
            $nextModule = Module::where('course_id', $course['id']);
            // Filtrar por module_number > atual
            $nextModule = array_filter($nextModule, function($m) use ($module) {
                return $m['module_number'] > $module['module_number'];
            });
            usort($nextModule, function($a, $b) {
                return $a['module_number'] <=> $b['module_number'];
            });
            if (!empty($nextModule)) {
                $next = $nextModule[0];
                $this->db->prepare("UPDATE modules SET status = 'active' WHERE id = ?")
                    ->execute([$next['id']]);
            }

            // Verificar se é o último módulo -> certificado
            $allModules = Module::where('course_id', $course['id']);
            $maxNumber = max(array_column($allModules, 'module_number'));
            if ($module['module_number'] == $maxNumber) {
                // Todos os módulos concluídos? Gerar certificado
                $cert = \App\Models\Certificate::getUserCertificate($user['id'], $course['id']);
                if (!$cert) {
                    \App\Models\Certificate::createCertificate($user['id'], $course['id']);
                }
            }
        }

        // Redirecionar de volta para a prova com resultado
        return $this->redirect('/cursos/' . $courseSlug . '/' . $moduleSlug . '/prova');
    }

    private function countAttempts($userId, $testId): int
    {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM user_module_tests WHERE user_id = ? AND module_test_id = ?");
        $stmt->execute([$userId, $testId]);
        return (int) $stmt->fetchColumn();
    }
}