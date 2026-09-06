<?php

namespace App\Models;

use App\Core\Model;

class UserLessonProgress extends Model
{
    protected static string $table = 'user_lesson_progress';

    protected static array $fillable = [
        'user_id',
        'lesson_id',
        'completed',
        'completed_at',
        'xp_earned',
    ];
}
