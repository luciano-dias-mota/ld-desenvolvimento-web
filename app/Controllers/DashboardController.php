<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;

class DashboardController extends Controller
{
    public function index(): void
    {
        $user = Auth::user();
        if (!$user) {
            $this->redirect('/login');
        }

        $courses = $this->db
            ->query("SELECT * FROM courses WHERE status = 'published' ORDER BY id ASC")
            ->fetchAll();

        if ($courses === []) {
            $this->view('dashboard/index', [
                'courses' => [],
                'user' => $user,
            ]);
            return;
        }

        $courseIds = array_map(static fn(array $course): int => (int) $course['id'], $courses);
        $placeholders = implode(',', array_fill(0, count($courseIds), '?'));

        $sql = "SELECT
                    m.*,
                    (
                        SELECT COUNT(*)
                        FROM lessons l
                        WHERE l.module_id = m.id AND l.status = 'published'
                    ) AS lessons_count,
                    (
                        SELECT COUNT(DISTINCT l2.id)
                        FROM lessons l2
                        INNER JOIN user_lesson_progress ulp ON ulp.lesson_id = l2.id
                        WHERE l2.module_id = m.id
                          AND l2.status = 'published'
                          AND ulp.user_id = ?
                          AND ulp.completed = 1
                    ) AS lessons_completed,
                    EXISTS(
                        SELECT 1
                        FROM module_tests mt
                        INNER JOIN user_module_tests umt ON umt.module_test_id = mt.id
                        WHERE mt.module_id = m.id
                          AND umt.user_id = ?
                          AND umt.passed = 1
                    ) AS passed
                FROM modules m
                WHERE m.course_id IN ({$placeholders})
                ORDER BY m.course_id ASC, m.module_number ASC, m.id ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(array_merge([(int) $user['id'], (int) $user['id']], $courseIds));
        $moduleRows = $stmt->fetchAll();

        $modulesByCourse = [];
        foreach ($moduleRows as $module) {
            $modulesByCourse[(int) $module['course_id']][] = $module;
        }

        $courseData = [];
        foreach ($courses as $course) {
            $modules = $modulesByCourse[(int) $course['id']] ?? [];
            $previousModulePassed = true;

            foreach ($modules as &$module) {
                $passed = (bool) $module['passed'];

                if ($passed) {
                    $module['status'] = 'completed';
                } elseif ($previousModulePassed) {
                    $module['status'] = 'active';
                } else {
                    $module['status'] = 'locked';
                }

                $module['lessons_count'] = (int) $module['lessons_count'];
                $module['lessons_completed'] = (int) $module['lessons_completed'];
                unset($module['passed']);

                $previousModulePassed = $passed;
            }
            unset($module);

            $courseData[] = [
                'course' => $course,
                'modules' => $modules,
            ];
        }

        $this->view('dashboard/index', [
            'courses' => $courseData,
            'user' => $user,
        ]);
    }
}
