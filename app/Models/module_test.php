<?php

namespace App\Models;

use App\Core\Model;

class ModuleTest extends Model
{
    protected $table = 'module_tests';
    protected $fillable = ['module_id', 'title', 'description', 'passing_score', 'max_attempts', 'time_limit_minutes', 'xp_reward', 'status'];

    // Relacionamento com módulo
    public function module()
    {
        return $this->belongsTo(Module::class, 'module_id');
    }

    // Questões da prova
    public function questions()
    {
        return $this->hasMany(TestQuestion::class, 'module_test_id')->orderBy('question_number');
    }

    // Verifica se o usuário já passou neste teste
    public function userPassed($userId)
    {
        $db = $this->db;
        $stmt = $db->prepare("SELECT COUNT(*) FROM user_module_tests WHERE user_id = ? AND module_test_id = ? AND passed = 1");
        $stmt->execute([$userId, $this->id]);
        return $stmt->fetchColumn() > 0;
    }

    // Registra tentativa
    public function recordAttempt($userId, $score, $passed)
    {
        $db = $this->db;
        // Buscar número de tentativas
        $stmt = $db->prepare("SELECT COUNT(*) FROM user_module_tests WHERE user_id = ? AND module_test_id = ?");
        $stmt->execute([$userId, $this->id]);
        $attempt = $stmt->fetchColumn() + 1;
        $xpEarned = $passed ? $this->xp_reward : 0;
        $stmt = $db->prepare("INSERT INTO user_module_tests (user_id, module_test_id, score, passed, xp_earned, attempt_number, started_at, completed_at) VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())");
        $stmt->execute([$userId, $this->id, $score, $passed, $xpEarned, $attempt]);
        if ($passed) {
            // Atualizar XP do usuário
            $stmt = $db->prepare("UPDATE users SET xp = xp + ? WHERE id = ?");
            $stmt->execute([$xpEarned, $userId]);
            // Marcar módulo como concluído e desbloquear próximo
            $module = $this->module();
            if ($module) {
                $module->completeForUser($userId);
            }
        }
    }
}