<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Services\EmailVerificationService;
use App\Services\ProgressService;

abstract class LearningController extends Controller
{
    private ?ProgressService $progressService = null;

    protected function progress(): ProgressService
    {
        if ($this->progressService === null) {
            $this->progressService = new ProgressService($this->db());
        }

        return $this->progressService;
    }

    protected function isGuestMode(): bool
    {
        return Auth::isGuest();
    }

    protected function canAccessModule(int $userId, array $module): bool
    {
        if ($this->isGuestMode()) {
            return ($module['status'] ?? '') === 'published';
        }

        return $this->progress()->canAccessModule($userId, $module);
    }

    protected function hasPassedModule(int $userId, int $moduleId): bool
    {
        return !$this->isGuestMode() && $this->progress()->hasPassedModule($userId, $moduleId);
    }

    protected function hasCompletedAllLessons(int $userId, int $moduleId): bool
    {
        return !$this->isGuestMode() && $this->progress()->hasCompletedAllLessons($userId, $moduleId);
    }

    protected function canIssueCertificateFor(array $user): bool
    {
        return (new EmailVerificationService($this->db()))->canIssueCertificate($user);
    }
}
