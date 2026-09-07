<?php

namespace App\Middleware;

use App\Core\Auth;
use App\Core\Url;

final class LearningMiddleware
{
    public function handle(): void
    {
        if (Auth::hasLearningAccess()) {
            return;
        }

        header('Location: ' . Url::to('/register'), true, 302);
        exit;
    }
}
