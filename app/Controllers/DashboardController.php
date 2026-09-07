<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Models\Certificate;

class DashboardController extends LearningController
{
    public function index(): void
    {
        $user = Auth::user();
        if (!$user) {
            $this->redirect('/login');
        }

        $courses = $this->db()
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
                    ) AS lessons_completed
                FROM modules m
                WHERE m.course_id IN ({$placeholders})
                  AND m.status = 'published'
                ORDER BY m.course_id ASC, m.module_number ASC, m.id ASC";

        $stmt = $this->db()->prepare($sql);
        $stmt->execute(array_merge([(int) $user['id']], $courseIds));
        $moduleRows = $this->progress()->decorateModuleStatuses((int) $user['id'], $stmt->fetchAll());

        $modulesByCourse = [];
        foreach ($moduleRows as $module) {
            $modulesByCourse[(int) $module['course_id']][] = $module;
        }

        $courseData = [];
        foreach ($courses as $course) {
            $modules = $modulesByCourse[(int) $course['id']] ?? [];
            $allPublishedModulesPassed = $modules !== [];

            foreach ($modules as &$module) {
                $module['lessons_count'] = (int) $module['lessons_count'];
                $module['lessons_completed'] = (int) $module['lessons_completed'];

                $moduleFullyCompleted = ($module['progress_status'] ?? 'locked') === 'completed'
                    && $module['lessons_count'] > 0
                    && $module['lessons_completed'] >= $module['lessons_count'];

                if (!$moduleFullyCompleted) {
                    $allPublishedModulesPassed = false;
                }
            }
            unset($module);

            // Certificados já emitidos permanecem acessíveis mesmo se o curso
            // receber novos conteúdos posteriormente.
            $certificate = Certificate::getUserCertificate((int) $user['id'], (int) $course['id']);

            $courseData[] = [
                'course' => $course,
                'modules' => $modules,
                'completed' => $allPublishedModulesPassed || $certificate !== null,
                'can_issue_certificate' => $allPublishedModulesPassed && $certificate === null,
                'certificate' => $certificate,
            ];
        }

        $this->view('dashboard/index', [
            'courses' => $courseData,
            'user' => $user,
        ]);
    }
}
