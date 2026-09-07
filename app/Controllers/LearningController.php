<?php

namespace App\Controllers;

use App\Core\Controller;
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

    protected function canAccessModule(int $userId, array $module): bool
    {
        return $this->progress()->canAccessModule($userId, $module);
    }

    protected function hasPassedModule(int $userId, int $moduleId): bool
    {
        return $this->progress()->hasPassedModule($userId, $moduleId);
    }

    protected function hasCompletedAllLessons(int $userId, int $moduleId): bool
    {
        return $this->progress()->hasCompletedAllLessons($userId, $moduleId);
    }
}
