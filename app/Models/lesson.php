<?php

namespace App\Models;

use App\Core\Model;
use App\Core\Database;

class Lesson extends Model
{
    protected static string $table = 'lessons';

    /**
     * Retorna a próxima aula de um módulo, com base no número atual.
     *
     * @param int $moduleId
     * @param int $currentLessonNumber
     * @return array|null
     */
    public static function findNextLesson($moduleId, $currentLessonNumber)
    {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare(
            "SELECT * FROM lessons 
             WHERE module_id = ? AND lesson_number > ? 
             ORDER BY lesson_number ASC 
             LIMIT 1"
        );
        $stmt->execute([$moduleId, $currentLessonNumber]);
        return $stmt->fetch() ?: null;
    }

    /**
     * Verifica se o usuário concluiu esta aula.
     *
     * @param int $userId
     * @return bool
     */
    public function isCompletedByUser($userId)
    {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare(
            "SELECT COUNT(*) FROM user_lesson_progress 
             WHERE user_id = ? AND lesson_id = ? AND completed = 1"
        );
        $stmt->execute([$userId, $this->id]);
        return $stmt->fetchColumn() > 0;
    }

    /**
     * Marca a aula como concluída para o usuário, registrando o progresso e XP.
     *
     * @param int $userId
     * @return void
     */
    public function completeForUser($userId)
    {
        $db = Database::getInstance()->getConnection();

        // Verifica se já existe registro
        $stmt = $db->prepare(
            "SELECT id FROM user_lesson_progress 
             WHERE user_id = ? AND lesson_id = ?"
        );
        $stmt->execute([$userId, $this->id]);
        $existing = $stmt->fetchColumn();

        if ($existing) {
            $stmt = $db->prepare(
                "UPDATE user_lesson_progress 
                 SET completed = 1, completed_at = NOW(), xp_earned = ? 
                 WHERE id = ?"
            );
            $stmt->execute([$this->xp_reward, $existing]);
        } else {
            $stmt = $db->prepare(
                "INSERT INTO user_lesson_progress 
                 (user_id, lesson_id, completed, completed_at, xp_earned) 
                 VALUES (?, ?, 1, NOW(), ?)"
            );
            $stmt->execute([$userId, $this->id, $this->xp_reward]);
        }

        // Atualiza XP do usuário
        $stmt = $db->prepare("UPDATE users SET xp = xp + ? WHERE id = ?");
        $stmt->execute([$this->xp_reward, $userId]);
    }
}