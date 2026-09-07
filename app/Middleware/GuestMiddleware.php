<?php

namespace App\Middleware;

use App\Core\Auth;
use App\Core\Url;

final class GuestMiddleware
{
    public function handle(): void
    {
        if (!Auth::check()) {
            return;
        }

        header('Location: ' . Url::to('/dashboard'), true, 302);
        exit;
    }
}
