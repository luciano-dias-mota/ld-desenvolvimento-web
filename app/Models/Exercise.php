<?php

namespace App\Models;

use App\Core\Model;

class Exercise extends Model
{
    protected static string $table = 'exercises';

    protected static array $fillable = [
        'lesson_id',
        'title',
        'exercise_type',
        'question',
        'options',
        'correct_answer',
        'xp_reward',
        'exercise_number',
        'status',
    ];
}
