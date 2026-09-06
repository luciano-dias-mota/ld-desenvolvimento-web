<?php

namespace App\Models;

use App\Core\Model;

class Course extends Model
{
    protected static string $table = 'courses';

    protected static array $fillable = [
        'title',
        'slug',
        'description',
        'status',
    ];
}
