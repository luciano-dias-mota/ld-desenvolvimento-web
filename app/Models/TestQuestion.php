<?php

namespace App\Models;

use App\Core\Model;

class TestQuestion extends Model
{
    protected static string $table = 'test_questions';

    protected static array $fillable = [
        'module_test_id',
        'question',
        'question_type',
        'options',
        'correct_answer',
        'points',
        'question_number',
    ];
}
