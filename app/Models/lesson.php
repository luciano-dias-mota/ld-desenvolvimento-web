<?php

namespace App\Models;

use App\Core\Database;
use App\Core\Model;
use PDO;

class Lesson extends Model
{
    protected static string $table = 'lessons';

    protected static array $fillable = [
        'module_id',
        'title',
        'slug',
        'content',
        'video_url',
        'lesson_number',
        'xp_reward',
        'status',
    ];

    public static function findNextLesson(int $moduleId, int $currentLessonNumber): ?array
    {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare(
            "SELECT *
             FROM lessons
             WHERE module_id = ?
               AND lesson_number > ?
               AND status = 'published'
             ORDER BY lesson_number ASC, id ASC
             LIMIT 1"
        );
        $stmt->execute([$moduleId, $currentLessonNumber]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public static function isCompletedByUser(int $lessonId, int $userId): bool
    {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare(
            'SELECT 1
             FROM user_lesson_progress
             WHERE user_id = ? AND lesson_id = ? AND completed = 1
             LIMIT 1'
        );
        $stmt->execute([$userId, $lessonId]);

        return (bool) $stmt->fetchColumn();
    }

    /**
     * Marca a aula como concluída e concede XP apenas na primeira conclusão.
     * Retorna true somente quando a conclusão foi registrada pela primeira vez.
     */
    public static function completeForUser(int $lessonId, int $userId): bool
    {
        $db = Database::getInstance()->getConnection();
        $ownsTransaction = !$db->inTransaction();

        if ($ownsTransaction) {
            $db->beginTransaction();
        }

        try {
            $stmt = $db->prepare('SELECT id FROM users WHERE id = ? FOR UPDATE');
            $stmt->execute([$userId]);
            if (!$stmt->fetchColumn()) {
                throw new \RuntimeException('Usuário não encontrado.');
            }

            $stmt = $db->prepare(
                "SELECT id, xp_reward
                 FROM lessons
                 WHERE id = ? AND status = 'published'
                 LIMIT 1"
            );
            $stmt->execute([$lessonId]);
            $lesson = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$lesson) {
                throw new \RuntimeException('Aula não encontrada ou indisponível.');
            }

            $stmt = $db->prepare(
                'SELECT id, completed
                 FROM user_lesson_progress
                 WHERE user_id = ? AND lesson_id = ?
                 LIMIT 1
                 FOR UPDATE'
            );
            $stmt->execute([$userId, $lessonId]);
            $progress = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($progress && (int) $progress['completed'] === 1) {
                if ($ownsTransaction) {
                    $db->commit();
                }
                return false;
            }

            $xpReward = max(0, (int) ($lesson['xp_reward'] ?? 0));

            if ($progress) {
                $stmt = $db->prepare(
                    'UPDATE user_lesson_progress
                     SET completed = 1, completed_at = NOW(), xp_earned = ?
                     WHERE id = ?'
                );
                $stmt->execute([$xpReward, $progress['id']]);
            } else {
                $stmt = $db->prepare(
                    'INSERT INTO user_lesson_progress
                        (user_id, lesson_id, completed, completed_at, xp_earned)
                     VALUES (?, ?, 1, NOW(), ?)'
                );
                $stmt->execute([$userId, $lessonId, $xpReward]);
            }

            if ($xpReward > 0) {
                $stmt = $db->prepare('UPDATE users SET xp = xp + ? WHERE id = ?');
                $stmt->execute([$xpReward, $userId]);
            }

            if ($ownsTransaction) {
                $db->commit();
            }

            return true;
        } catch (\Throwable $e) {
            if ($ownsTransaction && $db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }
}
