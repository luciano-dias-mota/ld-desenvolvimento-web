<?php

namespace App\Services;

use App\Core\Database;
use PDO;

final class ProgressService
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::getInstance()->getConnection();
    }

    public function canAccessModule(int $userId, array $module): bool
    {
        $courseId = (int) ($module['course_id'] ?? 0);
        $moduleNumber = (int) ($module['module_number'] ?? 0);

        if ($courseId <= 0 || $moduleNumber <= 0 || ($module['status'] ?? '') !== 'published') {
            return false;
        }

        $stmt = $this->db->prepare(
            "SELECT id
             FROM modules
             WHERE course_id = ?
               AND status = 'published'
               AND module_number < ?
             ORDER BY module_number DESC, id DESC
             LIMIT 1"
        );
        $stmt->execute([$courseId, $moduleNumber]);
        $previousModuleId = $stmt->fetchColumn();

        if (!$previousModuleId) {
            return true;
        }

        return $this->hasPassedModule($userId, (int) $previousModuleId);
    }

    public function hasPassedModule(int $userId, int $moduleId): bool
    {
        $stmt = $this->db->prepare(
            "SELECT 1
             FROM module_tests mt
             INNER JOIN user_module_tests umt ON umt.module_test_id = mt.id
             WHERE mt.module_id = ?
               AND mt.status = 'published'
               AND umt.user_id = ?
               AND umt.passed = 1
             LIMIT 1"
        );
        $stmt->execute([$moduleId, $userId]);
        return (bool) $stmt->fetchColumn();
    }

    public function hasCompletedAllLessons(int $userId, int $moduleId): bool
    {
        $stmt = $this->db->prepare(
            "SELECT COUNT(*)
             FROM lessons
             WHERE module_id = ? AND status = 'published'"
        );
        $stmt->execute([$moduleId]);
        $totalLessons = (int) $stmt->fetchColumn();

        if ($totalLessons === 0) {
            return false;
        }

        $stmt = $this->db->prepare(
            "SELECT COUNT(DISTINCT l.id)
             FROM lessons l
             INNER JOIN user_lesson_progress ulp ON ulp.lesson_id = l.id
             WHERE l.module_id = ?
               AND l.status = 'published'
               AND ulp.user_id = ?
               AND ulp.completed = 1"
        );
        $stmt->execute([$moduleId, $userId]);

        return (int) $stmt->fetchColumn() >= $totalLessons;
    }

    /**
     * Aplica locked/active/completed em uma única fonte de verdade.
     * O array pode conter módulos de vários cursos, desde que venha ordenado por
     * course_id, module_number e id (como no DashboardController).
     */
    public function decorateModuleStatuses(int $userId, array $modules): array
    {
        if ($modules === []) {
            return [];
        }

        $moduleIds = array_values(array_unique(array_map(
            static fn(array $module): int => (int) $module['id'],
            $modules
        )));

        $placeholders = implode(',', array_fill(0, count($moduleIds), '?'));
        $stmt = $this->db->prepare(
            "SELECT DISTINCT mt.module_id
             FROM module_tests mt
             INNER JOIN user_module_tests umt ON umt.module_test_id = mt.id
             WHERE mt.module_id IN ({$placeholders})
               AND mt.status = 'published'
               AND umt.user_id = ?
               AND umt.passed = 1"
        );
        $stmt->execute(array_merge($moduleIds, [$userId]));
        $passedModuleIds = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
        $passedMap = array_fill_keys($passedModuleIds, true);

        $currentCourseId = null;
        $previousPassed = true;

        foreach ($modules as &$module) {
            $courseId = (int) $module['course_id'];
            if ($currentCourseId !== $courseId) {
                $currentCourseId = $courseId;
                $previousPassed = true;
            }

            $passed = isset($passedMap[(int) $module['id']]);
            $module['progress_status'] = $passed
                ? 'completed'
                : ($previousPassed ? 'active' : 'locked');

            $previousPassed = $passed;
        }
        unset($module);

        return $modules;
    }

    public function isCourseComplete(int $userId, int $courseId): bool
    {
        $stmt = $this->db->prepare(
            "SELECT id
             FROM modules
             WHERE course_id = ? AND status = 'published'
             ORDER BY module_number ASC, id ASC"
        );
        $stmt->execute([$courseId]);
        $moduleIds = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));

        if ($moduleIds === []) {
            return false;
        }

        foreach ($moduleIds as $moduleId) {
            if (!$this->hasCompletedAllLessons($userId, $moduleId)
                || !$this->hasPassedModule($userId, $moduleId)) {
                return false;
            }
        }

        return true;
    }
}
