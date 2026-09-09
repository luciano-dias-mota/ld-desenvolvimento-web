<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Models\Certificate;
use App\Services\EmailVerificationService;

class DashboardController extends LearningController
{
    public function index(): void
    {
        $user = Auth::user();
        $isGuest = Auth::isGuest();

        if (!$user && !$isGuest) {
            $this->redirect('/register');
        }

        $courses = $this->learningRepository()->publishedCourses();

        if ($courses === []) {
            $this->view('dashboard/index', [
                'courses' => [],
                'user' => $user,
                'isGuest' => $isGuest,
                'emailVerificationEnabled' => false,
            ]);
            return;
        }

        $courseIds = array_map(
            static fn(array $course): int => (int) $course['id'],
            $courses
        );

        if ($isGuest) {
            $moduleRows = $this->learningRepository()->publishedModulesForCourses($courseIds);

            foreach ($moduleRows as &$module) {
                $moduleId = (int) $module['id'];
                $module['lessons_completed'] = $this->guestProgress()->completedLessonCount($moduleId);

                if ($this->guestProgress()->hasPassedModule($moduleId)) {
                    $module['progress_status'] = 'completed';
                } elseif ($this->guestProgress()->canAccessModule($module)) {
                    $module['progress_status'] = 'active';
                } else {
                    $module['progress_status'] = 'locked';
                }
            }
            unset($module);
        } else {
            $moduleRows = $this->learningRepository()->publishedModulesForCourses(
                $courseIds,
                (int) $user['id']
            );

            $moduleRows = $this->progress()->decorateModuleStatuses(
                (int) $user['id'],
                $moduleRows
            );
        }

        $modulesByCourse = [];

        foreach ($moduleRows as $module) {
            $modulesByCourse[(int) $module['course_id']][] = $module;
        }

        $courseData = [];

        foreach ($courses as $course) {
            $modules = $modulesByCourse[(int) $course['id']] ?? [];
            $allPublishedModulesPassed = !$isGuest && $modules !== [];

            foreach ($modules as &$module) {
                $module['lessons_count'] = (int) $module['lessons_count'];
                $module['lessons_completed'] = (int) $module['lessons_completed'];

                if (!$isGuest) {
                    $moduleFullyCompleted = ($module['progress_status'] ?? 'locked') === 'completed'
                        && $module['lessons_count'] > 0
                        && $module['lessons_completed'] >= $module['lessons_count'];

                    if (!$moduleFullyCompleted) {
                        $allPublishedModulesPassed = false;
                    }
                }
            }
            unset($module);

            $certificate = $isGuest
                ? null
                : Certificate::getUserCertificate((int) $user['id'], (int) $course['id']);

            $emailAllowed = !$isGuest && $this->canIssueCertificateFor($user);

            $courseData[] = [
                'course' => $course,
                'modules' => $modules,
                'completed' => !$isGuest && ($allPublishedModulesPassed || $certificate !== null),
                'can_issue_certificate' => !$isGuest
                    && $allPublishedModulesPassed
                    && $certificate === null
                    && $emailAllowed,
                'certificate' => $certificate,
                'certificate_blocked_by_email' => !$isGuest
                    && $allPublishedModulesPassed
                    && $certificate === null
                    && !$emailAllowed,
            ];
        }

        $verification = new EmailVerificationService($this->db());

        $this->view('dashboard/index', [
            'courses' => $courseData,
            'user' => $user,
            'isGuest' => $isGuest,
            'emailVerificationEnabled' => $verification->isEnabled(),
        ]);
    }
}
