<?php

namespace App\Models;

use App\Core\Model;

class Lesson extends Model
{
    protected $table = 'lessons';
    protected $fillable = ['module_id', 'title', 'slug', 'description', 'content', 'lesson_number', 'xp_reward', 'estimated_minutes', 'status'];

    // Relacionamento com módulo
    public function module()
    {
        return $this->belongsTo(Module::class, 'module_id');
    }

    // Retorna o exercício associado (se houver)
    public function exercise()
    {
        return $this->hasOne(Exercise::class, 'lesson_id');
    }

    // Verifica se o usuário concluiu esta aula
    public function isCompletedByUser($userId)
    {
        $db = $this->db;
        $stmt = $db->prepare("SELECT COUNT(*) FROM user_lesson_progress WHERE user_id = ? AND lesson_id = ? AND completed = 1");
        $stmt->execute([$userId, $this->id]);
        return $stmt->fetchColumn() > 0;
    }

    // Marca aula como concluída para o usuário
    public function completeForUser($userId)
    {
        $db = $this->db;
        // Verifica se já existe
        $stmt = $db->prepare("SELECT id FROM user_lesson_progress WHERE user_id = ? AND lesson_id = ?");
        $stmt->execute([$userId, $this->id]);
        $exists = $stmt->fetchColumn();
        if ($exists) {
            $stmt = $db->prepare("UPDATE user_lesson_progress SET completed = 1, completed_at = NOW() WHERE user_id = ? AND lesson_id = ?");
        } else {
            $stmt = $db->prepare("INSERT INTO user_lesson_progress (user_id, lesson_id, completed, completed_at, xp_earned) VALUES (?, ?, 1, NOW(), 0)");
        }
        $stmt->execute([$userId, $this->id]);
    }
}