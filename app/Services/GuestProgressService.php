<?php

namespace App\Services;

use App\Core\Session;
use App\Repositories\LearningRepository;

final class GuestProgressService
{
    private const SESSION_KEY = 'guest_learning_progress';

    public function __construct(private LearningRepository $repository)
    {
    }

    public function reset(): void
    {
        Session::set(self::SESSION_KEY, [
            'lessons' => [],
            'modules' => [],
        ]);
    }

    public function clear(): void
    {
        Session::remove(self::SESSION_KEY);
    }

    public function markLessonCompleted(int $lessonId): void
    {
        if ($lessonId <= 0) {
            return;
        }

        $state = $this->state();
        $state['lessons'][(string) $lessonId] = true;
        $this->save($state);
    }

    public function isLessonCompleted(int $lessonId): bool
    {
        $state = $this->state();

        return !empty($state['lessons'][(string) $lessonId]);
    }

    public function markModulePassed(int $moduleId): void
    {
        if ($moduleId <= 0) {
            return;
        }

        $state = $this->state();
        $state['modules'][(string) $moduleId] = true;
        $this->save($state);
    }

    public function hasPassedModule(int $moduleId): bool
    {
        $state = $this->state();

        return !empty($state['modules'][(string) $moduleId]);
    }

    public function canAccessModule(array $module): bool
    {
        if (($module['status'] ?? '') !== 'published') {
            return false;
        }

        $previous = $this->repository->previousPublishedModule(
            (int) ($module['course_id'] ?? 0),
            (int) ($module['module_number'] ?? 0),
            (int) ($module['id'] ?? 0)
        );

        if ($previous === null) {
            return true;
        }

        return $this->hasPassedModule((int) $previous['id']);
    }

    public function canAccessLesson(array $module, array $lesson): bool
    {
        if (!$this->canAccessModule($module)) {
            return false;
        }

        $previousLessonIds = $this->repository->previousPublishedLessonIds(
            (int) $module['id'],
            (int) ($lesson['lesson_number'] ?? 0),
            (int) ($lesson['id'] ?? 0)
        );

        foreach ($previousLessonIds as $previousLessonId) {
            if (!$this->isLessonCompleted($previousLessonId)) {
                return false;
            }
        }

        return true;
    }

    public function hasCompletedAllLessons(int $moduleId): bool
    {
        $lessonIds = $this->repository->publishedLessonIdsForModule($moduleId);
        if ($lessonIds === []) {
            return false;
        }

        foreach ($lessonIds as $lessonId) {
            if (!$this->isLessonCompleted($lessonId)) {
                return false;
            }
        }

        return true;
    }

    public function completedLessonCount(int $moduleId): int
    {
        $lessonIds = $this->repository->publishedLessonIdsForModule($moduleId);
        $count = 0;

        foreach ($lessonIds as $lessonId) {
            if ($this->isLessonCompleted($lessonId)) {
                $count++;
            }
        }

        return $count;
    }

    private function state(): array
    {
        $state = Session::get(self::SESSION_KEY, []);

        if (!is_array($state)) {
            $state = [];
        }

        $lessons = $state['lessons'] ?? [];
        $modules = $state['modules'] ?? [];

        return [
            'lessons' => is_array($lessons) ? $lessons : [],
            'modules' => is_array($modules) ? $modules : [],
        ];
    }

    private function save(array $state): void
    {
        Session::set(self::SESSION_KEY, $state);
    }
}
