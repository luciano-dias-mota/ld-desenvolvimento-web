<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Session;
use App\Models\Course;
use App\Models\Module;

class CourseController extends LearningController
{
    public function showModule(string $courseSlug, string $moduleSlug): void
    {
        $user = Auth::user();
        $isGuest = Auth::isGuest();
        if (!$user && !$isGuest) {
            $this->redirect('/register');
        }

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

        if (!$isGuest && !$this->canAccessModule((int) $user['id'], $module)) {
            Session::flash('error', 'Este módulo está bloqueado. Complete o módulo anterior primeiro.');
            $this->redirect('/dashboard#curso-' . rawurlencode($courseSlug));
        }

        $stmt = $this->db()->prepare(
            "SELECT * FROM lessons WHERE module_id = ? AND status = 'published' ORDER BY lesson_number ASC, id ASC"
        );
        $stmt->execute([$module['id']]);
        $lessons = $stmt->fetchAll();

        $progressMap = [];
        if (!$isGuest && $lessons !== []) {
            $lessonIds = array_map(static fn(array $lesson): int => (int) $lesson['id'], $lessons);
            $placeholders = implode(',', array_fill(0, count($lessonIds), '?'));
            $stmt = $this->db()->prepare(
                "SELECT lesson_id, completed FROM user_lesson_progress WHERE user_id = ? AND lesson_id IN ({$placeholders})"
            );
            $stmt->execute(array_merge([(int) $user['id']], $lessonIds));
            foreach ($stmt->fetchAll() as $progress) {
                $progressMap[(int) $progress['lesson_id']] = (bool) $progress['completed'];
            }
        }

        foreach ($lessons as &$lesson) {
            $lesson['completed'] = $isGuest ? false : ($progressMap[(int) $lesson['id']] ?? false);
        }
        unset($lesson);

        $module['progress_status'] = $isGuest
            ? 'active'
            : ($this->hasPassedModule((int) $user['id'], (int) $module['id']) ? 'completed' : 'active');

        $this->view('cursos/modulo', compact('course', 'module', 'lessons', 'user', 'isGuest'));
    }
}
