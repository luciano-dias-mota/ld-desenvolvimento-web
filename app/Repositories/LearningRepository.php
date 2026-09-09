<?php

namespace App\Repositories;

use PDO;

class LearningRepository
{
    public function __construct(private PDO $db)
    {
    }

    public function publishedCourses(): array
    {
        return $this->db
            ->query("SELECT * FROM courses WHERE status = 'published' ORDER BY id ASC")
            ->fetchAll();
    }

    public function publishedModulesForCourses(array $courseIds, ?int $userId = null): array
    {
        if ($courseIds === []) {
            return [];
        }

        $courseIds = array_values(array_map('intval', $courseIds));
        $placeholders = implode(',', array_fill(0, count($courseIds), '?'));

        if ($userId === null) {
            $sql = "SELECT
                        m.*,
                        (SELECT COUNT(*) FROM lessons l WHERE l.module_id = m.id AND l.status = 'published') AS lessons_count,
                        0 AS lessons_completed
                    FROM modules m
                    WHERE m.course_id IN ({$placeholders})
                      AND m.status = 'published'
                    ORDER BY m.course_id ASC, m.module_number ASC, m.id ASC";
            $stmt = $this->db->prepare($sql);
            $stmt->execute($courseIds);

            return $stmt->fetchAll();
        }

        $sql = "SELECT
                    m.*,
                    (SELECT COUNT(*) FROM lessons l WHERE l.module_id = m.id AND l.status = 'published') AS lessons_count,
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
        $stmt = $this->db->prepare($sql);
        $stmt->execute(array_merge([$userId], $courseIds));

        return $stmt->fetchAll();
    }

    public function publishedLessonsForModule(int $moduleId): array
    {
        $stmt = $this->db->prepare(
            "SELECT *
             FROM lessons
             WHERE module_id = ? AND status = 'published'
             ORDER BY lesson_number ASC, id ASC"
        );
        $stmt->execute([$moduleId]);

        return $stmt->fetchAll();
    }

    public function lessonProgressMap(int $userId, array $lessonIds): array
    {
        if ($lessonIds === []) {
            return [];
        }

        $lessonIds = array_values(array_map('intval', $lessonIds));
        $placeholders = implode(',', array_fill(0, count($lessonIds), '?'));
        $stmt = $this->db->prepare(
            "SELECT lesson_id, completed
             FROM user_lesson_progress
             WHERE user_id = ? AND lesson_id IN ({$placeholders})"
        );
        $stmt->execute(array_merge([$userId], $lessonIds));

        $progressMap = [];
        foreach ($stmt->fetchAll() as $progress) {
            $progressMap[(int) $progress['lesson_id']] = (bool) $progress['completed'];
        }

        return $progressMap;
    }

