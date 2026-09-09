<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Repositories\LearningRepository;
use App\Services\EmailVerificationService;
use App\Services\GuestProgressService;
use App\Services\LearningContentResolver;
use App\Services\ProgressService;

abstract class LearningController extends Controller
{
    private ?ProgressService $progressService = null;
    private ?LearningRepository $learningRepository = null;
    private ?LearningContentResolver $contentResolver = null;
    private ?GuestProgressService $guestProgressService = null;

    protected function progress(): ProgressService
    {
        if ($this->progressService === null) {
            $this->progressService = new ProgressService($this->db());
        }

        return $this->progressService;
    }

    protected function learningRepository(): LearningRepository
    {
        if ($this->learningRepository === null) {
            $this->learningRepository = new LearningRepository($this->db());
        }

        return $this->learningRepository;
    }

    protected function contentResolver(): LearningContentResolver
    {
        if ($this->contentResolver === null) {
            $this->contentResolver = new LearningContentResolver($this->learningRepository());
        }

        return $this->contentResolver;
    }

    protected function guestProgress(): GuestProgressService
    {
        if ($this->guestProgressService === null) {
            $this->guestProgressService = new GuestProgressService($this->learningRepository());
        }

        return $this->guestProgressService;
    }

    protected function isGuestMode(): bool
    {
        return Auth::isGuest();
    }

    protected function canAccessModule(?int $userId, array $module): bool
    {
        if ($this->isGuestMode()) {
            return $this->guestProgress()->canAccessModule($module);
        }

        if ($userId === null || $userId <= 0) {
            return false;
        }

        return $this->progress()->canAccessModule($userId, $module);
    }

    protected function canAccessLesson(?int $userId, array $module, array $lesson): bool
    {
        if (!$this->canAccessModule($userId, $module)) {
            return false;
        }

        if ($this->isGuestMode()) {
            return $this->guestProgress()->canAccessLesson($module, $lesson);
        }

        if ($userId === null || $userId <= 0) {
            return false;
        }

        $previousLessonIds = $this->learningRepository()->previousPublishedLessonIds(
            (int) $module['id'],
            (int) ($lesson['lesson_number'] ?? 0),
            (int) ($lesson['id'] ?? 0)
        );

        if ($previousLessonIds === []) {
            return true;
        }

        $progressMap = $this->learningRepository()->lessonProgressMap(
            $userId,
            $previousLessonIds
        );

        foreach ($previousLessonIds as $previousLessonId) {
            if (empty($progressMap[$previousLessonId])) {
                return false;
            }
        }

        return true;
    }

    protected function isLessonCompleted(?int $userId, int $lessonId): bool
    {
        if ($this->isGuestMode()) {
            return $this->guestProgress()->isLessonCompleted($lessonId);
        }

        return $userId !== null
            && $userId > 0
            && $this->learningRepository()->hasCompletedLesson($userId, $lessonId);
    }

    protected function hasPassedModule(?int $userId, int $moduleId): bool
    {
        if ($this->isGuestMode()) {
            return $this->guestProgress()->hasPassedModule($moduleId);
        }

        return $userId !== null
            && $userId > 0
            && $this->progress()->hasPassedModule($userId, $moduleId);
    }

    protected function hasCompletedAllLessons(?int $userId, int $moduleId): bool
    {
        if ($this->isGuestMode()) {
            return $this->guestProgress()->hasCompletedAllLessons($moduleId);
        }

        return $userId !== null
            && $userId > 0
            && $this->progress()->hasCompletedAllLessons($userId, $moduleId);
    }

    protected function canIssueCertificateFor(array $user): bool
    {
        return (new EmailVerificationService($this->db()))->canIssueCertificate($user);
    }
}
