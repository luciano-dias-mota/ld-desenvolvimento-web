<?php

namespace App\Services;

use App\Models\Course;
use App\Models\Lesson;
use App\Models\Module;
use App\Models\ModuleTest;
use App\Repositories\LearningRepository;

class LearningContentResolver
{
    public function __construct(private LearningRepository $repository)
    {
    }

    public function resolveModule(string $courseSlug, string $moduleSlug): ?array
    {
        $course = Course::firstWhere('slug', $courseSlug);
        if (!$course || ($course['status'] ?? '') !== 'published') {
            return null;
        }

        $module = Module::firstWhereAll([
            'course_id' => $course['id'],
            'slug' => $moduleSlug,
            'status' => 'published',
        ]);
        if (!$module) {
            return null;
        }

        return [$course, $module];
    }

    public function resolveLesson(string $courseSlug, string $moduleSlug, string $lessonSlug): ?array
    {
        $resolvedModule = $this->resolveModule($courseSlug, $moduleSlug);
        if ($resolvedModule === null) {
            return null;
        }

        [$course, $module] = $resolvedModule;
        $lesson = Lesson::firstWhereAll([
            'module_id' => $module['id'],
            'slug' => $lessonSlug,
        ]);

        if (!$lesson || ($lesson['status'] ?? '') !== 'published') {
            return null;
        }

        return [$course, $module, $lesson];
    }

    public function resolveExercise(
        string $courseSlug,
        string $moduleSlug,
        string $lessonSlug,
        bool $includeAnswer = false
    ): ?array {
        $resolvedLesson = $this->resolveLesson($courseSlug, $moduleSlug, $lessonSlug);
        if ($resolvedLesson === null) {
            return null;
        }

        [$course, $module, $lesson] = $resolvedLesson;
        $exercise = $this->repository->firstPublishedExercise((int) $lesson['id'], $includeAnswer);

        return [$course, $module, $lesson, $exercise];
    }

    public function resolveTest(string $courseSlug, string $moduleSlug): ?array
    {
        $resolvedModule = $this->resolveModule($courseSlug, $moduleSlug);
        if ($resolvedModule === null) {
            return null;
        }

        [$course, $module] = $resolvedModule;
        $test = ModuleTest::firstWhere('module_id', $module['id']);

        if (!$test || ($test['status'] ?? '') !== 'published') {
            $test = null;
        }

        return [$course, $module, $test];
    }
}
