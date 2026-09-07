<?php

namespace App\Models;

use App\Core\Model;

class ModuleTest extends Model
{
    protected static string $table = 'module_tests';

    protected static array $fillable = [
        'module_id',
        'title',
        'description',
        'passing_score',
        'max_attempts',
        'time_limit_minutes',
        'xp_reward',
        'status',
    ];
}
