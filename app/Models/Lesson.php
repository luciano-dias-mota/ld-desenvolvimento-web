<?php

namespace App\Models;

use App\Core\Model;

class Lesson extends Model
{
    protected static string $table = 'lessons';

    protected static array $fillable = [
        'module_id',
        'title',
        'slug',
        'description',
        'content',
        'video_url',
        'lesson_number',
        'xp_reward',
        'estimated_minutes',
        'status',
    ];
}
