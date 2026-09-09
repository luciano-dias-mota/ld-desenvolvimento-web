<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Session;

class CourseController extends LearningController
{
    public function showModule(string $courseSlug, string $moduleSlug): void
    {
        $user = Auth::user();
        $isGuest = Auth::isGuest();

        if (!$user && !$isGuest) {
            $this->redirect('/register');
        }

        $resolved = $this->contentResolver()->resolveModule($courseSlug, $moduleSlug);
        if ($resolved === null) {
            $this->notFound();
        }

        [$course, $module] = $resolved;
        $userId = $user ? (int) $user['id'] : null;

        if (!$this->canAccessModule($userId, $module)) {
            Session::flash('error', 'Este módulo está bloqueado. Complete a fase anterior primeiro.');
            $this->redirect('/dashboard#curso-' . rawurlencode($courseSlug));
        }

        $lessons = $this->learningRepository()->publishedLessonsForModule((int) $module['id']);
        $progressMap = [];

        if (!$isGuest && $lessons !== []) {
            $lessonIds = array_map(
                static fn(array $lesson): int => (int) $lesson['id'],
                $lessons
            );
            $progressMap = $this->learningRepository()->lessonProgressMap(
                (int) $userId,
                $lessonIds
            );
        }

        $chainUnlocked = true;

        foreach ($lessons as &$lesson) {
            $lessonId = (int) $lesson['id'];

            $lesson['completed'] = $isGuest
                ? $this->guestProgress()->isLessonCompleted($lessonId)
                : ($progressMap[$lessonId] ?? false);

            $lesson['locked'] = !$chainUnlocked;

            if (!$lesson['completed']) {
                $chainUnlocked = false;
            }
        }
        unset($lesson);

        $module['progress_status'] = $this->hasPassedModule($userId, (int) $module['id'])
            ? 'completed'
            : 'active';

        $this->view(
            'cursos/modulo',
            compact('course', 'module', 'lessons', 'user', 'isGuest')
        );
    }
}
