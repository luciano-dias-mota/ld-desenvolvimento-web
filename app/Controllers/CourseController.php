<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Session;
use App\Models\Course;
use App\Models\Module;

class CourseController extends Controller
{
    public function showModule(string $courseSlug, string $moduleSlug): void
    {
        $user = Auth::user();
        if (!$user) {
            $this->redirect('/login');
        }

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

        if (!$this->canAccessModule((int) $user['id'], $module)) {
            Session::flash('error', 'Este módulo está bloqueado. Complete o módulo anterior primeiro.');
            $this->redirect('/dashboard?curso=' . rawurlencode($courseSlug));
        }

        $stmt = $this->db->prepare(
            "SELECT *
             FROM lessons
             WHERE module_id = ? AND status = 'published'
             ORDER BY lesson_number ASC, id ASC"
        );
        $stmt->execute([$module['id']]);
        $lessons = $stmt->fetchAll();

        $progressMap = [];
        if ($lessons !== []) {
            $lessonIds = array_map(static fn(array $lesson): int => (int) $lesson['id'], $lessons);
            $placeholders = implode(',', array_fill(0, count($lessonIds), '?'));

            $stmt = $this->db->prepare(
                "SELECT lesson_id, completed
                 FROM user_lesson_progress
                 WHERE user_id = ? AND lesson_id IN ({$placeholders})"
            );
            $stmt->execute(array_merge([(int) $user['id']], $lessonIds));

            foreach ($stmt->fetchAll() as $progress) {
                $progressMap[(int) $progress['lesson_id']] = (bool) $progress['completed'];
            }
        }

        foreach ($lessons as &$lesson) {
            $lesson['completed'] = $progressMap[(int) $lesson['id']] ?? false;
        }
        unset($lesson);

        $module['status'] = $this->hasPassedModule((int) $user['id'], (int) $module['id'])
            ? 'completed'
            : 'active';

        $this->view('cursos/modulo', [
            'course' => $course,
            'module' => $module,
            'lessons' => $lessons,
            'user' => $user,
        ]);
    }
}