    public function nextPublishedLesson(int $moduleId, int $lessonNumber): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT *
             FROM lessons
             WHERE module_id = ?
               AND status = 'published'
               AND lesson_number > ?
             ORDER BY lesson_number ASC, id ASC
             LIMIT 1"
        );
        $stmt->execute([$moduleId, $lessonNumber]);

        return $stmt->fetch() ?: null;
    }

    public function firstPublishedExercise(int $lessonId, bool $includeAnswer = false): ?array
    {
        $columns = $includeAnswer
            ? 'id, lesson_id, title, exercise_type, question, options, correct_answer, xp_reward, exercise_number, status'
            : 'id, lesson_id, title, exercise_type, question, options, xp_reward, exercise_number, status';

        $stmt = $this->db->prepare(
            "SELECT {$columns}
             FROM exercises
             WHERE lesson_id = ? AND status = 'published'
             ORDER BY exercise_number ASC, id ASC
             LIMIT 1"
        );
        $stmt->execute([$lessonId]);

        return $stmt->fetch() ?: null;
    }

    public function latestExerciseSubmission(int $userId, int $exerciseId): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT answer, is_correct, xp_earned
             FROM user_exercise_submissions
             WHERE user_id = ? AND exercise_id = ?
             ORDER BY id DESC
             LIMIT 1'
        );
        $stmt->execute([$userId, $exerciseId]);

        return $stmt->fetch() ?: null;
    }

    public function lockUser(int $userId): bool
    {
        $stmt = $this->db->prepare('SELECT id FROM users WHERE id = ? FOR UPDATE');
        $stmt->execute([$userId]);

        return (bool) $stmt->fetchColumn();
    }

    public function lockLessonProgress(int $userId, int $lessonId): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT id, completed
             FROM user_lesson_progress
             WHERE user_id = ? AND lesson_id = ?
             LIMIT 1
             FOR UPDATE'
        );
        $stmt->execute([$userId, $lessonId]);

        return $stmt->fetch() ?: null;
    }

    public function completeLessonProgress(int $progressId, int $xpEarned): void
    {
        $stmt = $this->db->prepare(
            'UPDATE user_lesson_progress
             SET completed = 1, completed_at = NOW(), xp_earned = ?
             WHERE id = ?'
        );
        $stmt->execute([$xpEarned, $progressId]);
    }

    public function createCompletedLessonProgress(int $userId, int $lessonId, int $xpEarned): void
    {
        $stmt = $this->db->prepare(
            'INSERT INTO user_lesson_progress
                (user_id, lesson_id, completed, completed_at, xp_earned)
             VALUES (?, ?, 1, NOW(), ?)'
        );
        $stmt->execute([$userId, $lessonId, $xpEarned]);
    }

    public function hasCorrectExerciseSubmission(int $userId, int $exerciseId): bool
    {
        $stmt = $this->db->prepare(
            'SELECT 1
             FROM user_exercise_submissions
             WHERE user_id = ? AND exercise_id = ? AND is_correct = 1
             LIMIT 1'
        );
        $stmt->execute([$userId, $exerciseId]);

        return (bool) $stmt->fetchColumn();
    }

    public function createExerciseSubmission(
        int $userId,
        int $exerciseId,
        string $answer,
        bool $isCorrect,
        int $xpEarned
    ): void {
        $stmt = $this->db->prepare(
            'INSERT INTO user_exercise_submissions
                (user_id, exercise_id, answer, is_correct, xp_earned, submitted_at)
             VALUES (?, ?, ?, ?, ?, NOW())'
        );
        $stmt->execute([$userId, $exerciseId, $answer, (int) $isCorrect, $xpEarned]);
    }

    public function addUserXp(int $userId, int $xp): void
    {
        if ($xp <= 0) {
            return;
        }

        $stmt = $this->db->prepare('UPDATE users SET xp = xp + ? WHERE id = ?');
        $stmt->execute([$xp, $userId]);
    }

    public function testQuestions(int $testId, bool $includeAnswer = false): array
    {
        $columns = $includeAnswer
            ? 'id, module_test_id, question, question_type, options, correct_answer, points, question_number'
            : 'id, module_test_id, question, question_type, options, points, question_number';

        $stmt = $this->db->prepare(
            "SELECT {$columns}
             FROM test_questions
             WHERE module_test_id = ?
             ORDER BY question_number ASC, id ASC"
        );
        $stmt->execute([$testId]);

        return $stmt->fetchAll();
    }

    public function countTestAttempts(int $userId, int $testId): int
    {
        $stmt = $this->db->prepare(
            'SELECT COUNT(*)
             FROM user_module_tests
             WHERE user_id = ? AND module_test_id = ?'
        );
        $stmt->execute([$userId, $testId]);

        return (int) $stmt->fetchColumn();
    }

    public function hasPassedTest(int $userId, int $testId): bool
    {
        $stmt = $this->db->prepare(
            'SELECT 1
             FROM user_module_tests
             WHERE user_id = ? AND module_test_id = ? AND passed = 1
             LIMIT 1'
        );
        $stmt->execute([$userId, $testId]);

        return (bool) $stmt->fetchColumn();
    }

    public function createTestAttempt(
        int $userId,
        int $testId,
        float $score,
        bool $passed,
        int $xpEarned,
        int $attemptNumber
    ): void {
        $stmt = $this->db->prepare(
            'INSERT INTO user_module_tests
                (user_id, module_test_id, score, passed, xp_earned, attempt_number, started_at, completed_at)
             VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())'
        );
        $stmt->execute([
            $userId,
            $testId,
            $score,
            (int) $passed,
            $xpEarned,
            $attemptNumber,
        ]);
    }

    public function previousPublishedModule(
        int $courseId,
        int $moduleNumber,
        int $moduleId
    ): ?array {
        $stmt = $this->db->prepare(
            "SELECT id, course_id, module_number, status
             FROM modules
             WHERE course_id = ?
               AND status = 'published'
               AND (
                    module_number < ?
                    OR (module_number = ? AND id < ?)
               )
             ORDER BY module_number DESC, id DESC
             LIMIT 1"
        );
        $stmt->execute([$courseId, $moduleNumber, $moduleNumber, $moduleId]);

        return $stmt->fetch() ?: null;
    }

    public function previousPublishedLessonIds(
        int $moduleId,
        int $lessonNumber,
        int $lessonId
    ): array {
        $stmt = $this->db->prepare(
            "SELECT id
             FROM lessons
             WHERE module_id = ?
               AND status = 'published'
               AND (
                    lesson_number < ?
                    OR (lesson_number = ? AND id < ?)
               )
             ORDER BY lesson_number ASC, id ASC"
        );
        $stmt->execute([$moduleId, $lessonNumber, $lessonNumber, $lessonId]);

        return array_map('intval', $stmt->fetchAll(\PDO::FETCH_COLUMN));
    }

    public function publishedLessonIdsForModule(int $moduleId): array
    {
        $stmt = $this->db->prepare(
            "SELECT id
             FROM lessons
             WHERE module_id = ? AND status = 'published'
             ORDER BY lesson_number ASC, id ASC"
        );
        $stmt->execute([$moduleId]);

        return array_map('intval', $stmt->fetchAll(\PDO::FETCH_COLUMN));
    }

    public function hasCompletedLesson(int $userId, int $lessonId): bool
    {
        $stmt = $this->db->prepare(
            'SELECT 1
             FROM user_lesson_progress
             WHERE user_id = ? AND lesson_id = ? AND completed = 1
             LIMIT 1'
        );
        $stmt->execute([$userId, $lessonId]);

        return (bool) $stmt->fetchColumn();
    }


}
