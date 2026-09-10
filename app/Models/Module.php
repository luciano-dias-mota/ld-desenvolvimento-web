<?php

namespace App\Models;

use App\Core\Model;

class Module extends Model
{
    protected static string $table = 'modules';

    protected static array $fillable = [
        'course_id',
        'title',
        'slug',
        'description',
        'module_number',
        'xp_reward',
        'status',
    ];
}
