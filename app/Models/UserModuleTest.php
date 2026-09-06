<?php

namespace App\Models;

use App\Core\Model;

class UserModuleTest extends Model
{
    protected static string $table = 'user_module_tests';

    protected static array $fillable = [
        'user_id',
        'module_test_id',
        'score',
        'passed',
        'xp_earned',
        'attempt_number',
        'started_at',
        'completed_at',
    ];
}
