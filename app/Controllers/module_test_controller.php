<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Auth;
use App\Models\Course;
use App\Models\Module;
use App\Models\ModuleTest;
use App\Models\TestQuestion;

class ModuleTestController extends Controller
{
    public function show($courseSlug, $moduleSlug)
    {
        $user = Auth::user();
        if (!$user) {
            return $this->redirect('/login');
        }

        // Buscar curso, módulo, prova
        $courseModel = new Course();
        $course = $courseModel->where('slug', $courseSlug)->first();
        if (!$course) {
            return $this->view('errors/404');
        }

        $moduleModel = new Module();
        $module = $moduleModel->where('course_id', $course->id)->where('slug', $moduleSlug)->first();
        if (!$module) {
            return $this->view('errors/404');
        }

        // Verificar se o módulo está ativo ou concluído
        if ($module->status == 'locked') {
            $this->redirect('/dashboard?curso=' . $courseSlug);
        }

        // Buscar prova
        $testModel = new ModuleTest();
        $test = $testModel->where('module_id', $module->id)->first();
        if (!$test) {
            // Se não houver prova, redirecionar para mapa
            $this->redirect('/dashboard?curso=' . $courseSlug);
        }

        // Verificar se o usuário já fez a prova e passou
        $db = $this->db;
        $stmt = $db->prepare("SELECT * FROM user_module_tests WHERE user_id = ? AND module_test_id = ? ORDER BY id DESC LIMIT 1");
        $stmt->execute([$user->id, $test->id]);
        $lastAttempt = $stmt->fetch(\PDO::FETCH_ASSOC);

        if ($lastAttempt && $lastAttempt['passed']) {
            // Já passou, redirecionar para o mapa
            $this->redirect('/dashboard?curso=' . $courseSlug);
        }

        // Buscar questões
        $questionModel = new TestQuestion();
        $questions = $questionModel->where('module_test_id', $test->id)->orderBy('question_number')->get();

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
        $courseModel = new Course();
        $course = $courseModel->where('slug', $courseSlug)->first();
        if (!$course) {
            return $this->view('errors/404');
        }

        $moduleModel = new Module();
        $module = $moduleModel->where('course_id', $course->id)->where('slug', $moduleSlug)->first();
        if (!$module) {
            return $this->view('errors/404');
        }

        $testModel = new ModuleTest();
        $test = $testModel->where('module_id', $module->id)->first();
        if (!$test) {
            $this->redirect('/dashboard?curso=' . $courseSlug);
        }

        // Verificar se o usuário já passou
        $db = $this->db;
        $stmt = $db->prepare("SELECT COUNT(*) FROM user_module_tests WHERE user_id = ? AND module_test_id = ? AND passed = 1");
        $stmt->execute([$user->id, $test->id]);
        if ($stmt->fetchColumn() > 0) {
            $this->redirect('/dashboard?curso=' . $courseSlug);
        }

        // Pegar respostas
        $answers = $_POST['respostas'] ?? [];

        // Buscar questões
        $questionModel = new TestQuestion();
        $questions = $questionModel->where('module_test_id', $test->id)->get();

        $totalPoints = 0;
        $earnedPoints = 0;
        foreach ($questions as $q) {
            $totalPoints += $q->points;
            $userAnswer = $answers[$q->id] ?? '';
            if ($userAnswer == $q->correct_answer) {
                $earnedPoints += $q->points;
            }
        }

        $score = ($totalPoints > 0) ? ($earnedPoints / $totalPoints) * 100 : 0;
        $passed = $score >= $test->passing_score;

        // Registrar tentativa
        $test->recordAttempt($user->id, $score, $passed);

        // Se passou, desbloquear próximo módulo
        if ($passed) {
            // Buscar próximo módulo do curso (ordenado por module_number)
            $nextModule = $moduleModel->where('course_id', $course->id)
                                     ->where('module_number', '>', $module->module_number)
                                     ->orderBy('module_number', 'ASC')
                                     ->first();
            if ($nextModule) {
                // Desbloquear próximo módulo
                $nextModule->status = 'active';
                $nextModule->save();
            }

            // Verificar se é o último módulo -> certificado
            $allModules = $moduleModel->where('course_id', $course->id)->orderBy('module_number')->get();
            $lastModule = end($allModules);
            if ($module->id == $lastModule->id) {
                // Todos os módulos concluídos? Vamos gerar certificado
                $cert = new \App\Models\Certificate();
                if (!$cert::getUserCertificate($user->id, $course->id)) {
                    $cert::createCertificate($user->id, $course->id);
                }
            }
        }

        // Redirecionar de volta para a prova com resultado
        return $this->redirect('/cursos/' . $courseSlug . '/' . $moduleSlug . '/prova');
    }
}