<?php

namespace App\Models;

use App\Core\Model;

class UserExerciseSubmission extends Model
{
    protected static string $table = 'user_exercise_submissions';

    protected static array $fillable = [
        'user_id',
        'exercise_id',
        'answer',
        'is_correct',
        'xp_earned',
        'submitted_at',
    ];
}
