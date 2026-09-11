<?php

namespace App\Services;

use App\Core\Database;
use PDO;

final class ProfileService
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::getInstance()->getConnection();
    }

    public function summary(int $userId): array
    {
        if ($userId <= 0) {
            return $this->emptySummary();
        }

        $totalsStmt = $this->db->prepare(
            "SELECT
                (
                    SELECT COUNT(*)
                    FROM lessons l
                    INNER JOIN modules m ON m.id = l.module_id
                    INNER JOIN courses c ON c.id = m.course_id
                    WHERE l.status = 'published'
                      AND m.status = 'published'
                      AND c.status = 'published'
                ) AS total_lessons,
                (
                    SELECT COUNT(DISTINCT l.id)
                    FROM lessons l
                    INNER JOIN modules m ON m.id = l.module_id
                    INNER JOIN courses c ON c.id = m.course_id
                    INNER JOIN user_lesson_progress ulp ON ulp.lesson_id = l.id
                    WHERE l.status = 'published'
                      AND m.status = 'published'
                      AND c.status = 'published'
                      AND ulp.user_id = ?
                      AND ulp.completed = 1
                ) AS completed_lessons,
                (
                    SELECT COUNT(*)
                    FROM modules m
                    INNER JOIN courses c ON c.id = m.course_id
                    WHERE m.status = 'published'
                      AND c.status = 'published'
                ) AS total_modules,
                (
                    SELECT COUNT(DISTINCT m.id)
                    FROM modules m
                    INNER JOIN courses c ON c.id = m.course_id
                    INNER JOIN module_tests mt
                        ON mt.module_id = m.id
                       AND mt.status = 'published'
                    INNER JOIN user_module_tests umt
                        ON umt.module_test_id = mt.id
                       AND umt.user_id = ?
                       AND umt.passed = 1
                    WHERE m.status = 'published'
                      AND c.status = 'published'
                ) AS completed_modules"
        );
        $totalsStmt->execute([$userId, $userId]);
        $totals = $totalsStmt->fetch(PDO::FETCH_ASSOC) ?: [];

        $moduleStmt = $this->db->prepare(
            "SELECT
                c.id AS course_id,
                c.title AS course_title,
                c.slug AS course_slug,
                m.id AS module_id,
                m.title AS module_title,
                m.module_number,
                (
                    SELECT COUNT(*)
                    FROM lessons l
                    WHERE l.module_id = m.id
                      AND l.status = 'published'
                ) AS lessons_count,
                (
                    SELECT COUNT(DISTINCT l2.id)
                    FROM lessons l2
                    INNER JOIN user_lesson_progress ulp
                        ON ulp.lesson_id = l2.id
                    WHERE l2.module_id = m.id
                      AND l2.status = 'published'
                      AND ulp.user_id = ?
                      AND ulp.completed = 1
                ) AS lessons_completed,
                EXISTS(
                    SELECT 1
                    FROM module_tests mt
                    INNER JOIN user_module_tests umt
                        ON umt.module_test_id = mt.id
                    WHERE mt.module_id = m.id
                      AND mt.status = 'published'
                      AND umt.user_id = ?
                      AND umt.passed = 1
                ) AS test_passed
             FROM courses c
             INNER JOIN modules m ON m.course_id = c.id
             WHERE c.status = 'published'
               AND m.status = 'published'
             ORDER BY c.id ASC, m.module_number ASC, m.id ASC"
        );
        $moduleStmt->execute([$userId, $userId]);
        $rows = $moduleStmt->fetchAll(PDO::FETCH_ASSOC);

        $courses = [];
        foreach ($rows as $row) {
            $courseId = (int) $row['course_id'];
            $totalLessons = (int) $row['lessons_count'];
            $completedLessons = min($totalLessons, (int) $row['lessons_completed']);
            $percent = $totalLessons > 0
                ? (int) round(($completedLessons / $totalLessons) * 100)
                : 0;

            if (!isset($courses[$courseId])) {
                $courses[$courseId] = [
                    'id' => $courseId,
                    'title' => (string) $row['course_title'],
                    'slug' => (string) $row['course_slug'],
                    'lessons_count' => 0,
                    'lessons_completed' => 0,
                    'modules' => [],
                ];
            }

            $courses[$courseId]['lessons_count'] += $totalLessons;
            $courses[$courseId]['lessons_completed'] += $completedLessons;
            $courses[$courseId]['modules'][] = [
                'id' => (int) $row['module_id'],
                'title' => (string) $row['module_title'],
                'module_number' => (int) $row['module_number'],
                'lessons_count' => $totalLessons,
                'lessons_completed' => $completedLessons,
                'percent' => $percent,
                'completed' => (bool) $row['test_passed']
                    && $totalLessons > 0
                    && $completedLessons >= $totalLessons,
            ];
        }

        foreach ($courses as &$course) {
            $course['percent'] = $course['lessons_count'] > 0
                ? (int) round(
                    ($course['lessons_completed'] / $course['lessons_count']) * 100
                )
                : 0;
        }
        unset($course);

        $totalLessons = (int) ($totals['total_lessons'] ?? 0);
        $completedLessons = min(
            $totalLessons,
            (int) ($totals['completed_lessons'] ?? 0)
        );

        return [
            'total_lessons' => $totalLessons,
            'completed_lessons' => $completedLessons,
            'total_modules' => (int) ($totals['total_modules'] ?? 0),
            'completed_modules' => (int) ($totals['completed_modules'] ?? 0),
            'percent' => $totalLessons > 0
                ? (int) round(($completedLessons / $totalLessons) * 100)
                : 0,
            'courses' => array_values($courses),
        ];
    }

    private function emptySummary(): array
    {
        return [
            'total_lessons' => 0,
            'completed_lessons' => 0,
            'total_modules' => 0,
            'completed_modules' => 0,
            'percent' => 0,
            'courses' => [],
        ];
    }
}
