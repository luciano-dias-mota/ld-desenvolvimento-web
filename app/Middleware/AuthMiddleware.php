<?php

namespace App\Middleware;

use App\Core\Auth;
use App\Core\Url;

final class AuthMiddleware
{
    public function handle(): void
    {
        if (Auth::check()) {
            return;
        }

        header('Location: ' . Url::to('/login'), true, 302);
        exit;
    }
}
