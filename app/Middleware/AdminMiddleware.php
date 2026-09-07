<?php

namespace App\Middleware;

use App\Core\Auth;

final class AdminMiddleware
{
    public function handle(): void
    {
        if (Auth::isAdmin()) {
            return;
        }

        http_response_code(403);
        echo 'Acesso negado.';
        exit;
    }
}
