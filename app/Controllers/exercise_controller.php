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

        $lessonModel = new Lesson();
        $lesson = $lessonModel->where('module_id', $module->id)->where('slug', $lessonSlug)->first();
        if (!$lesson) {
            return $this->view('errors/404');
        }

        $exerciseModel = new Exercise();
        $exercise = $exerciseModel->where('lesson_id', $lesson->id)->first();
        if (!$exercise) {
            return $this->redirect('/aulas/' . $courseSlug . '/' . $moduleSlug . '/' . $lessonSlug);
        }

        // Verificar submissão anterior
        $db = $this->db;
        $stmt = $db->prepare("SELECT answer, is_correct FROM user_exercise_submissions WHERE user_id = ? AND exercise_id = ? ORDER BY id DESC LIMIT 1");
        $stmt->execute([$user->id, $exercise->id]);
        $submission = $stmt->fetch(\PDO::FETCH_ASSOC);

        $answer = $submission['answer'] ?? null;
        $isCorrect = $submission['is_correct'] ?? null;

        // Para múltipla escolha, decodificar options
        $options = json_decode($exercise->options, true);
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

        $lessonModel = new Lesson();
        $lesson = $lessonModel->where('module_id', $module->id)->where('slug', $lessonSlug)->first();
        if (!$lesson) {
            return $this->view('errors/404');
        }

        $exerciseModel = new Exercise();
        $exercise = $exerciseModel->where('lesson_id', $lesson->id)->first();
        if (!$exercise) {
            return $this->redirect('/aulas/' . $courseSlug . '/' . $moduleSlug . '/' . $lessonSlug);
        }

        // Processar resposta
        $answer = $_POST['resposta'] ?? '';
        $isCorrect = false;

        // Verificar tipo
        if ($exercise->exercise_type == 'multiple_choice' || $exercise->exercise_type == 'true_false') {
            // Comparar com correct_answer (que é uma string)
            $correct = $exercise->correct_answer;
            $isCorrect = (trim($answer) == trim($correct));
        } else {
            // Para código/texto, podemos fazer uma validação simples (ex: comparar string exata ou permitir)
            // Aqui, para simplificar, consideramos correto se o usuário acertar a saída esperada.
            // Você pode implementar uma lógica mais avançada.
            $correct = $exercise->correct_answer; // pode ser uma saída esperada
            $isCorrect = (trim($answer) == trim($correct));
        }

        // Registrar submissão
        $xpEarned = $exercise->submitAnswer($user->id, $answer, $isCorrect);

        // Redirecionar com mensagem
        return $this->redirect('/exercicios/' . $courseSlug . '/' . $moduleSlug . '/' . $lessonSlug . '?result=' . ($isCorrect ? 'correct' : 'wrong'));
    }
}