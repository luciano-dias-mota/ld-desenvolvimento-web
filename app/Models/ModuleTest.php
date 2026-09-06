<?php

namespace App\Models;

use App\Core\Database;
use App\Core\Model;
use PDO;

class ModuleTest extends Model
{
    protected static string $table = 'module_tests';

    protected static array $fillable = [
        'module_id',
        'title',
        'passing_score',
        'xp_reward',
        'status',
    ];

    public static function userPassed(int $testId, int $userId): bool
    {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare(
            'SELECT 1
             FROM user_module_tests
             WHERE user_id = ? AND module_test_id = ? AND passed = 1
             LIMIT 1'
        );
        $stmt->execute([$userId, $testId]);

        return (bool) $stmt->fetchColumn();
    }

    /**
     * Retorna as questões ordenadas. O Controller continua responsável por
     * corrigir a prova e registrar a tentativa dentro da transação principal.
     */
    public static function questionsForTest(int $testId): array
    {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare(
            'SELECT *
             FROM test_questions
             WHERE module_test_id = ?
             ORDER BY question_number ASC, id ASC'
        );
        $stmt->execute([$testId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
